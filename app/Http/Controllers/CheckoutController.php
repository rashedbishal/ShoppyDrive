<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\HomepageSetting;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CartManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartManager $cartManager,
    ) {}

    public function index(Request $request): Response
    {
        $savedAddresses = [];

        if (auth()->check()) {
            $savedAddresses = Address::where('user_id', auth()->id())
                ->orderByDesc('is_default')
                ->orderBy('created_at')
                ->get()
                ->toArray();
        }

        return Inertia::render('checkout/Checkout', [
            'cart' => $this->cartManager->serializeForRequest($request),
            'savedAddresses' => $savedAddresses,
            'buyNowItem' => $this->resolveBuyNowItem($request),
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
        $isBuyNow = $request->boolean('is_buy_now');

        if ($isBuyNow) {
            // Never touches carts/cart_items — Buy Now must not disturb the
            // customer's real cart (which, for a logged-in user, the client
            // re-hydrates from the server cart on every page navigation).
            $cart = $this->buildBuyNowCart($request->validated('items', []));
        } else {
            if ($request->filled('items')) {
                if ($request->user() !== null) {
                    $this->cartManager->syncUserCart($request->user(), $request->validated('items'));
                } else {
                    $this->cartManager->syncSessionCart($request->session()->getId(), $request->validated('items'));
                }
            }

            $cart = $this->cartManager->getCurrentCart($request);

            abort_if(! $cart || $cart->items->isEmpty(), 422, 'Your cart is empty.');

            // Eager-load products so free_delivery checks don't N+1
            $cart->load('items.product');
        }

        $shippingAddress = $this->resolveShippingAddress($request);

        $subtotal = $cart->items->sum(fn ($item) => $item->price * $item->quantity);
        $shippingCost = $this->calculateShippingCost($shippingAddress, $cart, $subtotal);
        [$couponId, $couponCode, $discount] = $this->resolveCoupon($request, $subtotal);
        $total = max(0, $subtotal - $discount + $shippingCost);

        // ── COD ─────────────────────────────────────────────────────────────
        if ($request->payment_method === PaymentMethod::COD->value) {
            $order = DB::transaction(function () use ($request, $cart, $isBuyNow, $shippingAddress, $subtotal, $shippingCost, $couponId, $couponCode, $discount, $total) {
                $this->maybeAutoSaveAddress($request, $shippingAddress);

                $order = Order::create([
                    'order_number' => $this->generateOrderNumber(),
                    'user_id' => auth()->id(),
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'payment_method' => PaymentMethod::COD->value,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'discount' => $discount,
                    'coupon_id' => $couponId,
                    'coupon_code' => $couponCode,
                    'tax' => 0,
                    'total' => $total,
                    'shipping_address' => $shippingAddress,
                    'billing_address' => $shippingAddress,
                ]);

                $order->statusHistories()->create(['status' => 'pending', 'notes' => 'Order placed successfully.']);

                foreach ($cart->items as $item) {
                    $product = $item->product;
                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
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

                if (! $isBuyNow) {
                    $cart->items()->delete();
                    $cart->delete();
                }

                // A real order went through for this phone number, so any
                // abandoned-checkout rows tracked against it no longer apply.
                IncompleteOrder::where('phone', $shippingAddress['phone'])->delete();

                return $order;
            });

            return redirect()->route('order.confirmation', $order->order_number);
        }

        // ── Mobile Banking (Bkash / Nagad) ───────────────────────────────────
        return $this->initiateMobileBanking($request, $cart, $shippingAddress, $subtotal, $shippingCost, $couponId, $couponCode, $discount, $total);
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
    ): RedirectResponse {
        $itemsSnapshot = $cart->items->map(function ($item) {
            $product = $item->product;

            return [
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
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
        $orderNumber = $this->generateOrderNumber();

        $this->maybeAutoSaveAddress($request, $shippingAddress);

        PendingCheckout::create([
            'token' => $token,
            'user_id' => auth()->id(),
            'cart_id' => $cart->id,
            'checkout_data' => [
                'order_number' => $orderNumber,
                'shipping_address' => $shippingAddress,
                'items' => $itemsSnapshot,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
                'tax' => 0,
                'total' => $total,
            ],
            'expires_at' => now()->addHours(2),
        ]);

        return redirect()->route('payment.mobile.show', $token);
    }

    // ── Shipping calculation ─────────────────────────────────────────────────

    private function calculateShippingCost(array $shippingAddress, Cart $cart, float $subtotal): float
    {
        // 1. Free delivery threshold (set in announcement bar CMS)
        $threshold = $this->getFreeDeliveryThreshold();
        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        // 2. Per-product free delivery:
        //    If EVERY item in cart qualifies for free delivery → no charge
        if ($cart->items->isNotEmpty()) {
            $allFree = $cart->items->every(function ($item) {
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

        // 3. Standard location-based rate
        $district = $shippingAddress['state'] ?? '';
        $city = $shippingAddress['city'] ?? '';

        return ($district === 'Dhaka' && $city === 'Inside Dhaka City Corporation') ? 60.0 : 120.0;
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
     * Builds an in-memory, never-persisted Cart/CartItem pair for "Buy Now"
     * so the rest of this method (shipping calc, order-item creation, mobile
     * banking) can treat it exactly like a real cart — without ever writing
     * to the carts/cart_items tables, so the customer's actual cart is left
     * completely untouched.
     *
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int}>  $items
     */
    private function buildBuyNowCart(array $items): Cart
    {
        abort_if(count($items) !== 1, 422, 'Buy Now requires exactly one item.');

        $payload = $items[0];
        $product = Product::with('images')->findOrFail($payload['product_id']);
        $variant = ! empty($payload['variant_id'])
            ? ProductVariant::with('media')->findOrFail($payload['variant_id'])
            : null;
        $quantity = max(1, (int) $payload['quantity']);
        $price = $this->cartManager->resolvePrice($product->id, $variant?->id);

        $cartItem = new CartItem([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
            'price' => $price,
        ]);
        $cartItem->setRelation('product', $product);
        $cartItem->setRelation('variant', $variant);

        $cart = new Cart;
        $cart->setRelation('items', collect([$cartItem]));

        return $cart;
    }

    /** @return array{0: int|null, 1: string|null, 2: float} */
    private function resolveCoupon(PlaceOrderRequest $request, float $subtotal): array
    {
        $code = $request->coupon_code ? strtoupper(trim($request->coupon_code)) : null;
        if (! $code) {
            return [null, null, 0.0];
        }

        $coupon = Coupon::where('code', $code)->where('is_active', true)->first();
        if (! $coupon) {
            return [null, null, 0.0];
        }

        $discount = $coupon->type->value === 'percent'
            ? min($subtotal, $subtotal * $coupon->value / 100)
            : min($subtotal, $coupon->value);

        $coupon->increment('used_count');

        return [$coupon->id, $coupon->code, round($discount, 2)];
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
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
