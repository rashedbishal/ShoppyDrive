<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\CouldNotRecordRefundException;
use App\Exceptions\CourierVerificationRefusedException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PermanentDeletionRefusedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOrderRequest;
use App\Http\Requests\Admin\OrderPricingPreviewRequest;
use App\Http\Requests\Admin\UpdateOrderShippingAddressRequest;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CourierEvidenceChain;
use App\Services\CourierOrderStatusMapper;
use App\Services\CourierSubmissionService;
use App\Services\InventoryService;
use App\Services\ManualOrderPricing;
use App\Services\OrderDeletionEligibility;
use App\Services\OrderLostParcelService;
use App\Services\OrderPermanentDeletionService;
use App\Services\RefundCalculator;
use App\Services\RefundService;
use App\Support\AdminListUrl;
use App\Support\OrderNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CourierSubmissionService $courierSubmissions,
        private readonly OrderDeletionEligibility $deletionEligibility,
        private readonly ManualOrderPricing $manualPricing,
        private readonly OrderPermanentDeletionService $permanentDeletion,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $orders = Order::with('user:id,name,email')
            ->when($request->search, fn ($q) => $q->where('order_number', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->payment_status, fn ($q) => $q->where('payment_status', $request->payment_status))
            ->latest()->paginate(20)->withQueryString();

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($orders, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

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
     * The calculated pricing for the create-order form to start from, so the
     * browser never recomputes prices or shipping itself. Purely advisory:
     * CreateOrderRequest recalculates everything on submit.
     */
    public function pricingPreview(OrderPricingPreviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->manualPricing->calculate($data['items'], $data['state'] ?? null, $data['city'] ?? null));
    }

    /**
     * Creates a real order directly from the admin panel — for a customer
     * who called/messaged in rather than checking out on the site. Reuses
     * the same InventoryService stock/flash-sale enforcement every other
     * order-creation path uses, so a phone order can't oversell any more
     * than a web one can. Deliberately skips coupon/referral/loyalty
     * logic (none of that applies to a manually-entered order) — same
     * scope as IncompleteOrderController::placeOrder().
     *
     * MED-01: staff keep full pricing flexibility, but CreateOrderRequest
     * requires a reason for any deviation from the server-calculated price,
     * shipping or discount, and each one goes to the Audit Trail.
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
                    'order_number' => OrderNumberGenerator::generate(),
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

                $this->inventory->lockStockInOrder($items);

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
                            'image' => $variant?->image ?? $product->primaryImagePath(),
                            'variant_attributes' => $variant?->attributes,
                        ],
                    ]);
                }

                $this->manualPricing->logOverrides($order, $items, $request->calculatedPricing(), [
                    'shipping_cost' => $shippingCost,
                    'discount' => $discount,
                    'shipping_reason' => $request->validated('shipping_override_reason'),
                    'discount_reason' => $request->validated('discount_override_reason'),
                ]);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$order->order_number} created."]);

        return redirect()->route('admin.orders.show', $order);
    }

    public function show(Order $order, RefundCalculator $refunds, CourierEvidenceChain $courierEvidence): Response
    {
        $order->load(['user:id,name,email,phone', 'items.product:id,name,slug', 'items.variant:id,attributes', 'statusHistories']);

        // Viewing the order is what "seeing" it means for the unseen-orders
        // sidebar badge — do this before building the response so it
        // reflects `is_seen: true` immediately, not after a second visit.
        if (! $order->is_seen) {
            $order->update(['is_seen' => true, 'seen_at' => now()]);
        }

        // So an attempt whose process died shows (and can be resolved) as
        // unknown rather than "sending" forever.
        $this->courierSubmissions->expireStaleSubmissions($order->id);

        $latestSubmission = $order->courierSubmissions()->with('resolvedBy:id,name')->latest('id')->first();

        return Inertia::render('admin/orders/show', [
            'courierSubmission' => $latestSubmission === null ? null : [
                'id' => $latestSubmission->id,
                'courier' => $latestSubmission->courier,
                'courier_label' => $latestSubmission->courierLabel(),
                'state' => $latestSubmission->state->value,
                'reference' => $latestSubmission->reference,
                'failure_kind' => $latestSubmission->failure_kind,
                'error_message' => $latestSubmission->error_message,
                'returned_consignment_id' => $latestSubmission->returned_consignment_id,
                'returned_tracking_code' => $latestSubmission->returned_tracking_code,
                'returned_at' => $latestSubmission->returned_at?->toIso8601String(),
                'claimed_at' => $latestSubmission->claimed_at?->toIso8601String(),
                'finished_at' => $latestSubmission->finished_at?->toIso8601String(),
                'resolution' => $latestSubmission->resolution,
                'resolved_by' => $latestSubmission->resolvedBy?->name,
                'resolved_at' => $latestSubmission->resolved_at?->toIso8601String(),
                'can_record_consignment' => $latestSubmission->courier === 'steadfast',
            ],
            'order' => array_merge($order->toArray(), [
                'pathao_status_label' => $order->pathao_order_status
                    ? CourierOrderStatusMapper::label($order->pathao_order_status)
                    : null,
                'steadfast_status_label' => $order->steadfast_status
                    ? CourierOrderStatusMapper::label($order->steadfast_status)
                    : null,
                // FSA-11: flags an active order whose latest courier report
                // didn't map to anything recognized (e.g. a lost parcel) —
                // see possibleLostParcel()'s docblock.
                'needsCourierInvestigation' => $this->possibleLostParcel($order),
            ]),
            // PAY-RETURN-001
            'refundSummary' => $refunds->summary($order),
            'courierEvidence' => $courierEvidence->displaySummary($order),
            'canVerifyDelivery' => (bool) Auth::guard('admin')->user()?->is_mother,
            'canConfirmLostParcel' => (bool) Auth::guard('admin')->user()?->is_mother && $order->lost_confirmed_at === null,
        ]);
    }

    /**
     * FSA-11: no mapped courier status for "lost" exists in either
     * PATHAO_MAP or STEADFAST_MAP — confirmed against both couriers'
     * actual reported vocabulary, not just their public docs — so this
     * can't map a specific transition. `CourierOrderStatusMapper::sync()`
     * already fails safe for any unrecognized report (records it as
     * evidence/history, never changes status, never restocks, never
     * refunds), so the only remaining gap is visibility: this surfaces
     * exactly that same "unrecognized but active" condition to the admin
     * viewing the order, so it can be investigated and resolved manually
     * through the existing refund/return workflows — never automatically.
     */
    private function possibleLostParcel(Order $order): bool
    {
        if (CourierOrderStatusMapper::isTerminal($order->status)) {
            return false;
        }

        if ($order->pathao_order_status !== null && CourierOrderStatusMapper::isUnrecognized('pathao', $order->pathao_order_status)) {
            return true;
        }

        return $order->steadfast_status !== null && CourierOrderStatusMapper::isUnrecognized('steadfast', $order->steadfast_status);
    }

    /**
     * FSA-11: records the mother admin's confirmed-lost determination.
     * Never changes order status, never refunds, never restores stock
     * itself — see OrderLostParcelService's docblock. The admin still
     * performs the actual cancel (COD) or "Mark as Refunded" (prepaid)
     * action separately, exactly as before; this only makes that
     * subsequent action skip the auto-restock it would otherwise do.
     */
    public function confirmLostParcel(Request $request, Order $order, OrderLostParcelService $lostParcels): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $lostParcels->confirm(Auth::guard('admin')->user(), $order, $validated['password'], $validated['note']);
        } catch (CourierVerificationRefusedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', 'Parcel confirmed lost. Stock will not be automatically restored when this order is cancelled or refunded.');
    }

    /**
     * PAY-RETURN-001: whole-order refund. The amount must equal exactly what
     * is still refundable; that, the unresolved-courier guard, payment
     * collection, courier verification and the shared refund ledger are all
     * enforced under the order lock by RefundService, in one transaction
     * with the status change and every Order::booted() side effect.
     */
    public function markRefunded(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        $validated = $request->validate([
            'refund_amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'refund_method' => ['required', 'string', 'max:60'],
            // BIZ-012: only consulted for shipped/delivered orders.
            'goods_returned' => ['sometimes', 'boolean'],
        ]);

        try {
            $refunds->recordOrderRefund(Auth::guard('admin')->user(), $order, $validated['refund_amount'], $validated['refund_method'], $request->boolean('goods_returned'));
        } catch (CouldNotRecordRefundException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order marked as refunded.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::CANCELLED) {
            return back()->with('error', 'Only cancelled orders can be deleted.');
        }

        $order->delete();

        return AdminListUrl::redirect('admin.orders.index')->with('success', 'Order moved to trash.');
    }

    public function trashed(Request $request): Response|RedirectResponse
    {
        $orders = Order::onlyTrashed()
            ->with('user:id,name,email')
            ->when($request->search, fn ($q) => $q->where('order_number', 'like', "%{$request->search}%"))
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($orders, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        // DB-001: only the mother admin sees permanent deletion at all, with
        // the reasons an order can't be deleted and any pending first step.
        $admin = Auth::guard('admin')->user();
        $isMother = (bool) $admin?->is_mother;

        if ($isMother) {
            $orders->getCollection()->each(function (Order $order): void {
                $order->setAttribute('deletion_blockers', $this->deletionEligibility->check($order->id));
            });
        }

        return Inertia::render('admin/orders/trashed', [
            'orders' => $orders,
            'filters' => $request->only(['search']),
            'canPermanentlyDelete' => $isMother,
            'pendingPermanentDeletion' => $isMother ? $this->permanentDeletion->pendingConfirmation($request, $admin) : null,
        ]);
    }

    public function restore(Order $order): RedirectResponse
    {
        $order->restore();

        return back()->with('success', 'Order restored.');
    }

    /**
     * DB-001 step 1 of permanent deletion (mother admin only — enforced by
     * the route middleware and again in the service).
     */
    public function confirmPermanentDelete(Request $request, Order $order): RedirectResponse
    {
        try {
            $this->permanentDeletion->confirm($request, Auth::guard('admin')->user(), $order, (string) $request->input('password', ''));
        } catch (PermanentDeletionRefusedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', 'Password confirmed. Within '.(OrderPermanentDeletionService::MARKER_TTL_SECONDS / 60).' minutes, enter your password again and the order number to permanently delete it.');
    }

    /**
     * DB-001 step 2: password again plus the typed order number, against a
     * valid step-1 confirmation for this order and this admin.
     */
    public function forceDelete(Request $request, Order $order): RedirectResponse
    {
        $orderNumber = $order->order_number;

        try {
            $this->permanentDeletion->delete(
                $request,
                Auth::guard('admin')->user(),
                $order,
                (string) $request->input('password', ''),
                (string) $request->input('order_number', ''),
            );
        } catch (PermanentDeletionRefusedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return AdminListUrl::redirect('admin.orders.trashed')->with('success', "Order {$orderNumber} was permanently deleted.");
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

    public const SHIPPED_CANCEL_MESSAGE = 'This order has shipped and is with the courier, so it can\'t be cancelled here. Cancel it or request a return in the courier\'s portal; the order is cancelled and its stock restored automatically when the courier reports the cancellation or return.';

    /**
     * BIZ-007: Steadfast's confirmed status vocabulary
     * (CourierOrderStatusMapper::STEADFAST_MAP) has no SHIPPED-equivalent
     * value, so a Steadfast order sits at PROCESSING for its entire transit
     * — the SHIPPED check below never catches it. A non-null consignment ID
     * is the courier-agnostic signal that the parcel is actually booked.
     */
    public const COURIER_CANCEL_MESSAGE = 'This order has a courier consignment and may already be with the courier, so it can\'t be cancelled here. Cancel it or request a return in the courier\'s portal; the order is cancelled and its stock restored automatically when the courier reports the cancellation or return.';

    /**
     * FSA-06: a paid order (e.g. mobile banking, created paid at checkout)
     * must not be cancelled directly here — "Mark as Refunded" is the
     * action that actually gives the money back, so admin-initiated
     * closure of a paid order goes through it instead of a plain cancel.
     * (BIZ-005: other paths — the customer self-cancel route, and the
     * courier's own return/cancel report — still cancel a paid order
     * without going through this check; RefundService::assertRefundable()
     * accepts a cancelled order for refund as long as it's still paid, so
     * those don't get stuck the way this admin action would.)
     */
    public const PAID_CANCEL_MESSAGE = 'This order has been paid for, so it can\'t be cancelled directly. Use "Mark as Refunded" instead to record the refund and close the order.';

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Same single-transaction, locked re-check shape as markRefunded().
        $error = DB::transaction(function () use ($request, $order): ?string {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();
            $status = $lockedOrder?->status ?? $order->status;

            if ($lockedOrder === null || in_array($lockedOrder->status, self::UNCANCELLABLE_STATUSES, true)) {
                return "Orders that are already {$status->value} cannot be cancelled.";
            }

            // FSA-11: only courier status sync ever sets SHIPPED, so the
            // parcel is with the courier. Cancelling here would restock goods
            // still in transit, and the courier's later "delivered" (ignored
            // once cancelled) would collect COD against a cancelled order.
            // The courier's own return/cancel report cancels the order —
            // and restocks it — through CourierOrderStatusMapper::sync().
            if ($lockedOrder->status === OrderStatus::SHIPPED) {
                return self::SHIPPED_CANCEL_MESSAGE;
            }

            // A mother admin's confirmed-lost-parcel finding (FSA-11's
            // approved lost-parcel policy, confirmLostParcel()) is itself
            // the authorized closure for an order a courier consignment
            // will never deliver — it must still be cancellable despite
            // the consignment ID below, and confirmLostParcel() already
            // requires existing courier evidence/verification first.
            if ($lockedOrder->pathao_consignment_id !== null || $lockedOrder->steadfast_consignment_id !== null) {
                if ($lockedOrder->lost_confirmed_at === null) {
                    return self::COURIER_CANCEL_MESSAGE;
                }
            }

            // FSA-06: a paid order must go through "Mark as Refunded"
            // (RefundService::recordOrderRefund()), not direct cancellation.
            if ($lockedOrder->payment_status === PaymentStatus::PAID) {
                return self::PAID_CANCEL_MESSAGE;
            }

            // COURIER-SEND-RACE-001: same guard as markRefunded().
            if ($this->courierSubmissions->hasUnresolvedSubmission($lockedOrder->id)) {
                return $this->courierSubmissions->unresolvedMessage('cancelled');
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

        if ($error !== null) {
            return back()->with('error', $error);
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
        // COURIER-SEND-RACE-002 (L3): while a courier booking is in flight or
        // unresolved, the courier may hold the address as it was when sent —
        // changing it here would make the order and the parcel disagree.
        $error = DB::transaction(function () use ($request, $order): ?string {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder === null) {
                return 'This order no longer exists.';
            }

            if ($this->courierSubmissions->hasUnresolvedSubmission($lockedOrder->id)) {
                return "The shipping address can't be changed while a courier submission is in progress or its outcome is unresolved. Resolve it in the Courier panel first.";
            }

            $lockedOrder->update([
                'shipping_address' => array_merge($lockedOrder->shipping_address, $request->validated()),
            ]);

            return null;
        }, 3);

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Shipping address updated.');
    }
}
