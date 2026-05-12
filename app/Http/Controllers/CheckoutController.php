<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ZiniPayService;
use App\Support\CartManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly ZiniPayService $ziniPay,
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

        // ── Create the order (same transaction for both COD and ZiniPay) ──────
        $order = DB::transaction(function () use ($request, $cart) {
            $shippingAddress = $this->resolveShippingAddress($request);

            $this->maybeAutoSaveAddress($request, $shippingAddress);

            $subtotal = $cart->items->sum(fn ($item) => $item->price * $item->quantity);
            $shippingCost = $this->calculateShippingCost($shippingAddress);
            [$couponId, $couponCode, $discount] = $this->resolveCoupon($request, $subtotal);
            $total = max(0, $subtotal - $discount + $shippingCost);

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => auth()->id(),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $request->payment_method,
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

            $order->statusHistories()->create([
                'status' => 'pending',
                'notes' => 'Order placed successfully.',
            ]);

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
                        'image' => $product->images->first()?->path,
                    ],
                ]);
            }

            // COD: clear cart inside transaction (atomic)
            if ($request->payment_method === PaymentMethod::COD->value) {
                $cart->items()->delete();
                $cart->delete();
            }

            return $order;
        });

        // ── COD: nothing more to do ──────────────────────────────────────────
        if ($order->payment_method === PaymentMethod::COD) {
            return redirect()->route('order.confirmation', $order->order_number);
        }

        // ── ZiniPay: create invoice then redirect to payment gateway ─────────
        return $this->initiateZiniPay($order, $cart, $request);
    }

    private function initiateZiniPay(Order $order, mixed $cart, PlaceOrderRequest $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        try {
            $invoicePayload = [
                'cus_name' => $order->shipping_address['name'],
                'cus_email' => $order->shipping_address['email'],
                'amount' => (int) round($order->total),   // amount from backend — never trusted from frontend
                'val_id' => $order->order_number,          // our reference; ZiniPay echoes it back
                'redirect_url' => route('payment.callback', $order->order_number),
                'cancel_url' => route('payment.cancel', $order->order_number),
                'webhook_url' => url('/api/zinipay/webhook'),
                'metadata' => ['order_id' => $order->order_number],
            ];

            $result = $this->ziniPay->createInvoice($invoicePayload);

            // Record a pending payment entry so the callback/webhook can update it
            Payment::create([
                'order_id' => $order->id,
                'method' => PaymentMethod::ZINIPAY->value,
                'amount' => $order->total,
                'status' => PaymentStatus::PENDING,
                'val_id' => $order->order_number,
            ]);

            // Clear the cart only after successful invoice creation
            $cart->items()->delete();
            $cart->delete();

            // Inertia::location() sends a 409 + Location header so the client
            // performs a hard redirect to the external ZiniPay payment page.
            return Inertia::location($result['payment_url']);

        } catch (\Throwable $e) {
            Log::error('[ZiniPay] Checkout initiation failed', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            // Mark the order as cancelled so it doesn't linger as unpaid
            $order->update(['status' => 'cancelled', 'cancellation_reason' => 'Payment gateway unavailable.']);
            $order->statusHistories()->create([
                'status' => 'cancelled',
                'notes' => 'Cancelled automatically: ZiniPay invoice creation failed.',
            ]);

            // Restore cart items so the customer can try again
            // (items were NOT yet deleted for ZiniPay path)
            return redirect()->route('checkout.index')
                ->with('error', 'Payment gateway is temporarily unavailable. Please try Cash on Delivery or try again later.');
        }
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

    private function calculateShippingCost(array $shippingAddress): float
    {
        $district = $shippingAddress['state'] ?? '';
        $city = $shippingAddress['city'] ?? '';

        if ($district === 'Dhaka' && $city === 'Inside Dhaka City Corporation') {
            return 60.0;
        }

        return 120.0;
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
