<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/order/track', fn () => inertia('order/TrackOrder'))->name('order.track');
Route::get('/order/{order}/confirmation', fn () => inertia('order/OrderConfirmation'))->name('order.confirmation');

Route::get('/wishlist', fn () => inertia('account/Wishlist'))->name('wishlist.index');
Route::get('/compare', fn () => inertia('compare/Compare'))->name('compare');
