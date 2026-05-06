<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\WishlistController;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('account')->group(function () {
    Route::get('/', fn () => inertia('account/Dashboard'))->name('account.dashboard');
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
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('account.wishlist');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::get('/addresses', [AddressController::class, 'index'])->name('account.addresses');
    Route::post('/addresses', [AddressController::class, 'store'])->name('account.addresses.store');
    Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('account.addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('account.addresses.default');
    Route::get('/profile', fn () => inertia('account/Profile'))->name('account.profile');
    Route::get('/password', fn () => inertia('account/ChangePassword'))->name('account.password');

    Route::post('/profile', fn () => back())->name('account.profile.update');
});
