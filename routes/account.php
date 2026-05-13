<?php

use App\Enums\OrderStatus;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\WishlistController;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Wishlist;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('account')->group(function () {
    Route::get('/', function (Request $request) {
        $user = $request->user();

        $recentOrders = Order::where('user_id', $user->id)
            ->with(['items'])
            ->latest()
            ->limit(5)
            ->get();

        $totalOrders = Order::where('user_id', $user->id)->count();

        $wishlistCount = Wishlist::where('user_id', $user->id)->count();

        $addressCount = Address::where('user_id', $user->id)->count();

        $totalSpent = Order::where('user_id', $user->id)
            ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::REFUNDED->value])
            ->sum('total');

        return inertia('account/Dashboard', [
            'recentOrders' => $recentOrders,
            'totalOrders' => $totalOrders,
            'wishlistCount' => $wishlistCount,
            'addressCount' => $addressCount,
            'totalSpent' => (float) $totalSpent,
        ]);
    })->name('account.dashboard');
    Route::get('/orders', function (Request $request) {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product'])
            ->when($request->search, function ($query, $search) {
                $query->where('order_number', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('account/Orders', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status']),
        ]);
    })->name('account.orders');

    Route::get('/orders/{order:order_number}', function (Order $order) {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $order->load(['items.product', 'statusHistories']);

        return inertia('account/OrderDetail', [
            'order' => $order,
        ]);
    })->name('account.orders.show');

    Route::get('/orders/{order:order_number}/invoice', function (Order $order) {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $status = $order->status->value;
        $isCod = $order->payment_method->value === 'cod';

        if (in_array($status, ['cancelled', 'refunded'])) {
            abort(403, 'Invoice is not available for cancelled or refunded orders.');
        }

        if ($isCod && $status !== OrderStatus::DELIVERED->value) {
            abort(403, 'Invoice is only available after delivery for Cash on Delivery orders.');
        }

        $order->load(['items.product', 'user']);

        $pdf = Pdf::loadView('invoices.order', ['order' => $order]);

        return $pdf->download("invoice-{$order->order_number}.pdf");
    })->name('account.orders.invoice');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('account.wishlist');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/wishlist/clear', [WishlistController::class, 'clear'])->name('wishlist.clear');
    Route::post('/wishlist/add-all-to-cart', [WishlistController::class, 'addAllToCart'])->name('wishlist.add-all-to-cart');
    Route::get('/addresses', [AddressController::class, 'index'])->name('account.addresses');
    Route::post('/addresses', [AddressController::class, 'store'])->name('account.addresses.store');
    Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('account.addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('account.addresses.default');
    Route::get('/profile', fn () => inertia('account/Profile'))->name('account.profile');
    Route::get('/password', fn () => inertia('account/ChangePassword'))->name('account.password');

    Route::post('/profile', fn () => back())->name('account.profile.update');

    Route::patch('/orders/{order:order_number}/cancel', function (Order $order) {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status !== OrderStatus::PENDING) {
            return back()->with('error', 'Only pending orders can be cancelled.');
        }

        $order->update(['status' => OrderStatus::CANCELLED]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
            'notes' => 'Cancelled by customer.',
        ]);

        return back()->with('success', 'Your order has been cancelled.');
    })->name('account.orders.cancel');
});
