<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\CartChangedException;
use App\Exceptions\CouponUnavailableException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\HomepageSetting;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\BundleService;
use App\Services\CouponService;
use App\Services\InventoryService;
use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;
use App\Support\CartManager;
use App\Support\OrderNumberGenerator;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    // CHECKOUT-COD-002: same idempotency-cache window as
    // LandingPageController's ORDER_TOKEN_RESULT_TTL_HOURS — matches
    // PendingCheckout's own existing 2-hour expiry convention.
    private const ORDER_TOKEN_RESULT_TTL_HOURS = 2;

    // CHECKOUT-COD-002: same reasoning as LandingPageController's
    // ORDER_TOKEN_LOCK_WAIT_SECONDS — order creation here is a handful of
    // queries, well under this.
    private const ORDER_TOKEN_LOCK_WAIT_SECONDS = 10;

    public function __construct(
        private readonly CartManager $cartManager,
        private readonly ReferralService $referrals,
        private readonly LoyaltyPointsService $loyalty,
        private readonly InventoryService $inventory,
        private readonly CouponService $coupons,
        private readonly BundleService $bundles,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $cart = $this->cartManager->serializeForRequest($request);
        $buyNowItem = $this->resolveBuyNowItem($request);

        // Nothing to check out — a direct/stale visit to /checkout with an
        // empty cart and no "Buy Now" item would otherwise render a blank
        // checkout page. Buy Now itself never touches the cart (see
        // resolveBuyNowItem()), so this can't false-positive on that flow.
        if (empty($cart['items']) && $buyNowItem === null) {
            return redirect()->route('home');
        }

        $savedAddresses = [];

        if (auth()->check()) {
            $savedAddresses = Address::where('user_id', auth()->id())
                ->orderByDesc('is_default')
                ->orderBy('created_at')
                ->get()
                ->toArray();
        }

        $user = $request->user();
        $subtotal = (float) ($cart['subtotal'] ?? 0);

        return Inertia::render('checkout/Checkout', [
            'cart' => $cart,
            // Fresh per page render, like LandingPageController::show() —
            // submitted back as order_token so a retried/double-clicked
            // submit of this exact render can't create a duplicate order
            // (CHECKOUT-COD-002).
            'orderToken' => (string) Str::uuid(),
            'savedAddresses' => $savedAddresses,
            'buyNowItem' => $buyNowItem,
            'referralDiscountEligible' => $this->referrals->isEligibleForDiscount($user),
            'referralDiscountPreview' => $user ? $this->referrals->calculateReferralDiscount($user, $subtotal) : 0.0,
            // Spendable points, not the raw balance: anything held by an
            // unpaid pending checkout can't be redeemed here.
            'pointsBalance' => $user ? $this->loyalty->availablePoints($user) : 0,
            'pointsValue' => $this->loyalty->pointsValue(),
            'pointsRedeemMaxPercent' => $this->loyalty->redeemMaxPercent(),
        ]);
    }

    /**
     * "Buy Now" on the product page links here with the product/variant/
     * quantity in the query string instead of going through the cart, so the
     * checkout page can show just this one item without ever touching the
     * customer's real cart.
     *
     * @return array<string, mixed>|null
     */
    private function resolveBuyNowItem(Request $request): ?array
    {
        $productId = $request->integer('buy_now_product');

        if ($productId <= 0) {
            return null;
        }

        $product = Product::with('images')->find($productId);

        if (! $product) {
            return null;
        }

        $variantId = $request->filled('buy_now_variant') ? $request->integer('buy_now_variant') : null;
        $variant = $variantId ? ProductVariant::with('media')->find($variantId) : null;
        $quantity = max(1, $request->integer('buy_now_qty', 1));
        $price = $this->cartManager->resolvePrice($product->id, $variant?->id);

        return [
            'id' => 0,
            'item_key' => $variant ? "variant:{$variant->id}" : "product:{$product->id}",
            'product' => $product->toArray(),
            'variant' => $variant?->toArray(),
            'quantity' => $quantity,
            'price' => $price,
            'formatted_price' => $this->cartManager->formatMoney($price),
            'subtotal' => $price * $quantity,
            'formatted_subtotal' => $this->cartManager->formatMoney($price * $quantity),
        ];
    }

    public function store(PlaceOrderRequest $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        // The checkout page always submits its full, already-merged items
        // list (cart items plus a "Buy Now" item, if any — see
        // Checkout.tsx) — so syncing the cart to exactly that list and then
        // checking out from it handles a plain cart, a plain Buy Now, and a
        // Buy Now merged with an existing cart identically, with no special
        // casing needed here. Viewing the checkout page never reaches this
        // method, so an abandoned Buy Now still never touches the cart.
        if ($request->filled('items')) {
            if ($request->user() !== null) {
                $this->cartManager->syncUserCart($request->user(), $request->validated('items'));
            } else {
                $this->cartManager->syncSessionCart($request->session()->getId(), $request->validated('items'));
            }
        }

        $cart = $this->cartManager->getCurrentCart($request);

        abort_if(! $cart || $cart->items->isEmpty(), 422, 'Your cart is empty.');

        $cart = $this->cartManager->withoutDuplicateRows($cart);

        // Eager-load products so free_delivery checks don't N+1
        $cart->load('items.product');

        // Drops items whose product was deleted/deactivated since being
        // added to the cart — every downstream read of `$cart->items`
        // (shipping calc, order-item creation) assumes `item->product`
        // exists, so this has to happen before any of that runs.
        $cart->setRelation('items', $this->cartManager->validItems($cart));

        abort_if($cart->items->isEmpty(), 422, 'Your cart is empty.');

        // A cart item's price is a snapshot frozen at the last add/sync
        // — it can go stale (a flash sale started, ended, or sold out)
        // between then and now. Re-resolve against current state and
        // refuse to silently charge a different amount than the
        // checkout page just showed; the redirect back re-renders the
        // cart with the corrected prices for the customer to confirm.
        if ($this->cartManager->revalidatePrices($cart)) {
            return back()->with('error', 'Some prices in your cart have changed. Please review your updated cart and try again.');
        }

        $shippingAddress = $this->resolveShippingAddress($request);

        $subtotal = $cart->items->sum(fn ($item) => $item->price * $item->quantity);
        $shippingCost = $this->calculateShippingCost($shippingAddress, $cart, $subtotal);

        try {
            [$couponId, $couponCode, $couponDiscount] = $this->resolveCoupon($request, $subtotal);
        } catch (CouponUnavailableException $e) {
            return back()->withErrors(['coupon_code' => $e->getMessage()])->withInput();
        }

        // CHECKOUT-COD-002: same order_token/Cache::lock() idempotency
        // pattern as LandingPageController::placeOrder() — a repeated
        // submission (double-click, network retry, back-button resubmit)
        // of this exact page render replays the cached outcome instead of
        // creating a second Order/PendingCheckout; a concurrent duplicate
        // blocks on the lock instead of racing it. Only a final successful
        // outcome is cached (see completeCheckout()), so a failed attempt
        // never poisons the token and a genuine retry still works.
        $orderToken = $request->string('order_token')->toString();
        $resultKey = "checkout-order-result:{$orderToken}";

        if (($cached = Cache::get($resultKey)) !== null) {
            return $this->redirectForCachedCheckoutOrder($cached);
        }

        $lock = Cache::lock("checkout-order-lock:{$orderToken}", self::ORDER_TOKEN_LOCK_WAIT_SECONDS * 3);

        try {
            return $lock->block(self::ORDER_TOKEN_LOCK_WAIT_SECONDS, function () use (
                $request, $cart, $shippingAddress, $subtotal, $shippingCost, $couponId, $couponCode, $couponDiscount, $resultKey
            ) {
                // The other holder may have finished and released between
                // our first check above and actually acquiring the lock.
                if (($cached = Cache::get($resultKey)) !== null) {
                    return $this->redirectForCachedCheckoutOrder($cached);
                }

                return $this->completeCheckout($request, $cart, $shippingAddress, $subtotal, $shippingCost, $couponId, $couponCode, $couponDiscount, $resultKey);
            });
        } catch (LockTimeoutException) {
            // Someone else is actively processing this exact submission and
            // hasn't finished yet — never fall through to creating a second
            // order/PendingCheckout; ask the customer to wait rather than
            // guess at what happened.
            return back()
                ->withErrors(['order_token' => 'Your order is still being processed. Please wait a moment and try again.'])
                ->withInput();
        }
    }

    private function redirectForCachedCheckoutOrder(array $cached): RedirectResponse
    {
        if ($cached['type'] === 'cod') {
            return redirect()->route('order.confirmation', $cached['order_number']);
        }

        return redirect()->route('payment.mobile.show', $cached['pending_token']);
    }

    /**
     * @param  array<string, mixed>  $shippingAddress
     */
    private function completeCheckout(
        PlaceOrderRequest $request,
        Cart $cart,
        array $shippingAddress,
        float $subtotal,
        float $shippingCost,
        ?int $couponId,
        ?string $couponCode,
        float $couponDiscount,
        string $resultKey,
    ): RedirectResponse {
        // ── COD ─────────────────────────────────────────────────────────────
        if ($request->payment_method === PaymentMethod::COD->value) {
            try {
                $order = DB::transaction(function () use ($request, $cart, $shippingAddress, $subtotal, $shippingCost, $couponId, $couponCode, $couponDiscount) {
                    // Locking the purchaser's row here (rather than before the
                    // transaction) lets us re-check referral/points eligibility
                    // against a guaranteed-fresh row, so two concurrent COD
                    // checkouts by the same user can never both consume the
                    // one-time referral discount or double-spend points. It
                    // must stay the first statement: see
                    // LoyaltyPointsService::availablePoints().
                    $lockedUser = auth()->check() ? $this->referrals->lockUser(auth()->id()) : null;
                    $this->cartManager->lockForCheckout($cart);

                    $this->maybeAutoSaveAddress($request, $shippingAddress);

                    [$discount, $finalCouponId, $finalCouponCode, $usedReferralDiscount, $pointsToRedeem, $pointsDiscountAmount] =
                        $this->resolveFinalDiscount($request, $subtotal, $couponId, $couponCode, $couponDiscount, $lockedUser);

                    $total = max(0, $subtotal - $discount + $shippingCost);

                    $order = Order::create([
                        'order_number' => OrderNumberGenerator::generate(),
                        'user_id' => auth()->id(),
                        'status' => 'pending',
                        'payment_status' => 'unpaid',
                        'payment_method' => PaymentMethod::COD->value,
                        'subtotal' => $subtotal,
                        'shipping_cost' => $shippingCost,
                        'discount' => $discount,
                        'coupon_id' => $finalCouponId,
                        'coupon_code' => $finalCouponCode,
                        'used_referral_discount' => $usedReferralDiscount,
                        'points_redeemed' => $pointsToRedeem ?: null,
                        'tax' => 0,
                        'total' => $total,
                        'shipping_address' => $shippingAddress,
                        'billing_address' => $shippingAddress,
                    ]);

                    $order->statusHistories()->create(['status' => 'pending', 'notes' => 'Order placed successfully.']);

                    foreach ($cart->items as $item) {
                        $product = $item->product;
                        $flashSaleItemId = $this->inventory->decrementForOrderItem($order, $item->product_id, $item->variant_id, $item->quantity, (float) $item->price);
                        $order->items()->create([
                            'product_id' => $item->product_id,
                            'variant_id' => $item->variant_id,
                            'flash_sale_item_id' => $flashSaleItemId,
                            'bundle_id' => $item->bundle_id,
                            'product_name' => $product->name,
                            'sku' => $item->variant?->sku ?? $product->sku,
                            'price' => $item->price,
                            'quantity' => $item->quantity,
                            'subtotal' => $item->price * $item->quantity,
                            'product_snapshot' => [
                                'id' => $product->id,
                                'name' => $product->name,
                                'sku' => $product->sku,
                                'price' => $item->price,
                                'image' => $item->variant?->image ?? $product->images->first()?->path,
                                'variant_attributes' => $item->variant?->attributes,
                            ],
                        ]);
                    }

                    if ($finalCouponId !== null) {
                        $this->coupons->consume($finalCouponId, $order, auth()->id(), $discount, $shippingAddress['phone'] ?? null);
                    }

                    if ($lockedUser) {
                        if ($usedReferralDiscount) {
                            $this->referrals->markReferralDiscountUsed($order, $lockedUser);
                        }
                        if ($pointsToRedeem > 0) {
                            $this->loyalty->debitPointsForOrder($order, $lockedUser, $pointsToRedeem, $pointsDiscountAmount);
                        }
                    }

                    $cart->items()->delete();
                    $cart->delete();

                    // A real order went through for this phone number (or
                    // this logged-in customer, who may have used a different
                    // phone number than their abandoned attempt), so any
                    // abandoned-checkout rows tracked against it no longer apply.
                    IncompleteOrder::deleteCompletedFor($shippingAddress['phone'], $order->user_id);

                    return $order;
                });
            } catch (InsufficientStockException $e) {
                return back()->withErrors(['items' => $e->getMessage()])->withInput();
            } catch (CouponUnavailableException $e) {
                return back()->withErrors(['coupon_code' => $e->getMessage()])->withInput();
            } catch (CartChangedException $e) {
                return back()->with('error', $e->getMessage());
            }

            Cache::put($resultKey, ['type' => 'cod', 'order_number' => $order->order_number], now()->addHours(self::ORDER_TOKEN_RESULT_TTL_HOURS));

            return redirect()->route('order.confirmation', $order->order_number);
        }

        // ── Mobile Banking (Bkash / Nagad) ───────────────────────────────────
        // No Order exists yet — this only stages a PendingCheckout; the real
        // order is created later, under a lock, in
        // MobilePaymentController::submit(). The discount baked in here is
        // what the customer is told to pay and is NOT recomputed at submit()
        // time, because by then they have already sent the money. So redeemed
        // points are reserved now, under the same user lock as the COD path,
        // rather than rechecked at payment. Coupons and the referral discount
        // are not reserved and keep their existing residual risk.
        try {
            $token = DB::transaction(function () use ($request, $cart, $shippingAddress, $subtotal, $shippingCost, $couponId, $couponCode, $couponDiscount): string {
                $lockedUser = auth()->check() ? $this->referrals->lockUser(auth()->id()) : null;
                $this->cartManager->lockForCheckout($cart);
                [$discount, $finalCouponId, $finalCouponCode, $usedReferralDiscount, $pointsToRedeem, $pointsDiscountAmount] =
                    $this->resolveFinalDiscount($request, $subtotal, $couponId, $couponCode, $couponDiscount, $lockedUser);
                $total = max(0, $subtotal - $discount + $shippingCost);

                return $this->initiateMobileBanking(
                    $request, $cart, $shippingAddress, $subtotal, $shippingCost,
                    $finalCouponId, $finalCouponCode, $discount, $total,
                    $usedReferralDiscount, $pointsToRedeem, $pointsDiscountAmount,
                );
            }, 3);
        } catch (CartChangedException $e) {
            return back()->with('error', $e->getMessage());
        }

        Cache::put($resultKey, ['type' => 'mobile_banking', 'pending_token' => $token], now()->addHours(self::ORDER_TOKEN_RESULT_TTL_HOURS));

        return redirect()->route('payment.mobile.show', $token);
    }

    /**
     * Discount priority: an explicit coupon code wins outright; otherwise a
     * points redemption request is honored if the (possibly locked) user has
     * enough balance; otherwise an eligible referred customer's one-time
     * discount auto-applies. Only one of the three ever contributes.
     *
     * @return array{0: float, 1: int|null, 2: string|null, 3: bool, 4: int, 5: float}
     */
    private function resolveFinalDiscount(
        PlaceOrderRequest $request,
        float $subtotal,
        ?int $couponId,
        ?string $couponCode,
        float $couponDiscount,
        ?User $user,
    ): array {
        if ($couponDiscount > 0) {
            return [$couponDiscount, $couponId, $couponCode, false, 0, 0.0];
        }

        if ($user && $request->boolean('redeem_points')) {
            $requested = max(0, $request->integer('points_to_redeem'));
            $pointsToRedeem = min($requested, $this->loyalty->maxRedeemablePoints($user, $subtotal));
            $amount = $pointsToRedeem > 0 ? $this->loyalty->redeemPoints($user, $pointsToRedeem, $subtotal) : 0.0;

            if ($amount > 0) {
                return [$amount, null, null, false, $pointsToRedeem, $amount];
            }
        }

        if ($user && $this->referrals->isEligibleForDiscount($user)) {
            $referralDiscount = $this->referrals->calculateReferralDiscount($user, $subtotal);

            if ($referralDiscount > 0) {
                return [$referralDiscount, null, null, true, 0, 0.0];
            }
        }

        return [0.0, null, null, false, 0, 0.0];
    }

    private function initiateMobileBanking(
        PlaceOrderRequest $request,
        Cart $cart,
        array $shippingAddress,
        float $subtotal,
        float $shippingCost,
        ?int $couponId,
        ?string $couponCode,
        float $discount,
        float $total,
        bool $usedReferralDiscount,
        int $pointsRedeemed,
        float $pointsDiscountAmount,
    ): string {
        $itemsSnapshot = $cart->items->map(function ($item) {
            $product = $item->product;

            return [
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'bundle_id' => $item->bundle_id,
                'product_name' => $product->name,
                'sku' => $item->variant?->sku ?? $product->sku,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->price * $item->quantity,
                'product_snapshot' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $item->price,
                    'image' => $item->variant?->image ?? $product->images->first()?->path,
                    'variant_attributes' => $item->variant?->attributes,
                ],
            ];
        })->all();

        $token = (string) Str::uuid();
        $orderNumber = OrderNumberGenerator::generate();

        $this->maybeAutoSaveAddress($request, $shippingAddress);

        PendingCheckout::create([
            'token' => $token,
            'user_id' => auth()->id(),
            'cart_id' => $cart->id,
            'points_reserved' => $pointsRedeemed ?: null,
            'checkout_data' => [
                'order_number' => $orderNumber,
                'shipping_address' => $shippingAddress,
                'items' => $itemsSnapshot,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
                'used_referral_discount' => $usedReferralDiscount,
                'points_redeemed' => $pointsRedeemed,
                'points_discount_amount' => $pointsDiscountAmount,
                'tax' => 0,
                'total' => $total,
            ],
            'expires_at' => now()->addHours(2),
        ]);

        return $token;
    }

    // ── Shipping calculation ─────────────────────────────────────────────────

    private function calculateShippingCost(array $shippingAddress, Cart $cart, float $subtotal): float
    {
        // 1. Free delivery threshold (set in announcement bar CMS)
        $threshold = $this->getFreeDeliveryThreshold();
        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        // 2. Per-product free delivery (or an intact free-delivery bundle):
        //    If EVERY item in cart qualifies for free delivery → no charge
        if ($cart->items->isNotEmpty()) {
            $allFree = $cart->items->every(function ($item) {
                if ($this->bundles->itemQualifiesForFreeDelivery($item)) {
                    return true;
                }

                $product = $item->product;
                if (! $product->free_delivery) {
                    return false;
                }
                // min qty set? customer must order at least that many
                if ($product->free_delivery_min_qty !== null) {
                    return $item->quantity >= $product->free_delivery_min_qty;
                }

                return true;
            });

            if ($allFree) {
                return 0.0;
            }
        }

        // 3. Standard location-based rate (admin-configurable, see Setting::shippingRateFor())
        return Setting::shippingRateFor($shippingAddress['state'] ?? null, $shippingAddress['city'] ?? null);
    }

    private function getFreeDeliveryThreshold(): float
    {
        static $threshold = null;

        if ($threshold === null) {
            $bar = HomepageSetting::where('section_key', 'announcement_bar')->first();
            $threshold = (float) ($bar?->metadata['free_delivery_amount'] ?? 0);
        }

        return $threshold;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * A preview check only — it validates eligibility and computes the
     * discount amount, but does not touch `used_count`. Usage is only
     * ever counted inside CouponService::consume(), at the moment an
     * order is actually created (see CouponService's docblock for why).
     *
     * @return array{0: int|null, 1: string|null, 2: float}
     *
     * @throws CouponUnavailableException
     */
    private function resolveCoupon(PlaceOrderRequest $request, float $subtotal): array
    {
        $code = $request->coupon_code ? strtoupper(trim($request->coupon_code)) : null;
        if (! $code) {
            return [null, null, 0.0];
        }

        $coupon = Coupon::where('code', $code)->first();

        if ($coupon === null) {
            throw CouponUnavailableException::because('Invalid coupon code.');
        }

        $error = $coupon->eligibilityError($subtotal, auth()->id(), $request->phone);

        if ($error !== null) {
            throw CouponUnavailableException::because($error);
        }

        $discount = $coupon->type->value === 'percent'
            ? min($subtotal, $subtotal * $coupon->value / 100)
            : min($subtotal, $coupon->value);

        return [$coupon->id, $coupon->code, round($discount, 2)];
    }

    private function resolveShippingAddress(PlaceOrderRequest $request): array
    {
        $base = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ];

        if ($request->filled('address_id')) {
            $address = Address::where('id', $request->integer('address_id'))
                ->where('user_id', auth()->id())
                ->firstOrFail();

            return array_merge($base, [
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
            ]);
        }

        return array_merge($base, $request->only([
            'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
        ]));
    }

    private function maybeAutoSaveAddress(PlaceOrderRequest $request, array $shippingAddress): void
    {
        if (! auth()->check() || $request->filled('address_id')) {
            return;
        }

        $hasNoAddresses = Address::where('user_id', auth()->id())->doesntExist();

        if ($request->boolean('save_address') || $hasNoAddresses) {
            $isDefault = $hasNoAddresses;
            if ($isDefault) {
                Address::where('user_id', auth()->id())->update(['is_default' => false]);
            }

            Address::create([
                'user_id' => auth()->id(),
                'label' => 'Home',
                'name' => $shippingAddress['name'],
                'phone' => $shippingAddress['phone'],
                'address_line1' => $shippingAddress['address_line1'],
                'address_line2' => $shippingAddress['address_line2'] ?? null,
                'city' => $shippingAddress['city'],
                'state' => $shippingAddress['state'],
                'postal_code' => $shippingAddress['postal_code'] ?? null,
                'country' => $shippingAddress['country'] ?? 'Bangladesh',
                'is_default' => $isDefault,
            ]);
        }
    }
}
