<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\IncompleteOrderController;
use App\Http\Controllers\MobilePaymentController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WishlistController;
use App\Models\Coupon;
use App\Models\Order;
use App\Support\OrderConfirmationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');

// Checkout and payment: a guest is let through only when "Allow Guest
// Checkout" is enabled in Admin > Settings (see EnsureCheckoutAccess); a
// logged-in-but-unverified customer is still sent to verify their email
// either way.
Route::middleware(['checkout.access', 'auth.session', 'account.active'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:place-order')->name('checkout.store');

    // Mobile banking (Bkash / Nagad)
    Route::get('/payment/mobile/{token}', [MobilePaymentController::class, 'show'])->name('payment.mobile.show');
    Route::post('/payment/mobile/{token}', [MobilePaymentController::class, 'submit'])->middleware('throttle:payment-verify')->name('payment.mobile.submit');

    // Fires from the checkout page once a valid phone number + items are
    // present, so admins can follow up on abandoned checkouts — see
    // Admin\IncompleteOrderController.
    Route::post('/checkout/track-incomplete', [IncompleteOrderController::class, 'store'])->middleware('throttle:checkout-tracking')->name('checkout.track-incomplete');
});

Route::middleware(['auth', 'auth.session', 'account.active'])->group(function () {
    Route::post('/cart/sync', [CartController::class, 'sync'])->name('cart.sync');

    // ZiniPay payment return routes (kept for historical pending checkouts)
    Route::get('/payment/callback/{token}', [PaymentController::class, 'callback'])->name('payment.callback');
    Route::get('/payment/cancel/{token}', [PaymentController::class, 'cancel'])->name('payment.cancel');
});
Route::get('/order/track', [OrderTrackingController::class, 'show'])->middleware('throttle:order-tracking')->name('order.track');
Route::get('/order/track/check', [OrderTrackingController::class, 'checkRequirements'])->middleware('throttle:order-tracking')->name('order.track.check');
// FSA-03: only the owner or the session that placed the order — see
// OrderConfirmationAccess. 404, not 403, so a guessed number reveals nothing.
Route::get('/order/{order:order_number}/confirmation', function (Request $request, Order $order) {
    abort_unless(OrderConfirmationAccess::allows($request, $order), 404);

    return inertia('order/OrderConfirmation', [
        'order' => OrderConfirmationAccess::payload($order),
    ]);
})->middleware('throttle:order-tracking')->name('order.confirmation');

Route::post('/coupon/validate', function (Request $request) {
    $request->validate([
        'code' => ['required', 'string'],
        'subtotal' => ['required', 'numeric', 'min:0'],
        'phone' => ['nullable', 'string'],
    ]);

    $coupon = Coupon::where('code', strtoupper(trim($request->code)))->first();

    if (! $coupon) {
        return response()->json(['error' => 'Invalid coupon code.'], 422);
    }

    $userId = $request->user()?->id;

    // Canonical format (same regex PlaceOrderRequest enforces at actual
    // checkout) gates whether a submitted phone is used for the guest
    // check below at all. A non-matching value is treated exactly like
    // an omitted one — not a 422 — since this is a best-effort preview
    // the frontend can call before the shipping form is finished (e.g.
    // the phone field only partially typed); the real submission is
    // what actually validates the field and has the final say either way.
    $guestPhone = ($userId === null && $request->filled('phone') && preg_match('/^01[3-9][0-9]{8}$/', (string) $request->phone) === 1)
        ? (string) $request->phone
        : null;

    // Guests are never told specifically that *this* phone number has
    // already redeemed the coupon — anyone who knows a coupon code could
    // otherwise probe arbitrary phone numbers against this endpoint and
    // learn which ones have used it. This generic rejection still
    // correctly says no (it doesn't weaken enforcement — the
    // authoritative check in CheckoutController::resolveCoupon() /
    // CouponService::consume() has the final say regardless of what this
    // preview says), it just never confirms *why*. Logged-in users are
    // unaffected: $guestPhone is only ever non-null for a guest request.
    if ($guestPhone !== null && Coupon::guestRedemptionCount($coupon->id, $guestPhone) >= $coupon->per_user_limit) {
        return response()->json(['error' => "This coupon isn't available for this order."], 422);
    }

    // Same check CheckoutController::resolveCoupon() uses at actual
    // checkout time, so this preview can never promise a discount the
    // real submission would then reject. Guest phone is deliberately not
    // passed here — the dedicated, non-revealing check above already
    // covers it; eligibilityError()'s own per-user message is reserved
    // for the logged-in branch, which doesn't carry the same enumeration
    // risk since a request can only ever check its own account's status.
    $error = $coupon->eligibilityError((float) $request->subtotal, $userId);

    if ($error !== null) {
        return response()->json(['error' => $error], 422);
    }

    return response()->json(['coupon' => $coupon]);
})->middleware('throttle:coupon-check')->name('coupon.validate');

Route::get('/wishlist', [WishlistController::class, 'entry'])->name('wishlist.index');
