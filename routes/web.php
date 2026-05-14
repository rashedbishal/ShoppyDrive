<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/about', fn () => inertia('static/About'))->name('about');
Route::get('/contact', fn () => inertia('static/Contact'))->name('contact');
Route::post('/contact', fn () => back())->name('contact.store');
Route::get('/page/{page:slug}', fn () => inertia('static/Page'))->name('page.show');

// Landing pages (public, guest-accessible)
Route::get('/lp/{slug}', [LandingPageController::class, 'show'])->name('lp.show');
Route::post('/lp/{slug}/order', [LandingPageController::class, 'placeOrder'])->name('lp.order');
Route::get('/lp/{slug}/payment/{token}', [LandingPageController::class, 'payment'])->name('lp.payment');
Route::post('/lp/{slug}/payment/{token}', [LandingPageController::class, 'submitPayment'])->name('lp.payment.submit');
Route::get('/lp/confirmation/{guestToken}', [LandingPageController::class, 'confirmation'])->name('lp.confirmation');
Route::get('/lp/invoice/{guestToken}', [LandingPageController::class, 'downloadInvoice'])->name('lp.invoice');

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/catalog.php';
require __DIR__.'/commerce.php';
require __DIR__.'/account.php';
