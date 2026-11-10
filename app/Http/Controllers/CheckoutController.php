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
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CouponService;
use App\Services\InventoryService;
use App\Services\LoyaltyPointsService;
use App\Services\PendingCodLimit;
use App\Services\ReferralService;
use App\Services\ShippingCostCalculator;
use App\Support\CartManager;
use App\Support\OrderConfirmationAccess;
use App\Support\OrderNumberGenerator;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    private const string FREE_ORDER_MESSAGE = 'This order total is ৳0, so there is nothing to pay by bKash or Nagad. Please choose Cash on Delivery.';

    /** BIZ-024: the phone a referral discount was refused for in this session. */
    private const REFERRAL_REFUSED_SESSION_KEY = 'checkout.referral_refused_phone';

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
        private readonly ShippingCostCalculator $shippingCalculator,
        private readonly PendingCodLimit $pendingCodLimit,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $cart = $this->cartManager->serializeForRequest($request);
        $buyNowItem = $this->resolveBuyNowItem($request);

        // Nothing to check out — a direct/stale visit to /checkout with an
        // empty cart and no "Buy Now" item would otherwise render a blank
        // checkout page. Buy Now itself never touches the cart (see
        // resolveBuyNowItem()), so this can't false-positive on that flow.
        // A guest's cart lives only in their browser, so the server can't
        // tell it's empty: Checkout.tsx makes the same check for guests.
        if (auth()->check() && empty($cart['items']) && $buyNowItem === null) {
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
        // BIZ-018: Buy Now is part of what this order will pay for.
        $subtotal = (float) ($cart['subtotal'] ?? 0) + (float) ($buyNowItem['subtotal'] ?? 0);

        return Inertia::render('checkout/Checkout', [
            'cart' => $cart,
            // Fresh per page render, like LandingPageController::show() —
            // submitted back as order_token so a retried/double-clicked
            // submit of this exact render can't create a duplicate order
            // (CHECKOUT-COD-002).
            'orderToken' => (string) Str::uuid(),
            'savedAddresses' => $savedAddresses,
            'buyNowItem' => $buyNowItem,
            // BIZ-024: hidden once a submit was refused because the phone had
            // already received a referral discount (see store()).
            'referralDiscountEligible' => $this->referrals->isEligibleForDiscount($user) && ! $request->session()->has(self::REFERRAL_REFUSED_SESSION_KEY),
            'referralDiscountPreview' => $user && ! $request->session()->has(self::REFERRAL_REFUSED_SESSION_KEY) ? $this->referrals->calculateReferralDiscount($user, $subtotal) : 0.0,
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

        // FSA-05: a Buy Now link to a hidden product shows nothing to buy.
        if (! $product || ! $product->isPubliclyVisible()) {
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
        // BIZ-014: a retry of a submission that already placed its order
        // (network retry, double-click) must go straight to that order,
        // before anything below re-syncs the submitted items back into the
        // just-emptied cart. Same order as LandingPageController::placeOrder().
        // The check inside the lock further down stays for concurrent duplicates.
        if (($cached = Cache::get(self::orderResultKey($request->string('order_token')->toString()))) !== null) {
            return $this->redirectForCachedCheckoutOrder($cached);
        }

        // BIZ-023: the cache forgets on deploy; the order itself doesn't.
        if (($placed = $this->redirectForPlacedOrder($request->string('order_token')->toString())) !== null) {
            return $placed;
        }

        $items = $request->filled('items') ? $request->validated('items') : null;

        if ($items !== null) {
            // A hard-deleted product's id has nothing left to resolve a
            // price/stock/visibility against. Reported as a cart issue
            // and refused outright — not synced at all — rather than
            // silently writing the surviving items only: syncUserCart()
            // replaces the *entire* cart with whatever's passed, so
            // syncing a partial list here would wipe out every other
            // line still in the customer's real, stored cart too.
            $existingProductIds = Product::query()->whereIn('id', collect($items)->pluck('product_id'))->pluck('id')->all();
            $missingIds = collect($items)->pluck('product_id')->reject(fn ($id) => in_array($id, $existingProductIds, true))->values();

            if ($missingIds->isNotEmpty()) {
                // BE-002: a soft-deleted product keeps its name, so say which
                // item to remove. A permanently (force-)deleted one has
                // nothing left in the database at all — the real path an
                // admin actually uses (Admin\ProductController::destroy()
                // always force-deletes) — so fall back to whatever name the
                // page itself was showing for that line; only a product
                // that never existed at all (and so was never displayed)
                // stays generic.
                $deletedNames = Product::withTrashed()->whereIn('id', $missingIds)->pluck('name', 'id');
                $submittedNames = collect($items)->pluck('product_name', 'product_id');

                return $this->blockedByCartIssues($missingIds
                    ->map(fn ($id) => self::unavailableItemMessage($deletedNames[$id] ?? $submittedNames[$id] ?? null))
                    ->all());
            }
        }

        // BIZ-001: captured before syncUserCart()/syncSessionCart() below —
        // both call replaceItems(), which overwrites every item's price to
        // the current one, so comparing after that sync would just compare
        // the current price against itself. This compares against what the
        // checkout page actually showed the customer instead.
        $priceWasStale = $items !== null && $this->cartManager->submittedPricesStale($items);

        // The checkout page always submits its full, already-merged items
        // list (cart items plus a "Buy Now" item, if any — see
        // Checkout.tsx) — so syncing the cart to exactly that list and then
        // checking out from it handles a plain cart, a plain Buy Now, and a
        // Buy Now merged with an existing cart identically, with no special
        // casing needed here. Viewing the checkout page never reaches this
        // method, so an abandoned Buy Now still never touches the cart.
        // Always sync, even when the price was stale: replaceItems() always
        // writes the current resolved price, never the client's submitted
        // one, so this corrects the stored cart instead of leaving it frozen
        // at the old price — which is what let a customer get stuck
        // refusing the same stale price forever, unable to ever check out
        // (BIZ-001 retest, 2026-11-09).
        // BIZ-019: deleteOmitted: false — Checkout.tsx's items list is a
        // client-side snapshot that goes stale the instant another tab adds
        // something to the same cart while this page sits open; the
        // checkout page itself has no way to remove an item, so unlike the
        // general cart-sync call, an existing row missing from this
        // submission is never a deliberate removal. restrictToSubmittedItems()
        // below keeps this request's own view of the cart scoped to exactly
        // what it submitted, same as before — the extra row just survives
        // in the database instead of being deleted.
        if ($items !== null) {
            if ($request->user() !== null) {
                $this->cartManager->syncUserCart($request->user(), $items, deleteOmitted: false);
            } else {
                $this->cartManager->syncSessionCart($request->session()->getId(), $items, deleteOmitted: false);
            }
        }

        if ($priceWasStale) {
            // Checkout.tsx's displayed/submitted prices come from its own
            // client-side cart store, not the server's `cart` prop — so
            // without handing the corrected prices back directly, the
            // customer would see the exact same stale price again and hit
            // this same refusal every time they retried.
            return back()
                ->with('error', 'Some prices in your cart have changed. Please review your updated cart and try again.')
                ->with('priceUpdates', $this->cartManager->serializeForRequest($request));
        }

        $cart = $this->cartManager->getCurrentCart($request);

        abort_if(! $cart || $cart->items->isEmpty(), 422, 'Your cart is empty.');

        $cart = $this->cartManager->withoutDuplicateRows($cart);

        // Eager-load products so free_delivery checks don't N+1; category/
        // subCategory/brand too, since collectCartIssues()/validItems()
        // below call isPubliclyVisible(), which reads all three per item.
        $cart->load('items.product.category', 'items.product.subCategory', 'items.product.brand', 'items.variant');

        // BIZ-019: the sync above no longer deletes a row this submission
        // didn't mention, so without this, everything below — stock/price
        // checks, totals, the order itself, and the post-order cleanup —
        // would see any item added to the cart elsewhere while checkout was
        // open too, and either block on its unrelated stock issue or charge
        // for something the customer never reviewed on this page.
        if ($items !== null) {
            $cart = $this->cartManager->restrictToSubmittedItems($cart, $items);
        }

        // A deleted/deactivated product or an out-of-stock quantity must
        // block the whole checkout, not silently drop that line and place
        // the order with whatever's left — the customer has to go fix
        // their cart themselves. Checked against the full, un-filtered
        // item list, before validItems() would otherwise drop anything.
        $cartIssues = $this->collectCartIssues($cart);

        if ($cartIssues !== []) {
            return $this->blockedByCartIssues($cartIssues);
        }

        // Defense in depth only at this point — every item already passed
        // the check above, so this should never actually drop anything.
        $cart->setRelation('items', $this->cartManager->validItems($cart));

        abort_if($cart->items->isEmpty(), 422, 'Your cart is empty.');

        // A cart item's price is a snapshot frozen at the last add/sync
        // — it can go stale (a flash sale started, ended, or sold out)
        // between then and now. Re-resolve against current state and
        // refuse to silently charge a different amount than the
        // checkout page just showed. This already persists the corrected
        // price into the cart row; `priceUpdates` hands it to the client
        // too, since Checkout.tsx's own cart store — not the server's
        // `cart` prop — decides what's shown and resubmitted next
        // (BIZ-001 retest, 2026-11-09).
        if ($this->cartManager->revalidatePrices($cart)) {
            return back()
                ->with('error', 'Some prices in your cart have changed. Please review your updated cart and try again.')
                ->with('priceUpdates', $this->cartManager->serialize($cart));
        }

        $shippingAddress = $this->resolveShippingAddress($request);

        $subtotal = $cart->items->sum(fn ($item) => $item->price * $item->quantity);
        $shippingCost = $this->shippingCalculator->forCartWithBundles($shippingAddress, $cart->items, $subtotal);

        try {
            [$couponId, $couponCode, $couponDiscount] = $this->resolveCoupon($request, $subtotal, $cart);
        } catch (CouponUnavailableException $e) {
            // BIZ-013: withErrors(['coupon_code' => ...]) alone is invisible
            // here — no field on this page renders that key — so it needs
            // the same banner flash the 'items' refusals above already use.
            return back()->withErrors(['coupon_code' => $e->getMessage()])->with('error', $e->getMessage())->withInput();
        }

        // BIZ-024: the page showed this account's referral discount, but the
        // phone already received one on another account. Never charge more
        // than the page showed: refuse once with an explanation, and let the
        // re-rendered page (now without the discount) be submitted again.
        if ($refusal = $this->refuseReferralAlreadyUsedByPhone($request, $subtotal, $couponDiscount)) {
            return $refusal;
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
        $resultKey = self::orderResultKey($orderToken);

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
            // BIZ-013: same visibility issue as the coupon_code refusals below.
            $message = 'Your order is still being processed. Please wait a moment and try again.';

            return back()
                ->withErrors(['order_token' => $message])
                ->with('error', $message)
                ->withInput();
        }
    }

    private function refuseReferralAlreadyUsedByPhone(PlaceOrderRequest $request, float $subtotal, float $couponDiscount): ?RedirectResponse
    {
        $user = $request->user();
        $phone = (string) $request->phone;

        $pageShowedReferralDiscount = $user !== null
            && $couponDiscount <= 0
            && ! $request->boolean('redeem_points')
            && $this->referrals->isEligibleForDiscount($user)
            && $this->referrals->calculateReferralDiscount($user, $subtotal) > 0;

        if (! $pageShowedReferralDiscount
            || ! $this->referrals->referralDiscountUsedByPhone($phone, $user->id)
            || $request->session()->get(self::REFERRAL_REFUSED_SESSION_KEY) === $phone) {
            return null;
        }

        $request->session()->put(self::REFERRAL_REFUSED_SESSION_KEY, $phone);
        $message = 'The referral welcome discount was already used with this phone number, so it can\'t be applied again. Please review your total and place the order again.';

        return back()->with('error', $message)->withErrors(['referral' => $message])->withInput();
    }

    private static function orderResultKey(string $orderToken): string
    {
        return "checkout-order-result:{$orderToken}";
    }

    private function redirectForPlacedOrder(string $orderToken): ?RedirectResponse
    {
        $orderNumber = Order::withTrashed()->where('order_token', $orderToken)->value('order_number');

        return $orderNumber === null
            ? null
            : $this->redirectForCachedCheckoutOrder(['type' => 'cod', 'order_number' => $orderNumber]);
    }

    private function redirectForCachedCheckoutOrder(array $cached): RedirectResponse
    {
        if ($cached['type'] === 'cod') {
            OrderConfirmationAccess::grant(request(), $cached['order_number']);

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

                    // BIZ-011: before any stock is taken.
                    $this->pendingCodLimit->assertWithinLimit(
                        $shippingAddress['phone'],
                        $cart->items->groupBy('product_id')->map(fn ($lines) => (int) $lines->sum('quantity'))->all(),
                    );

                    $this->maybeAutoSaveAddress($request, $shippingAddress);

                    [$discount, $finalCouponId, $finalCouponCode, $usedReferralDiscount, $pointsToRedeem, $pointsDiscountAmount] =
                        $this->resolveFinalDiscount($request, $subtotal, $couponId, $couponCode, $couponDiscount, $lockedUser);

                    $total = max(0, $subtotal - $discount + $shippingCost);

                    $order = Order::create([
                        'order_number' => OrderNumberGenerator::generate(),
                        'order_token' => $request->string('order_token')->toString(),
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

                    $appliedCoupon = $finalCouponId !== null ? Coupon::find($finalCouponId) : null;

                    $this->inventory->lockStockInOrder($cart->items);

                    foreach ($cart->items as $item) {
                        $product = $item->product;
                        $flashSaleItemId = $this->inventory->decrementForOrderItem($order, $item->product_id, $item->variant_id, $item->quantity, (float) $item->price);
                        $order->items()->create([
                            'product_id' => $item->product_id,
                            'variant_id' => $item->variant_id,
                            'flash_sale_item_id' => $flashSaleItemId,
                            'bundle_id' => $item->bundle_id,
                            'coupon_applied' => $this->couponApplied($appliedCoupon, $item),
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
                                'image' => $item->variant?->image ?? $product->primaryImagePath(),
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

                    // BIZ-019: only the rows this order was actually built
                    // from — $cart->items() (the relation query) ignores the
                    // in-memory restrictToSubmittedItems() narrowing above
                    // and would otherwise delete every row for this cart,
                    // including anything added to it elsewhere while this
                    // checkout was in flight. The cart row itself cascades
                    // its items on delete, so it's only removed once none
                    // are left.
                    $cart->items()->whereKey($cart->items->pluck('id'))->delete();

                    if ($cart->items()->doesntExist()) {
                        $cart->delete();
                    }

                    // A real order went through for this phone number (or
                    // this logged-in customer, who may have used a different
                    // phone number than their abandoned attempt), so any
                    // abandoned-checkout rows tracked against it no longer apply.
                    IncompleteOrder::deleteCompletedFor($shippingAddress['phone'], $order->user_id);

                    return $order;
                }, 3);
            } catch (UniqueConstraintViolationException $e) {
                // BIZ-023: another request placed this exact submission first.
                return $this->redirectForPlacedOrder($request->string('order_token')->toString()) ?? throw $e;
            } catch (InsufficientStockException $e) {
                // `withErrors(['items' => ...])` alone is invisible to the
                // customer: Checkout.tsx only renders field-level errors for
                // the named shipping-address inputs, nothing reads an
                // `items` key. Also flashing it as `error` surfaces it
                // through the same banner CartChangedException's message
                // already uses below — `withErrors` stays, since several
                // existing tests assert `assertSessionHasErrors('items')`.
                return back()
                    ->withErrors(['items' => $e->getMessage()])
                    ->with('error', $e->getMessage())
                    ->withInput();
            } catch (CouponUnavailableException $e) {
                return back()->withErrors(['coupon_code' => $e->getMessage()])->with('error', $e->getMessage())->withInput();
            } catch (CartChangedException $e) {
                return back()->with('error', $e->getMessage());
            }

            Cache::put($resultKey, ['type' => 'cod', 'order_number' => $order->order_number], now()->addHours(self::ORDER_TOKEN_RESULT_TTL_HOURS));
            OrderConfirmationAccess::grant($request, $order->order_number);

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

                // BIZ-020: nothing to send means no SMS could ever confirm it,
                // so the order would wait forever.
                if ($total <= 0) {
                    throw ValidationException::withMessages(['payment_method' => self::FREE_ORDER_MESSAGE]);
                }

                return $this->initiateMobileBanking(
                    $request, $cart, $shippingAddress, $subtotal, $shippingCost,
                    $finalCouponId, $finalCouponCode, $discount, $total,
                    $usedReferralDiscount, $pointsToRedeem, $pointsDiscountAmount,
                );
            }, 3);
        } catch (ValidationException) {
            return back()
                ->withErrors(['payment_method' => self::FREE_ORDER_MESSAGE])
                ->with('error', self::FREE_ORDER_MESSAGE)
                ->withInput();
        } catch (CartChangedException $e) {
            return back()->with('error', $e->getMessage());
        } catch (InsufficientStockException $e) {
            return back()
                ->withErrors(['items' => $e->getMessage()])
                ->with('error', $e->getMessage())
                ->withInput();
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

        // BIZ-024: the authoritative check includes the checkout phone.
        if ($user && $this->referrals->isEligibleForDiscount($user, $request->phone)) {
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
        // BIZ-006: nothing else here ever checked stock before this point —
        // decrementForOrderItem() only runs once the customer has already
        // paid and submitted their TrxID (MobilePaymentController::submit()).
        foreach ($cart->items as $item) {
            $this->inventory->assertAvailable($item->product_id, $item->variant_id, $item->quantity, $item->price);
        }

        $appliedCoupon = $couponId !== null ? Coupon::find($couponId) : null;

        $itemsSnapshot = $cart->items->map(function ($item) use ($appliedCoupon) {
            $product = $item->product;

            return [
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'bundle_id' => $item->bundle_id,
                'coupon_applied' => $this->couponApplied($appliedCoupon, $item),
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
                    'image' => $item->variant?->image ?? $product->primaryImagePath(),
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
    private function resolveCoupon(PlaceOrderRequest $request, float $subtotal, Cart $cart): array
    {
        $code = $request->coupon_code ? strtoupper(trim($request->coupon_code)) : null;
        if (! $code) {
            return [null, null, 0.0];
        }

        $coupon = Coupon::where('code', $code)->first();

        if ($coupon === null) {
            throw CouponUnavailableException::because('Invalid coupon code.');
        }

        $eligibleLines = $coupon->eligibleLines($cart->items->map(fn ($item): array => [
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'subtotal' => (float) ($item->price * $item->quantity),
        ])->all());

        if ($eligibleLines === []) {
            throw CouponUnavailableException::because(Coupon::NOT_APPLICABLE_MESSAGE);
        }

        $eligibleSubtotal = $coupon->appliesToEverything() ? $subtotal : (float) array_sum(array_column($eligibleLines, 'subtotal'));
        $error = $coupon->eligibilityError($eligibleSubtotal, auth()->id(), $request->phone);

        if ($error !== null) {
            throw CouponUnavailableException::because($error);
        }

        return [$coupon->id, $coupon->code, $coupon->discountFor($eligibleSubtotal)];
    }

    /**
     * Recorded so refunds can count a selected-items coupon's discount against
     * the lines it covered. Null for every other order.
     */
    private function couponApplied(?Coupon $coupon, CartItem $item): ?bool
    {
        if ($coupon === null || $coupon->appliesToEverything()) {
            return null;
        }

        return $coupon->appliesToProduct($item->product, $item->variant_id);
    }

    /**
     * Every reason this cart can't check out as submitted: a line whose
     * product was deleted/deactivated (directly, or via its brand/
     * category/sub-category — see Product::isPubliclyVisible()), or
     * doesn't have enough stock for the requested quantity. Collected
     * against every item (not just the first problem found) so the
     * customer sees the whole picture in one pass, not one error per
     * resubmission.
     *
     * @return list<string>
     */
    private function collectCartIssues(Cart $cart): array
    {
        $issues = [];

        foreach ($cart->items as $item) {
            if ($item->product === null || ! $item->product->isPubliclyVisible()) {
                // BE-002: `product` is null for a soft-deleted product (name
                // still on file via withTrashed()) or a permanently deleted
                // one — product_id is nullOnDelete, so this row survives
                // with its own product_name snapshot instead of nothing at
                // all to name it with.
                $issues[] = self::unavailableItemMessage(
                    $item->product?->name
                    ?? ($item->product_id !== null ? $item->product()->withTrashed()->value('name') : null)
                    ?? $item->product_name
                );

                continue;
            }

            try {
                $this->inventory->assertAvailable($item->product_id, $item->variant_id, $item->quantity, (float) $item->price);
            } catch (InsufficientStockException $e) {
                $issues[] = $e->getMessage();
            }
        }

        return $issues;
    }

    private static function unavailableItemMessage(?string $productName): string
    {
        return ($productName ?? 'An item in your cart').' is no longer available. Please remove it from your cart to continue.';
    }

    /**
     * @param  list<string>  $issues
     */
    private function blockedByCartIssues(array $issues): RedirectResponse
    {
        return back()
            ->with('cartIssues', $issues)
            ->with('error', 'Some items in your cart need attention before you can place this order.')
            // withErrors(['items' => ...]) kept alongside the new
            // cartIssues flash for every existing caller/test that reads
            // the plain `items` field error this already used to set.
            ->withErrors(['items' => implode(' ', $issues)]);
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
