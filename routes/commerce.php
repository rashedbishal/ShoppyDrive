<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\IncompleteOrderController;
use App\Http\Controllers\MobilePaymentController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PaymentController;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');

// Checkout and payment: a guest is let through only when "Allow Guest
// Checkout" is enabled in Admin > Settings (see EnsureCheckoutAccess); a
// logged-in-but-unverified customer is still sent to verify their email
// either way.
Route::middleware(['checkout.access'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // Mobile banking (Bkash / Nagad)
    Route::get('/payment/mobile/{token}', [MobilePaymentController::class, 'show'])->name('payment.mobile.show');
    Route::post('/payment/mobile/{token}', [MobilePaymentController::class, 'submit'])->name('payment.mobile.submit');

    // Fires from the checkout page once a valid phone number + items are
    // present, so admins can follow up on abandoned checkouts — see
    // Admin\IncompleteOrderController.
    Route::post('/checkout/track-incomplete', [IncompleteOrderController::class, 'store'])->name('checkout.track-incomplete');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/cart/sync', [CartController::class, 'sync'])->name('cart.sync');

    // ZiniPay payment return routes (kept for historical pending checkouts)
    Route::get('/payment/callback/{token}', [PaymentController::class, 'callback'])->name('payment.callback');
    Route::get('/payment/cancel/{token}', [PaymentController::class, 'cancel'])->name('payment.cancel');
});
Route::get('/order/track', [OrderTrackingController::class, 'show'])->name('order.track');
Route::get('/order/track/check', [OrderTrackingController::class, 'checkRequirements'])->name('order.track.check');
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

    $coupon = Coupon::where('code', strtoupper(trim($request->code)))->first();

    if (! $coupon) {
        return response()->json(['error' => 'Invalid coupon code.'], 422);
    }

    // Same check CheckoutController::resolveCoupon() uses at actual
    // checkout time, so this preview can never promise a discount the
    // real submission would then reject.
    $error = $coupon->eligibilityError((float) $request->subtotal, $request->user()?->id);

    if ($error !== null) {
        return response()->json(['error' => $error], 422);
    }

    return response()->json(['coupon' => $coupon]);
})->name('coupon.validate');

Route::get('/wishlist', fn () => inertia('account/Wishlist'))->name('wishlist.index');
Route::get('/compare', fn () => inertia('compare/Compare'))->name('compare');
