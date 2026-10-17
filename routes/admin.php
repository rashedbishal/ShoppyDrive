<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\LandingPageController as AdminLandingPageController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PopularKeywordController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionalBannerController;
use App\Http\Controllers\Admin\ReceivedSmsController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SmsForwarderSettingsController;
use App\Http\Controllers\Admin\SmsPatternController;
use App\Http\Controllers\Admin\SubCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['redirect-customer-from-admin'])->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

Route::middleware(['redirect-customer-from-admin', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('products', ProductController::class);
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::resource('categories', CategoryController::class);
    Route::resource('sub-categories', SubCategoryController::class);
    Route::resource('brands', BrandController::class);
    Route::resource('coupons', CouponController::class);
    Route::resource('flash-sales', FlashSaleController::class)->parameters(['flash-sales' => 'flashSale']);

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{user}', [CustomerController::class, 'show'])->name('customers.show');

    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('settings/sms-forwarder', [SmsForwarderSettingsController::class, 'index'])->name('settings.sms-forwarder.index');
    Route::post('settings/sms-forwarder/regenerate', [SmsForwarderSettingsController::class, 'regenerate'])->name('settings.sms-forwarder.regenerate');

    Route::resource('admins', AdminController::class)->except(['show']);
    Route::resource('popular-keywords', PopularKeywordController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['popular-keywords' => 'popularKeyword']);
    Route::resource('landing-pages', AdminLandingPageController::class)->except(['show'])->parameters(['landing-pages' => 'landingPage']);

    Route::get('received-sms', [ReceivedSmsController::class, 'index'])->name('received-sms.index');
    Route::delete('received-sms/{receivedSms}', [ReceivedSmsController::class, 'destroy'])->name('received-sms.destroy');
    Route::get('sms/poll', [ReceivedSmsController::class, 'poll'])->name('sms.poll');

    Route::get('sms-patterns', [SmsPatternController::class, 'index'])->name('sms-patterns.index');
    Route::post('sms-patterns', [SmsPatternController::class, 'store'])->name('sms-patterns.store');
    Route::patch('sms-patterns/{smsPattern}', [SmsPatternController::class, 'update'])->name('sms-patterns.update');
    Route::delete('sms-patterns/{smsPattern}', [SmsPatternController::class, 'destroy'])->name('sms-patterns.destroy');

    // Homepage Management
    Route::get('homepage', [HomepageController::class, 'index'])->name('homepage.index');
    Route::post('homepage/reorder', [HomepageController::class, 'reorder'])->name('homepage.reorder');
    Route::get('homepage/products/search', [HomepageController::class, 'searchProducts'])->name('homepage.products.search');
    Route::get('homepage/brands/search', [HomepageController::class, 'searchBrands'])->name('homepage.brands.search');
    Route::get('homepage/{setting}/edit', [HomepageController::class, 'edit'])->name('homepage.edit');
    Route::put('homepage/{setting}', [HomepageController::class, 'update'])->name('homepage.update');

    // Newsletter
    Route::get('newsletter', [AdminNewsletterController::class, 'index'])->name('newsletter.index');
    Route::delete('newsletter/{subscriber}', [AdminNewsletterController::class, 'destroy'])->name('newsletter.destroy');
    Route::get('newsletter/compose', [AdminNewsletterController::class, 'compose'])->name('newsletter.compose');
    Route::post('newsletter/send', [AdminNewsletterController::class, 'send'])->name('newsletter.send');

    Route::resource('hero-slides', HeroSlideController::class)->except(['show']);
    Route::get('promotional-banners/products/search', [PromotionalBannerController::class, 'searchProducts'])->name('promotional-banners.products.search');
    Route::resource('promotional-banners', PromotionalBannerController::class)->except(['show']);
});
