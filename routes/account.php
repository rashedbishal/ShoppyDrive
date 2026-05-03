<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('account')->group(function () {
    Route::get('/', fn () => inertia('account/Dashboard'))->name('account.dashboard');
    Route::get('/orders', fn () => inertia('account/Orders'))->name('account.orders');
    Route::get('/orders/{order}', fn () => inertia('account/OrderDetail'))->name('account.orders.show');
    Route::get('/wishlist', fn () => inertia('account/Wishlist'))->name('account.wishlist');
    Route::get('/addresses', fn () => inertia('account/Addresses'))->name('account.addresses');
    Route::get('/profile', fn () => inertia('account/Profile'))->name('account.profile');
    Route::get('/password', fn () => inertia('account/ChangePassword'))->name('account.password');

    // API-like routes for forms
    Route::post('/addresses', fn () => back())->name('account.addresses.store');
    Route::put('/addresses/{address}', fn () => back())->name('account.addresses.update');
    Route::delete('/addresses/{address}', fn () => back())->name('account.addresses.destroy');
    Route::patch('/addresses/{address}/default', fn () => back())->name('account.addresses.default');

    Route::post('/profile', fn () => back())->name('account.profile.update');
});
