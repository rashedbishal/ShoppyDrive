<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
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
        return Inertia::render('admin/orders/show', [
            'order' => $order->load(['user:id,name,email,phone', 'items.product:id,name,slug', 'statusHistories']),
            'statuses' => array_column(OrderStatus::cases(), 'value'),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate(['status' => ['required', 'in:'.implode(',', array_column(OrderStatus::cases(), 'value'))]]);

        $old = $order->status->value;
        $updateData = [
            'status' => $request->status,
            'delivered_at' => $request->status === OrderStatus::DELIVERED->value ? now() : $order->delivered_at,
        ];

        if ($request->status === OrderStatus::DELIVERED->value) {
            $updateData['payment_status'] = PaymentStatus::PAID->value;
        }

        $order->update($updateData);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $request->status,
            'notes' => $request->notes ?? "Status changed from {$old} to {$request->status}",
        ]);

        return back()->with('success', 'Order status updated.');
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
            'pathao_order_status' => $result['order_status'],
            'pathao_delivery_fee' => $result['delivery_fee'],
            'pathao_sent_at' => now(),
            'tracking_number' => $order->tracking_number ?? $result['consignment_id'],
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $order->status->value,
            'notes' => "Sent to Pathao courier. Consignment ID: {$result['consignment_id']}.",
        ]);

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

        $order->update(['pathao_order_status' => $info['order_status']]);

        return back()->with('success', "Pathao status: {$info['order_status']}.");
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
            'steadfast_status' => $result['status'],
            'steadfast_sent_at' => now(),
            'tracking_number' => $order->tracking_number ?? ($result['tracking_code'] ?? $result['consignment_id']),
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $order->status->value,
            'notes' => "Sent to Steadfast courier. Consignment ID: {$result['consignment_id']}.",
        ]);

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

        $order->update(['steadfast_status' => $info['status']]);

        return back()->with('success', "Steadfast status: {$info['status']}.");
    }
}
