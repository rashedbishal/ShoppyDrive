<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AttributeValueController;
use App\Http\Controllers\Admin\AuditTrailController;
use App\Http\Controllers\Admin\BlockedIpController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentImageController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\IncompleteOrderController;
use App\Http\Controllers\Admin\LandingPageController as AdminLandingPageController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PopularKeywordController;
use App\Http\Controllers\Admin\ProductBundleController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionalBannerController;
use App\Http\Controllers\Admin\ReceivedSmsController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SmsForwarderSettingsController;
use App\Http\Controllers\Admin\SmsPatternController;
use App\Http\Controllers\Admin\SubCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['redirect-customer-from-admin'])->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:admin-login')
        ->name('admin.login.submit');
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

Route::middleware(['redirect-customer-from-admin', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::patch('products/cry-baby', [ProductController::class, 'updateCryBabyForAll'])->name('products.cry-baby.update-all');
    Route::resource('products', ProductController::class);
    Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
    Route::post('attribute-values/reorder', [AttributeValueController::class, 'reorder'])->name('attribute-values.reorder');
    Route::resource('attribute-values', AttributeValueController::class)->only(['store', 'update', 'destroy']);
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::patch('categories/bulk-status', [CategoryController::class, 'updateStatusMany'])->name('categories.updateStatusMany');
    Route::delete('categories/bulk-delete', [CategoryController::class, 'destroyMany'])->name('categories.destroyMany');
    Route::resource('categories', CategoryController::class);
    Route::patch('sub-categories/bulk-status', [SubCategoryController::class, 'updateStatusMany'])->name('sub-categories.updateStatusMany');
    Route::delete('sub-categories/bulk-delete', [SubCategoryController::class, 'destroyMany'])->name('sub-categories.destroyMany');
    Route::resource('sub-categories', SubCategoryController::class);
    Route::patch('brands/bulk-status', [BrandController::class, 'updateStatusMany'])->name('brands.updateStatusMany');
    Route::delete('brands/bulk-delete', [BrandController::class, 'destroyMany'])->name('brands.destroyMany');
    Route::resource('brands', BrandController::class);
    Route::patch('coupons/bulk-status', [CouponController::class, 'updateStatusMany'])->name('coupons.updateStatusMany');
    Route::delete('coupons/bulk-delete', [CouponController::class, 'destroyMany'])->name('coupons.destroyMany');
    Route::resource('coupons', CouponController::class);
    Route::patch('flash-sales/bulk-status', [FlashSaleController::class, 'updateStatusMany'])->name('flash-sales.updateStatusMany');
    Route::delete('flash-sales/bulk-delete', [FlashSaleController::class, 'destroyMany'])->name('flash-sales.destroyMany');
    Route::resource('flash-sales', FlashSaleController::class)->parameters(['flash-sales' => 'flashSale']);
    Route::get('product-bundles/products/search', [ProductBundleController::class, 'searchProducts'])->name('product-bundles.products.search');
    Route::patch('product-bundles/bulk-status', [ProductBundleController::class, 'updateStatusMany'])->name('product-bundles.updateStatusMany');
    Route::delete('product-bundles/bulk-delete', [ProductBundleController::class, 'destroyMany'])->name('product-bundles.destroyMany');
    Route::resource('product-bundles', ProductBundleController::class)->parameters(['product-bundles' => 'productBundle']);

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/trashed', [OrderController::class, 'trashed'])->name('orders.trashed');
    Route::get('orders/customers/search', [OrderController::class, 'searchCustomers'])->name('orders.customers.search');

    // Incomplete orders (abandoned checkouts) — registered above the
    // orders/{order} wildcard so these literal segments aren't swallowed
    // by order route-model binding.
    Route::get('orders/incomplete', [IncompleteOrderController::class, 'index'])->name('orders.incomplete.index');
    Route::delete('orders/incomplete/all', [IncompleteOrderController::class, 'destroyAll'])->name('orders.incomplete.destroyAll');
    Route::get('orders/incomplete/products/search', [IncompleteOrderController::class, 'searchProducts'])->name('orders.incomplete.products.search');
    Route::get('orders/incomplete/{incompleteOrder}/convert', [IncompleteOrderController::class, 'convert'])->name('orders.incomplete.convert');
    Route::post('orders/incomplete/{incompleteOrder}/convert', [IncompleteOrderController::class, 'placeOrder'])->name('orders.incomplete.placeOrder');
    Route::delete('orders/incomplete/{incompleteOrder}', [IncompleteOrderController::class, 'destroy'])->name('orders.incomplete.destroy');

    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::patch('orders/{order}/restore', [OrderController::class, 'restore'])->name('orders.restore')->withTrashed();
    Route::delete('orders/{order}/force', [OrderController::class, 'forceDelete'])->name('orders.forceDelete')->withTrashed();
    Route::patch('orders/{order}/refund', [OrderController::class, 'markRefunded'])->name('orders.markRefunded');
    Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/pathao/send', [OrderController::class, 'sendToPathao'])->name('orders.pathao.send');
    Route::post('orders/{order}/pathao/refresh', [OrderController::class, 'refreshPathaoStatus'])->name('orders.pathao.refresh');
    Route::post('orders/{order}/steadfast/send', [OrderController::class, 'sendToSteadfast'])->name('orders.steadfast.send');
    Route::post('orders/{order}/steadfast/refresh', [OrderController::class, 'refreshSteadfastStatus'])->name('orders.steadfast.refresh');

    Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::patch('returns/{returnRequest}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
    Route::patch('returns/{returnRequest}/reject', [ReturnController::class, 'reject'])->name('returns.reject');
    Route::patch('returns/{returnRequest}/refund', [ReturnController::class, 'markRefunded'])->name('returns.markRefunded');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{user}', [CustomerController::class, 'show'])->name('customers.show');
    Route::delete('customers/{user}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::delete('contact-messages/clear', [ContactMessageController::class, 'clear'])->name('contact-messages.clear');
    Route::delete('contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('contact-messages.destroy');

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('settings/social', [SettingController::class, 'socialConfig'])->name('settings.social');
    Route::get('settings/courier', [SettingController::class, 'courierConfig'])->name('settings.courier');
    Route::get('settings/about-page', [SettingController::class, 'aboutPageConfig'])->name('settings.about-page');
    Route::get('settings/contact-page', [SettingController::class, 'contactPageConfig'])->name('settings.contact-page');
    Route::get('settings/referral', [SettingController::class, 'referralConfig'])->name('settings.referral');
    Route::get('settings/pathao/stores', [SettingController::class, 'pathaoStores'])->name('settings.pathao.stores');
    Route::get('settings/steadfast/balance', [SettingController::class, 'steadfastBalance'])->name('settings.steadfast.balance');

    Route::get('settings/sms-forwarder', [SmsForwarderSettingsController::class, 'index'])->name('settings.sms-forwarder.index');
    Route::post('settings/sms-forwarder/regenerate', [SmsForwarderSettingsController::class, 'regenerate'])->name('settings.sms-forwarder.regenerate');

    Route::resource('admins', AdminController::class)->except(['show']);
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');
    Route::delete('audit-trail', [AuditTrailController::class, 'destroy'])->name('audit-trail.destroy');
    Route::patch('popular-keywords/bulk-status', [PopularKeywordController::class, 'updateStatusMany'])->name('popular-keywords.updateStatusMany');
    Route::delete('popular-keywords/bulk-delete', [PopularKeywordController::class, 'destroyMany'])->name('popular-keywords.destroyMany');
    Route::resource('popular-keywords', PopularKeywordController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['popular-keywords' => 'popularKeyword']);
    Route::patch('landing-pages/bulk-status', [AdminLandingPageController::class, 'updateStatusMany'])->name('landing-pages.updateStatusMany');
    Route::delete('landing-pages/bulk-delete', [AdminLandingPageController::class, 'destroyMany'])->name('landing-pages.destroyMany');
    Route::resource('landing-pages', AdminLandingPageController::class)->except(['show'])->parameters(['landing-pages' => 'landingPage']);

    Route::get('received-sms', [ReceivedSmsController::class, 'index'])->name('received-sms.index');
    Route::delete('received-sms/{receivedSms}', [ReceivedSmsController::class, 'destroy'])->name('received-sms.destroy');
    Route::get('sms/poll', [ReceivedSmsController::class, 'poll'])->name('sms.poll');

    Route::get('sms-patterns', [SmsPatternController::class, 'index'])->name('sms-patterns.index');
    Route::post('sms-patterns', [SmsPatternController::class, 'store'])->name('sms-patterns.store');
    Route::patch('sms-patterns/bulk-status', [SmsPatternController::class, 'updateStatusMany'])->name('sms-patterns.updateStatusMany');
    Route::delete('sms-patterns/bulk-delete', [SmsPatternController::class, 'destroyMany'])->name('sms-patterns.destroyMany');
    Route::patch('sms-patterns/{smsPattern}', [SmsPatternController::class, 'update'])->name('sms-patterns.update');
    Route::delete('sms-patterns/{smsPattern}', [SmsPatternController::class, 'destroy'])->name('sms-patterns.destroy');

    Route::get('blocked-ips', [BlockedIpController::class, 'index'])->name('blocked-ips.index');
    Route::post('blocked-ips', [BlockedIpController::class, 'store'])->name('blocked-ips.store');
    Route::delete('blocked-ips/{blockedIp}', [BlockedIpController::class, 'destroy'])->name('blocked-ips.destroy');

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

    Route::patch('blogs/bulk-status', [BlogController::class, 'updateStatusMany'])->name('blogs.updateStatusMany');
    Route::delete('blogs/bulk-delete', [BlogController::class, 'destroyMany'])->name('blogs.destroyMany');
    Route::resource('blogs', BlogController::class)->except(['show']);
    Route::post('content-images', [ContentImageController::class, 'store'])->name('content-images.store');

    Route::resource('hero-slides', HeroSlideController::class)->except(['show']);
    Route::get('promotional-banners/products/search', [PromotionalBannerController::class, 'searchProducts'])->name('promotional-banners.products.search');
    Route::resource('promotional-banners', PromotionalBannerController::class)->except(['show']);
});
