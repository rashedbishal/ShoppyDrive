<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOrderRequest;
use App\Http\Requests\Admin\UpdateOrderShippingAddressRequest;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CourierOrderStatusMapper;
use App\Services\InventoryService;
use App\Services\PathaoCourierService;
use App\Services\SteadfastCourierService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private readonly PathaoCourierService $pathao,
        private readonly SteadfastCourierService $steadfast,
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request): Response
    {
        $orders = Order::with('user:id,name,email')
            ->when($request->search, fn ($q) => $q->where('order_number', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->payment_status, fn ($q) => $q->where('payment_status', $request->payment_status))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'payment_status']),
            'statuses' => array_map(
                fn (OrderStatus $status) => ['value' => $status->value, 'label' => ucfirst($status->value)],
                OrderStatus::cases(),
            ),
        ]);
    }

    /**
     * Minimal, typeahead-sized customer lookup for the "Create Order" modal
     * — not a replacement for CustomerController's full admin/customers
     * page. Returns each match's default address too, so the modal can
     * prefill shipping fields in one click instead of the admin re-typing
     * an address that's already on file.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        $customers = User::query()
            ->when($query !== '', fn ($q) => $q
                ->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(function (User $user) {
                $address = Address::where('user_id', $user->id)
                    ->orderByDesc('is_default')
                    ->orderBy('created_at')
                    ->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $address ? [
                        'address_line1' => $address->address_line1,
                        'address_line2' => $address->address_line2,
                        'city' => $address->city,
                        'state' => $address->state,
                        'postal_code' => $address->postal_code,
                    ] : null,
                ];
            });

        return response()->json($customers);
    }

    /**
     * Creates a real order directly from the admin panel — for a customer
     * who called/messaged in rather than checking out on the site. Reuses
     * the same InventoryService stock/flash-sale enforcement every other
     * order-creation path uses, so a phone order can't oversell any more
     * than a web one can. Deliberately skips coupon/referral/loyalty
     * logic (none of that applies to a manually-entered order) — same
     * scope as IncompleteOrderController::placeOrder().
     */
    public function store(CreateOrderRequest $request): RedirectResponse
    {
        $shippingAddress = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country' => 'Bangladesh',
        ];

        $items = $request->validated('items');
        $subtotal = array_sum(array_map(fn (array $item) => (float) $item['price'] * (int) $item['quantity'], $items));
        $shippingCost = (float) $request->shipping_cost;
        $discount = (float) ($request->discount ?? 0);
        $total = max(0, $subtotal - $discount + $shippingCost);
        $isPaid = $request->payment_method === 'mobile_banking';

        try {
            $order = DB::transaction(function () use ($request, $shippingAddress, $items, $subtotal, $shippingCost, $discount, $total, $isPaid): Order {
                $order = Order::create([
                    'order_number' => $this->generateOrderNumber(),
                    'user_id' => $request->customer_id,
                    'status' => 'pending',
                    'payment_status' => $isPaid ? 'paid' : 'unpaid',
                    'payment_method' => $request->payment_method,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'discount' => $discount,
                    'tax' => 0,
                    'total' => $total,
                    'shipping_address' => $shippingAddress,
                    'billing_address' => $shippingAddress,
                    'notes' => $request->notes,
                ]);

                $order->statusHistories()->create([
                    'status' => 'pending',
                    'notes' => 'Order placed manually by '.(auth('admin')->user()?->name ?? 'an admin').' on behalf of the customer.',
                ]);

                foreach ($items as $item) {
                    $product = Product::with('images')->findOrFail($item['product_id']);
                    $variant = $item['variant_id'] ?? null
                        ? ProductVariant::findOrFail($item['variant_id'])
                        : null;

                    $price = (float) $item['price'];
                    $quantity = (int) $item['quantity'];

                    $flashSaleItemId = $this->inventory->decrementForOrderItem($order, $product->id, $variant?->id, $quantity, $price);

                    $order->items()->create([
                        'product_id' => $product->id,
                        'variant_id' => $variant?->id,
                        'flash_sale_item_id' => $flashSaleItemId,
                        'product_name' => $product->name,
                        'sku' => $variant?->sku ?? $product->sku,
                        'price' => $price,
                        'quantity' => $quantity,
                        'subtotal' => $price * $quantity,
                        'product_snapshot' => [
                            'id' => $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'price' => $price,
                            'image' => $variant?->image ?? $product->images->first()?->path,
                            'variant_attributes' => $variant?->attributes,
                        ],
                    ]);
                }

                return $order;
            });
        } catch (InsufficientStockException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$order->order_number} created."]);

        return redirect()->route('admin.orders.show', $order);
    }

    public function show(Order $order): Response
    {
        $order->load(['user:id,name,email,phone', 'items.product:id,name,slug', 'items.variant:id,attributes', 'statusHistories']);

        // Viewing the order is what "seeing" it means for the unseen-orders
        // sidebar badge — do this before building the response so it
        // reflects `is_seen: true` immediately, not after a second visit.
        if (! $order->is_seen) {
            $order->update(['is_seen' => true, 'seen_at' => now()]);
        }

        return Inertia::render('admin/orders/show', [
            'order' => array_merge($order->toArray(), [
                'pathao_status_label' => $order->pathao_order_status
                    ? CourierOrderStatusMapper::label($order->pathao_order_status)
                    : null,
                'steadfast_status_label' => $order->steadfast_status
                    ? CourierOrderStatusMapper::label($order->steadfast_status)
                    : null,
            ]),
        ]);
    }

    /**
     * The status change, its history row and every financial side effect
     * Order::booted() runs for it (redeemed-points refund, referral
     * reversal, stock restore, coupon release) commit or roll back as one
     * transaction — a failing side effect can't leave the order REFUNDED
     * with the customer's points never returned. The status is re-checked
     * on the locked row, so two concurrent clicks can't both transition it
     * (and double the history row and customer email).
     */
    public function markRefunded(Order $order): RedirectResponse
    {
        $marked = DB::transaction(function () use ($order): bool {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder === null || $lockedOrder->status === OrderStatus::REFUNDED) {
                return false;
            }

            $old = $lockedOrder->status->value;
            $lockedOrder->update(['status' => OrderStatus::REFUNDED]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'status' => OrderStatus::REFUNDED->value,
                'notes' => "Marked as refunded by admin (was: {$old}).",
            ]);

            return true;
        }, 3);

        if (! $marked) {
            return back()->with('error', 'This order is already marked as refunded.');
        }

        return back()->with('success', 'Order marked as refunded.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::CANCELLED) {
            return back()->with('error', 'Only cancelled orders can be deleted.');
        }

        $order->delete();

        return redirect()->route('admin.orders.index')->with('success', 'Order moved to trash.');
    }

    public function trashed(Request $request): Response
    {
        $orders = Order::onlyTrashed()
            ->with('user:id,name,email')
            ->when($request->search, fn ($q) => $q->where('order_number', 'like', "%{$request->search}%"))
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/orders/trashed', [
            'orders' => $orders,
            'filters' => $request->only(['search']),
        ]);
    }

    public function restore(Order $order): RedirectResponse
    {
        $order->restore();

        return back()->with('success', 'Order restored.');
    }

    public function forceDelete(Order $order): RedirectResponse
    {
        $order->forceDelete();

        return redirect()->route('admin.orders.trashed')->with('success', 'Order permanently deleted.');
    }

    /**
     * Mirrors the condition the order-detail page already uses to hide the
     * Cancel Order card (resources/js/pages/admin/orders/show.tsx) — once an
     * order is delivered, refunded, or already cancelled, "cancelling" it
     * here would still run the same stock-restore/coupon-release/loyalty-
     * reversal side effects (Order::booted()) for a sale that was actually
     * fulfilled, or that's already in its own terminal state. A delivered
     * order that needs to come back belongs in the Return/Refund workflow,
     * not this action.
     *
     * @var list<OrderStatus>
     */
    private const UNCANCELLABLE_STATUSES = [OrderStatus::CANCELLED, OrderStatus::REFUNDED, OrderStatus::DELIVERED];

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Same single-transaction, locked re-check shape as markRefunded().
        $blockingStatus = DB::transaction(function () use ($request, $order): ?OrderStatus {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder === null) {
                return $order->status;
            }

            if (in_array($lockedOrder->status, self::UNCANCELLABLE_STATUSES, true)) {
                return $lockedOrder->status;
            }

            $old = $lockedOrder->status->value;
            $lockedOrder->update([
                'status' => OrderStatus::CANCELLED,
                'cancellation_reason' => $request->reason,
            ]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'status' => OrderStatus::CANCELLED->value,
                'notes' => $request->reason ?? "Order cancelled by admin (was: {$old}).",
            ]);

            return null;
        }, 3);

        if ($blockingStatus !== null) {
            return back()->with('error', "Orders that are already {$blockingStatus->value} cannot be cancelled.");
        }

        return back()->with('success', 'Order has been cancelled.');
    }

    /**
     * The only part of a placed order an admin can edit — everything else
     * (items, pricing, status) stays frozen once the order exists. Merges
     * into the existing `shipping_address` JSON instead of replacing it, so
     * an email key set at checkout (used by Order::booted() for the
     * customer notification) survives an address-only edit — the same
     * reason the recipient `name` is left out of the request's validated
     * data: it's never in the merge, so it can never change here either.
     */
    public function updateShippingAddress(UpdateOrderShippingAddressRequest $request, Order $order): RedirectResponse
    {
        $order->update([
            'shipping_address' => array_merge($order->shipping_address, $request->validated()),
        ]);

        return back()->with('success', 'Shipping address updated.');
    }

    public function sendToPathao(Order $order): RedirectResponse
    {
        if ($order->pathao_consignment_id !== null) {
            return back()->with('error', 'This order has already been sent to Pathao.');
        }

        try {
            $result = $this->pathao->createOrder($order);
        } catch (RuntimeException|ConnectionException $e) {
            return back()->with('error', 'Pathao: '.$e->getMessage());
        }

        $order->update([
            'pathao_consignment_id' => $result['consignment_id'],
            'pathao_delivery_fee' => $result['delivery_fee'],
            'pathao_sent_at' => now(),
            'tracking_number' => $order->tracking_number ?? $result['consignment_id'],
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $order->status->value,
            'notes' => "Sent to Pathao courier. Consignment ID: {$result['consignment_id']}.",
        ]);

        CourierOrderStatusMapper::sync($order, 'pathao', $result['order_status']);

        return back()->with('success', 'Order sent to Pathao for pickup.');
    }

    public function refreshPathaoStatus(Order $order): RedirectResponse
    {
        if ($order->pathao_consignment_id === null) {
            return back()->with('error', 'This order has not been sent to Pathao yet.');
        }

        try {
            $info = $this->pathao->getOrderInfo($order->pathao_consignment_id);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Pathao: '.$e->getMessage());
        }

        $raw = $info['order_status_slug'] ?? $info['order_status'];
        CourierOrderStatusMapper::sync($order, 'pathao', $raw);

        return back()->with('success', 'Pathao status: '.CourierOrderStatusMapper::label($raw).'.');
    }

    public function sendToSteadfast(Order $order): RedirectResponse
    {
        if ($order->steadfast_consignment_id !== null) {
            return back()->with('error', 'This order has already been sent to Steadfast.');
        }

        try {
            $result = $this->steadfast->createOrder($order);
        } catch (RuntimeException|ConnectionException $e) {
            return back()->with('error', 'Steadfast: '.$e->getMessage());
        }

        $order->update([
            'steadfast_consignment_id' => $result['consignment_id'],
            'steadfast_tracking_code' => $result['tracking_code'],
            'steadfast_sent_at' => now(),
            'tracking_number' => $order->tracking_number ?? ($result['tracking_code'] ?? $result['consignment_id']),
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $order->status->value,
            'notes' => "Sent to Steadfast courier. Consignment ID: {$result['consignment_id']}.",
        ]);

        CourierOrderStatusMapper::sync($order, 'steadfast', $result['status']);

        return back()->with('success', 'Order sent to Steadfast for pickup.');
    }

    public function refreshSteadfastStatus(Order $order): RedirectResponse
    {
        if ($order->steadfast_consignment_id === null) {
            return back()->with('error', 'This order has not been sent to Steadfast yet.');
        }

        try {
            $info = $this->steadfast->getOrderInfo($order->steadfast_consignment_id);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Steadfast: '.$e->getMessage());
        }

        CourierOrderStatusMapper::sync($order, 'steadfast', $info['status']);

        return back()->with('success', 'Steadfast status: '.CourierOrderStatusMapper::label($info['status']).'.');
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
