<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MobilePaymentController;
use App\Http\Controllers\PaymentController;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::middleware(['auth'])->group(function () {
    Route::post('/cart/sync', [CartController::class, 'sync'])->name('cart.sync');

    Route::middleware(['verified'])->group(function () {
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    });

    // ZiniPay payment return routes (kept for historical pending checkouts)
    Route::get('/payment/callback/{token}', [PaymentController::class, 'callback'])->name('payment.callback');
    Route::get('/payment/cancel/{token}', [PaymentController::class, 'cancel'])->name('payment.cancel');

    // Mobile banking (Bkash / Nagad)
    Route::get('/payment/mobile/{token}', [MobilePaymentController::class, 'show'])->name('payment.mobile.show');
    Route::post('/payment/mobile/{token}', [MobilePaymentController::class, 'submit'])->name('payment.mobile.submit');
});
Route::get('/order/track', function (Request $request) {
    $order = null;
    $error = null;

    $orderNumber = $request->get('order_number');
    $email = $request->get('email');

    if ($orderNumber && $email) {
        $found = Order::where('order_number', $orderNumber)
            ->with(['items', 'statusHistories' => fn ($q) => $q->orderByDesc('created_at')])
            ->first();

        if (! $found) {
            $error = 'No order found with that order number. Please check and try again.';
        } elseif (strtolower($found->shipping_address['email'] ?? '') !== strtolower($email)) {
            $error = 'The email address does not match our records for this order.';
        } else {
            $order = $found;
        }
    }

    return inertia('order/TrackOrder', [
        'order' => $order,
        'error' => $error,
        'prefill_order_number' => $orderNumber ?? '',
    ]);
})->name('order.track');
Route::get('/order/{order:order_number}/confirmation', function (Order $order) {
    return inertia('order/OrderConfirmation', [
        'order' => $order->load('items.product'),
    ]);
})->name('order.confirmation');

Route::post('/coupon/validate', function (Request $request) {
    $request->validate([
        'code' => ['required', 'string'],
        'subtotal' => ['required', 'numeric', 'min:0'],
    ]);

    $coupon = Coupon::where('code', strtoupper(trim($request->code)))
        ->where('is_active', true)
        ->first();

    if (! $coupon) {
        return response()->json(['error' => 'Invalid coupon code.'], 422);
    }

    if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
        return response()->json(['error' => 'This coupon is not yet active.'], 422);
    }

    if ($coupon->expires_at && $coupon->expires_at->isPast()) {
        return response()->json(['error' => 'This coupon has expired.'], 422);
    }

    if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
        return response()->json(['error' => 'This coupon has reached its usage limit.'], 422);
    }

    if ($coupon->min_order_amount !== null && $request->subtotal < $coupon->min_order_amount) {
        return response()->json([
            'error' => 'Minimum order amount of ৳'.number_format($coupon->min_order_amount, 0).' required.',
        ], 422);
    }

    return response()->json(['coupon' => $coupon]);
})->name('coupon.validate');

Route::get('/wishlist', fn () => inertia('account/Wishlist'))->name('wishlist.index');
Route::get('/compare', fn () => inertia('compare/Compare'))->name('compare');
