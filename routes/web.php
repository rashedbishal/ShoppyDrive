<?php

use App\Console\Commands\GenerateSitemapCommand;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\NewsletterController;
use App\Models\Page;
use App\Support\PageSeo;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Chrome DevTools probes this on every page load when open; without a route
// it 404s through the app's themed error page instead of failing silently.
Route::get('/.well-known/appspecific/com.chrome.devtools.json', fn () => response()->json([]));

Route::get('/about', [AboutController::class, 'show'])->name('about');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact-message')->name('contact.store');
// FE-010: crash reports from the browser's error boundary.
Route::post('/client-errors', ClientErrorController::class)->middleware('throttle:client-errors')->name('client-errors.store');

Route::get('/page/{page:slug}', function (Page $page) {
    abort_unless($page->is_published, 404);

    PageSeo::shareForPage($page);

    return inertia('static/Page', ['page' => $page]);
})->name('page.show');

Route::get('/privacy-policy', function () {
    $page = Page::where('slug', 'privacy-policy')->where('is_published', true)->firstOrFail();

    PageSeo::shareForPage($page);

    return inertia('static/Page', ['page' => $page]);
})->name('privacy-policy');

Route::get('/terms', function () {
    $page = Page::where('slug', 'terms')->where('is_published', true)->firstOrFail();

    PageSeo::shareForPage($page);

    return inertia('static/Page', ['page' => $page]);
})->name('terms');

Route::get('/faq', function () {
    $page = Page::where('slug', 'faq')->where('is_published', true)->firstOrFail();

    PageSeo::shareForPage($page);

    return inertia('static/FAQ', ['page' => $page]);
})->name('faq');

// OPS-001: rebuilt nightly by sitemap:generate; see GenerateSitemapCommand::path().
Route::get('/sitemap.xml', function () {
    abort_unless(is_file(GenerateSitemapCommand::path()), 404);

    return response()->file(GenerateSitemapCommand::path(), ['Content-Type' => 'application/xml']);
})->name('sitemap');

// BIZ-034: return, refund and shipping policies.
foreach (['return-policy', 'refund-policy', 'shipping-policy'] as $policySlug) {
    Route::get('/'.$policySlug, function () use ($policySlug) {
        $page = Page::where('slug', $policySlug)->where('is_published', true)->firstOrFail();

        PageSeo::shareForPage($page);

        return inertia('static/Page', ['page' => $page]);
    })->name($policySlug);
}

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{blog:slug}', [BlogController::class, 'show'])->name('blog.show');

// Newsletter
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:newsletter-signup')->name('newsletter.subscribe');
// FSA-14: unsubscribing requires the subscriber's own token. The GET here
// only ever reads (never changes subscription state) and shows a
// confirmation page; the actual unsubscribe happens on the POST below, so
// an email client/scanner that auto-opens the link can't unsubscribe anyone.
Route::get('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribeConfirm'])->middleware('throttle:newsletter-check')->name('newsletter.unsubscribe.confirm');
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:newsletter-signup')->name('newsletter.unsubscribe');
// FSA-14: the generic link in newsletter emails (FSA-13's shared BCC body
// can't carry a per-recipient token) — a visitor submits an email here and,
// if it's an active subscriber, a separate personalized email containing
// their own token link is queued. The response is identical either way.
Route::get('/newsletter/manage', [NewsletterController::class, 'manage'])->middleware('throttle:newsletter-check')->name('newsletter.manage');
Route::post('/newsletter/manage', [NewsletterController::class, 'manageSubmit'])->middleware('throttle:newsletter-manage')->name('newsletter.manage.send');

// Landing pages (public, guest-accessible)
Route::get('/lp/{slug}', [LandingPageController::class, 'show'])->name('lp.show');
Route::post('/lp/{slug}/order', [LandingPageController::class, 'placeOrder'])->middleware('throttle:place-order')->name('lp.order');
Route::get('/lp/{slug}/payment/{token}', [LandingPageController::class, 'payment'])->name('lp.payment');
Route::post('/lp/{slug}/payment/{token}', [LandingPageController::class, 'submitPayment'])->middleware('throttle:payment-verify')->name('lp.payment.submit');
Route::get('/lp/confirmation/{guestToken}', [LandingPageController::class, 'confirmation'])->name('lp.confirmation');
Route::get('/lp/invoice/{guestToken}', [LandingPageController::class, 'downloadInvoice'])->middleware('throttle:invoice-download')->name('lp.invoice');

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/catalog.php';
require __DIR__.'/commerce.php';
require __DIR__.'/account.php';
