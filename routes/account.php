<?php

use App\Enums\OrderStatus;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Controllers\WishlistController;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Wishlist;
use App\Services\CourierSubmissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'auth.session', 'account.active'])->prefix('account')->group(function () {
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
    // DB-001 (L5): a customer still sees their own orders after an admin
    // moved them to trash (only cancelled orders can be trashed). Read-only:
    // the return route below keeps the default binding, so trashed orders 404 there.
    Route::get('/orders', function (Request $request) {
        $orders = Order::withTrashed()->where('user_id', $request->user()->id)
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

        $order->load(['items.product', 'items.variant', 'statusHistories', 'latestReturnRequest']);

        return inertia('account/OrderDetail', [
            'order' => $order,
        ]);
    })->withTrashed()->name('account.orders.show');

    Route::post('/orders/{order:order_number}/return', [ReturnRequestController::class, 'store'])->middleware('throttle:account-action')->name('account.orders.return.store');

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

        $order->load(['items.product', 'items.variant', 'user']);

        $pdf = Pdf::loadView('invoices.order', ['order' => $order]);

        return $pdf->download("invoice-{$order->order_number}.pdf");
    })->middleware('throttle:invoice-download')->name('account.orders.invoice');
    Route::get('/referral', [ReferralController::class, 'index'])->name('account.referral');
    Route::post('/referral/daily-login', [ReferralController::class, 'claimDailyLogin'])->middleware('throttle:account-action')->name('account.referral.daily-login');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('account.wishlist');
    Route::get('/addresses', [AddressController::class, 'index'])->name('account.addresses');
    Route::post('/addresses', [AddressController::class, 'store'])->middleware('throttle:account-action')->name('account.addresses.store');
    Route::put('/addresses/{address}', [AddressController::class, 'update'])->middleware('throttle:account-action')->name('account.addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->middleware('throttle:account-action')->name('account.addresses.destroy');
    Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault'])->middleware('throttle:account-action')->name('account.addresses.default');
    Route::get('/profile', fn () => inertia('account/Profile'))->name('account.profile');
    Route::get('/password', fn () => inertia('account/ChangePassword'))->name('account.password');

    Route::post('/profile', [AccountProfileController::class, 'update'])->middleware('throttle:account-action')->name('account.profile.update');

    // FSA-15: a pending order can already have a courier consignment —
    // CourierSubmissionService::claim()/markSent() never touch `status`,
    // only the consignment columns, so the order stays `pending` until the
    // async courier-status sync later promotes it to `shipped`. This must
    // be locked and checked exactly like Admin\OrderController::cancel()
    // does (same Order-row-first locking convention documented on
    // CourierSubmissionService), since that's the only thing serializing
    // this against claim()/markSent()/markFailed()/markUnknown(), all of
    // which lock the same Order row before touching it.
    Route::patch('/orders/{order:order_number}/cancel', function (Order $order, CourierSubmissionService $courierSubmissions) {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $error = DB::transaction(function () use ($order, $courierSubmissions): ?string {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder === null || $lockedOrder->status !== OrderStatus::PENDING) {
                return 'Only pending orders can be cancelled.';
            }

            if ($lockedOrder->pathao_consignment_id !== null
                || $lockedOrder->steadfast_consignment_id !== null
                || $courierSubmissions->hasUnresolvedSubmission($lockedOrder->id)) {
                return 'This order is already being processed by our courier and can no longer be cancelled here. Please contact our support team for assistance.';
            }

            $lockedOrder->update(['status' => OrderStatus::CANCELLED]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'status' => OrderStatus::CANCELLED->value,
                'notes' => 'Cancelled by customer.',
            ]);

            return null;
        }, 3);

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Your order has been cancelled.');
    })->middleware('throttle:account-action')->name('account.orders.cancel');
});

// Wishlist mutations — only auth required, no email verification needed
Route::middleware(['auth', 'auth.session', 'account.active'])->prefix('account')->group(function () {
    Route::post('/wishlist/sync', [WishlistController::class, 'sync'])->middleware('throttle:account-action')->name('wishlist.sync');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->middleware('throttle:account-action')->name('wishlist.toggle');
    Route::post('/wishlist/clear', [WishlistController::class, 'clear'])->middleware('throttle:account-action')->name('wishlist.clear');
    Route::post('/wishlist/add-all-to-cart', [WishlistController::class, 'addAllToCart'])->middleware('throttle:account-action')->name('wishlist.add-all-to-cart');
});
