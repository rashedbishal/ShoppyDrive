<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\NewsletterController;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/about', [AboutController::class, 'show'])->name('about');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::get('/page/{page:slug}', function (Page $page) {
    abort_unless($page->is_published, 404);

    return inertia('static/Page', ['page' => $page]);
})->name('page.show');

Route::get('/privacy-policy', function () {
    $page = Page::where('slug', 'privacy-policy')->where('is_published', true)->firstOrFail();

    return inertia('static/Page', ['page' => $page]);
})->name('privacy-policy');

Route::get('/terms', function () {
    $page = Page::where('slug', 'terms')->where('is_published', true)->firstOrFail();

    return inertia('static/Page', ['page' => $page]);
})->name('terms');

Route::get('/faq', function () {
    $page = Page::where('slug', 'faq')->where('is_published', true)->firstOrFail();

    return inertia('static/FAQ', ['page' => $page]);
})->name('faq');

// Newsletter
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::get('/newsletter/status', [NewsletterController::class, 'status'])->name('newsletter.status');

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
