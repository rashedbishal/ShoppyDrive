<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
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
            $updateData['payment_status'] = \App\Enums\PaymentStatus::PAID->value;
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
}
