<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\CourierOrderStatusMapper;
use App\Services\PathaoCourierService;
use App\Services\SteadfastCourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private readonly PathaoCourierService $pathao,
        private readonly SteadfastCourierService $steadfast,
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
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['user:id,name,email,phone', 'items.product:id,name,slug', 'statusHistories']);

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

    public function markRefunded(Order $order): RedirectResponse
    {
        if ($order->status === OrderStatus::REFUNDED) {
            return back()->with('error', 'This order is already marked as refunded.');
        }

        $old = $order->status->value;
        $order->update(['status' => OrderStatus::REFUNDED]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => OrderStatus::REFUNDED->value,
            'notes' => "Marked as refunded by admin (was: {$old}).",
        ]);

        return back()->with('success', 'Order marked as refunded.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $old = $order->status->value;
        $order->update([
            'status' => OrderStatus::CANCELLED,
            'cancellation_reason' => $request->reason,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
            'notes' => $request->reason ?? "Order cancelled by admin (was: {$old}).",
        ]);

        return back()->with('success', 'Order has been cancelled.');
    }

    public function sendToPathao(Order $order): RedirectResponse
    {
        if ($order->pathao_consignment_id !== null) {
            return back()->with('error', 'This order has already been sent to Pathao.');
        }

        try {
            $result = $this->pathao->createOrder($order);
        } catch (RuntimeException $e) {
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
        } catch (RuntimeException $e) {
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
}
