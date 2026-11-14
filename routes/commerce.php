<?php

use App\Http\Controllers\AbandonedCartUnsubscribeController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\IncompleteOrderController;
use App\Http\Controllers\MetaTrackingController;
use App\Http\Controllers\MobilePaymentController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\WishlistController;
use App\Models\Coupon;
use App\Models\Order;
use App\Support\OrderConfirmationAccess;
use App\Support\OrderInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');

// OPS-006: unsubscribe link in AbandonedCartReminderMail — signed, so no
// login is required and the link can't be guessed or reused for another
// customer's account.
Route::get('/cart/reminders/unsubscribe/{user}', AbandonedCartUnsubscribeController::class)
    ->middleware('signed')
    ->name('cart.reminders.unsubscribe');

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
    Route::get('/cart/state', [CartController::class, 'state'])->middleware('throttle:cart-state')->name('cart.state');

    // The bKash/Nagad payment page's "Cancel payment" link. BIZ-026: moved
    // here when the retired ZiniPay gateway's routes were removed; same URL,
    // name and behaviour.
    Route::get('/payment/cancel/{token}', [MobilePaymentController::class, 'cancel'])->name('payment.cancel');
});
Route::get('/order/track', [OrderTrackingController::class, 'show'])->middleware('throttle:order-tracking')->name('order.track');
Route::get('/order/track/check', [OrderTrackingController::class, 'checkRequirements'])->middleware('throttle:order-tracking')->name('order.track.check');
// FSA-03: only the owner or the session that placed the order — see
// OrderConfirmationAccess. 404, not 403, so a guessed number reveals nothing.
Route::get('/order/{order:order_number}/confirmation', function (Request $request, Order $order) {
    abort_unless(OrderConfirmationAccess::allows($request, $order), 404);

    return inertia('order/OrderConfirmation', [
        'order' => OrderConfirmationAccess::payload($order),
        // A Buy Now order never touched the customer's cart, so this page
        // must not empty the cart they still have.
        'keepCart' => (bool) $order->is_buy_now,
    ]);
})->middleware('throttle:order-tracking')->name('order.confirmation');

// DISC-38: the invoice from the confirmation page, for exactly whoever may
// see that page (the logged-in owner, or the guest session that just placed
// or paid for the order). Generated on request; 404 for anyone else.
Route::get('/order/{order:order_number}/invoice', function (Request $request, Order $order) {
    abort_unless(OrderConfirmationAccess::allows($request, $order), 404);

    return OrderInvoice::download($order);
})->middleware('throttle:invoice-download')->name('order.invoice');

// DISC-38: the invoice from Track Order. OrderTrackingController hands out
// this 30-minute signed URL only after the visitor proved the order number
// plus its phone or email, so the signature is the access check.
Route::get('/order/track/{order:order_number}/invoice', fn (Order $order) => OrderInvoice::download($order))
    ->middleware(['signed', 'throttle:invoice-download'])
    ->name('order.track.invoice');

Route::post('/coupon/validate', function (Request $request) {
    $request->validate([
        'code' => ['required', 'string', 'max:50'],
        'subtotal' => ['required', 'numeric', 'min:0'],
        'phone' => ['nullable', 'string', 'max:20'],
        'items' => ['nullable', 'array'],
        'items.*.product_id' => ['required', 'integer'],
        'items.*.variant_id' => ['nullable', 'integer'],
        'items.*.subtotal' => ['required', 'numeric', 'min:0'],
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

    // BIZ-015 (owner decision, 2026-11-09): a guest now sees the same
    // specific per-user-limit reason a logged-in customer does ("already
    // used the maximum number of times"), traded against the
    // enumeration risk that specificity carries — someone who knows a
    // coupon code could probe arbitrary phone numbers against this
    // endpoint and learn which ones have used it. Owner chose clearer
    // guest messaging over closing that risk. Same check
    // CheckoutController::resolveCoupon() uses at actual checkout time,
    // so this preview can never promise a discount the real submission
    // would then reject.
    if ($coupon->appliesToEverything()) {
        $error = $coupon->eligibilityError((float) $request->subtotal, $userId, $guestPhone);

        return $error !== null
            ? response()->json(['error' => $error], 422)
            : response()->json(['coupon' => $coupon->publicPreview()]);
    }

    // A selected-items coupon is measured against what it covers. This is
    // only a preview: checkout recalculates from the real cart.
    $eligibleLines = $coupon->eligibleLines(array_map(fn (array $item): array => [
        'product_id' => (int) $item['product_id'],
        'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
        'subtotal' => (float) $item['subtotal'],
    ], $request->input('items', [])));

    if ($eligibleLines === []) {
        return response()->json(['error' => Coupon::NOT_APPLICABLE_MESSAGE], 422);
    }

    $error = $coupon->eligibilityError((float) array_sum(array_column($eligibleLines, 'subtotal')), $userId, $guestPhone);

    if ($error !== null) {
        return response()->json(['error' => $error], 422);
    }

    return response()->json(['coupon' => $coupon->publicPreview(
        array_map(fn (array $line): string => $line['product_id'].':'.($line['variant_id'] ?? ''), $eligibleLines),
    )]);
})->middleware('throttle:coupon-check')->name('coupon.validate');

Route::get('/wishlist', [WishlistController::class, 'entry'])->name('wishlist.index');

// Meta Conversions API: the storefront reports ViewContent, AddToCart,
// RemoveFromCart, AddToWishlist, InitiateCheckout and Search with the same
// event id it pushed to GTM (see MetaTrackingController). Open to guests.
Route::post('/tracking/meta', MetaTrackingController::class)
    ->middleware('throttle:meta-tracking')
    ->name('tracking.meta');

// AI assistant widget: open to guests (limited — see AiAssistantTools::lookupOrder())
// and logged-in customers alike. auth.session is deliberately not required.
Route::post('/assistant/chat', [AiAssistantController::class, 'chat'])
    ->middleware('throttle:ai-assistant')
    ->name('assistant.chat');
