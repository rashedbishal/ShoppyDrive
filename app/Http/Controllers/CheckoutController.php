<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\HomepageSetting;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Support\CartManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartManager $cartManager,
    ) {}

    public function index(): Response
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
            'cart' => $this->cartManager->serializeForRequest(request()),
            'savedAddresses' => $savedAddresses,
        ]);
    }

    public function store(PlaceOrderRequest $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
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

        $shippingAddress = $this->resolveShippingAddress($request);

        $subtotal = $cart->items->sum(fn ($item) => $item->price * $item->quantity);
        $shippingCost = $this->calculateShippingCost($shippingAddress, $cart, $subtotal);
        [$couponId, $couponCode, $discount] = $this->resolveCoupon($request, $subtotal);
        $total = max(0, $subtotal - $discount + $shippingCost);

        // ── COD ─────────────────────────────────────────────────────────────
        if ($request->payment_method === PaymentMethod::COD->value) {
            $order = DB::transaction(function () use ($request, $cart, $shippingAddress, $subtotal, $shippingCost, $couponId, $couponCode, $discount, $total) {
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

                $cart->items()->delete();
                $cart->delete();

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
