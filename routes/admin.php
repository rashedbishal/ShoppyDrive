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
use App\Http\Controllers\Admin\CourierVerificationReadinessController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\IncompleteOrderController;
use App\Http\Controllers\Admin\LandingPageController as AdminLandingPageController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderDeliveryVerificationController;
use App\Http\Controllers\Admin\PopularKeywordController;
use App\Http\Controllers\Admin\ProductBundleController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionalBannerController;
use App\Http\Controllers\Admin\ReceivedSmsController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SecuritySettingsController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SmsForwarderSettingsController;
use App\Http\Controllers\Admin\SmsPatternController;
use App\Http\Controllers\Admin\SubCategoryController;
use App\Support\AdminSections;
use Illuminate\Routing\PendingResourceRegistration;
use Illuminate\Support\Facades\Route;

Route::middleware(['redirect-customer-from-admin'])->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:admin-login')
        ->name('admin.login.submit');
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

/**
 * Applies the standard view/create/update/delete `admin.can` middleware to
 * a resource route's index+show, create+store, edit+update and destroy
 * actions. Safe to call even when some of those actions were excluded via
 * ->only()/->except() — middlewareFor() on an action that was never
 * registered is a no-op.
 */
$guardResource = function (PendingResourceRegistration $resource, string $section): PendingResourceRegistration {
    return $resource
        ->middlewareFor(['index', 'show'], AdminSections::middleware($section, 'view'))
        ->middlewareFor(['create', 'store'], AdminSections::middleware($section, 'create'))
        ->middlewareFor(['edit', 'update'], AdminSections::middleware($section, 'update'))
        ->middlewareFor('destroy', AdminSections::middleware($section, 'delete'));
};

/**
 * `admin.can:<section>,<ability>` (EnsureAdminPermission) gates every
 * section-specific route below per the catalog in App\Support\AdminSections
 * — the mother admin always passes; everyone else needs that section+
 * ability granted via Admin > Admins. Deliberately NOT gated this way:
 * `/dashboard` (every admin's landing page, no CRUD of its own),
 * `admins.*` (stays mother-only/self-only — see AdminController and
 * AdminSections's docblock for why it isn't delegable),
 * `settings.update` (one endpoint shared by six sidebar items — gated by
 * field group inside SettingController itself), `settings.security.update`
 * (already hardcoded mother-only in SecuritySettingsController), and
 * `content-images.store` (a shared rich-text-editor upload helper used
 * across several sections, not worth gating on its own).
 */
Route::middleware(['redirect-customer-from-admin', 'admin'])->prefix('admin')->name('admin.')->group(function () use ($guardResource): void {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::patch('products/cry-baby', [ProductController::class, 'updateCryBabyForAll'])->name('products.cry-baby.update-all')->middleware(AdminSections::middleware('products', 'update'));
    $guardResource(Route::resource('products', ProductController::class), 'products');

    Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index')->middleware(AdminSections::middleware('attributes', 'view'));
    Route::post('attribute-values/reorder', [AttributeValueController::class, 'reorder'])->name('attribute-values.reorder')->middleware(AdminSections::middleware('attributes', 'update'));
    $guardResource(Route::resource('attribute-values', AttributeValueController::class)->only(['store', 'update', 'destroy']), 'attributes');

    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder')->middleware(AdminSections::middleware('categories', 'update'));
    Route::patch('categories/bulk-status', [CategoryController::class, 'updateStatusMany'])->name('categories.updateStatusMany')->middleware(AdminSections::middleware('categories', 'update'));
    Route::delete('categories/bulk-delete', [CategoryController::class, 'destroyMany'])->name('categories.destroyMany')->middleware(AdminSections::middleware('categories', 'delete'));
    $guardResource(Route::resource('categories', CategoryController::class), 'categories');

    Route::patch('sub-categories/bulk-status', [SubCategoryController::class, 'updateStatusMany'])->name('sub-categories.updateStatusMany')->middleware(AdminSections::middleware('sub_categories', 'update'));
    Route::delete('sub-categories/bulk-delete', [SubCategoryController::class, 'destroyMany'])->name('sub-categories.destroyMany')->middleware(AdminSections::middleware('sub_categories', 'delete'));
    $guardResource(Route::resource('sub-categories', SubCategoryController::class), 'sub_categories');

    Route::patch('brands/bulk-status', [BrandController::class, 'updateStatusMany'])->name('brands.updateStatusMany')->middleware(AdminSections::middleware('brands', 'update'));
    Route::delete('brands/bulk-delete', [BrandController::class, 'destroyMany'])->name('brands.destroyMany')->middleware(AdminSections::middleware('brands', 'delete'));
    $guardResource(Route::resource('brands', BrandController::class), 'brands');

    Route::patch('coupons/bulk-status', [CouponController::class, 'updateStatusMany'])->name('coupons.updateStatusMany')->middleware(AdminSections::middleware('coupons', 'update'));
    Route::delete('coupons/bulk-delete', [CouponController::class, 'destroyMany'])->name('coupons.destroyMany')->middleware(AdminSections::middleware('coupons', 'delete'));
    $guardResource(Route::resource('coupons', CouponController::class), 'coupons');

    Route::patch('flash-sales/bulk-status', [FlashSaleController::class, 'updateStatusMany'])->name('flash-sales.updateStatusMany')->middleware(AdminSections::middleware('flash_sales', 'update'));
    Route::delete('flash-sales/bulk-delete', [FlashSaleController::class, 'destroyMany'])->name('flash-sales.destroyMany')->middleware(AdminSections::middleware('flash_sales', 'delete'));
    $guardResource(Route::resource('flash-sales', FlashSaleController::class)->parameters(['flash-sales' => 'flashSale']), 'flash_sales');

    Route::get('product-bundles/products/search', [ProductBundleController::class, 'searchProducts'])->name('product-bundles.products.search')->middleware(AdminSections::middleware('bundles', 'view'));
    Route::patch('product-bundles/bulk-status', [ProductBundleController::class, 'updateStatusMany'])->name('product-bundles.updateStatusMany')->middleware(AdminSections::middleware('bundles', 'update'));
    Route::delete('product-bundles/bulk-delete', [ProductBundleController::class, 'destroyMany'])->name('product-bundles.destroyMany')->middleware(AdminSections::middleware('bundles', 'delete'));
    $guardResource(Route::resource('product-bundles', ProductBundleController::class)->parameters(['product-bundles' => 'productBundle']), 'bundles');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index')->middleware(AdminSections::middleware('orders', 'view'));
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store')->middleware(AdminSections::middleware('orders', 'create'));
    Route::get('orders/trashed', [OrderController::class, 'trashed'])->name('orders.trashed')->middleware(AdminSections::middleware('trashed_orders', 'view'));
    Route::get('orders/customers/search', [OrderController::class, 'searchCustomers'])->name('orders.customers.search')->middleware(AdminSections::middleware('orders', 'view'));

    // Incomplete orders (abandoned checkouts) — registered above the
    // orders/{order} wildcard so these literal segments aren't swallowed
    // by order route-model binding.
    Route::get('orders/incomplete', [IncompleteOrderController::class, 'index'])->name('orders.incomplete.index')->middleware(AdminSections::middleware('incomplete_orders', 'view'));
    Route::delete('orders/incomplete/all', [IncompleteOrderController::class, 'destroyAll'])->name('orders.incomplete.destroyAll')->middleware(AdminSections::middleware('incomplete_orders', 'delete'));
    Route::get('orders/incomplete/products/search', [IncompleteOrderController::class, 'searchProducts'])->name('orders.incomplete.products.search')->middleware(AdminSections::middleware('incomplete_orders', 'view'));
    Route::get('orders/incomplete/{incompleteOrder}/convert', [IncompleteOrderController::class, 'convert'])->name('orders.incomplete.convert')->middleware(AdminSections::middleware('incomplete_orders', 'create'));
    Route::post('orders/incomplete/{incompleteOrder}/convert', [IncompleteOrderController::class, 'placeOrder'])->name('orders.incomplete.placeOrder')->middleware(AdminSections::middleware('incomplete_orders', 'create'));
    Route::delete('orders/incomplete/{incompleteOrder}', [IncompleteOrderController::class, 'destroy'])->name('orders.incomplete.destroy')->middleware(AdminSections::middleware('incomplete_orders', 'delete'));

    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show')->middleware(AdminSections::middleware('orders', 'view'));
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy')->middleware(AdminSections::middleware('orders', 'delete'));
    Route::patch('orders/{order}/restore', [OrderController::class, 'restore'])->name('orders.restore')->withTrashed()->middleware(AdminSections::middleware('trashed_orders', 'update'));
    // DB-001: permanent deletion is mother-admin only (no section permission
    // is enough) and takes two password-verified requests.
    Route::post('orders/{order}/permanent-delete/confirm', [OrderController::class, 'confirmPermanentDelete'])->name('orders.permanentDelete.confirm')->withTrashed()->middleware(['admin.mother', 'throttle:mother-actions'])->block(10, 10);
    Route::delete('orders/{order}/force', [OrderController::class, 'forceDelete'])->name('orders.forceDelete')->withTrashed()->middleware(['admin.mother', 'throttle:mother-actions'])->block(10, 10);
    Route::patch('orders/{order}/shipping-address', [OrderController::class, 'updateShippingAddress'])->name('orders.updateShippingAddress')->middleware(AdminSections::middleware('orders', 'update'));
    Route::patch('orders/{order}/refund', [OrderController::class, 'markRefunded'])->name('orders.markRefunded')->middleware(AdminSections::middleware('orders', 'update'));
    Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel')->middleware(AdminSections::middleware('orders', 'update'));
    Route::post('orders/{order}/pathao/send', [OrderController::class, 'sendToPathao'])->name('orders.pathao.send')->middleware(AdminSections::middleware('orders', 'update'));
    Route::post('orders/{order}/pathao/refresh', [OrderController::class, 'refreshPathaoStatus'])->name('orders.pathao.refresh')->middleware(AdminSections::middleware('orders', 'update'));
    Route::post('orders/{order}/steadfast/send', [OrderController::class, 'sendToSteadfast'])->name('orders.steadfast.send')->middleware(AdminSections::middleware('orders', 'update'));
    Route::post('orders/{order}/steadfast/refresh', [OrderController::class, 'refreshSteadfastStatus'])->name('orders.steadfast.refresh')->middleware(AdminSections::middleware('orders', 'update'));
    Route::post('orders/{order}/courier-submissions/{courierSubmission}/record-consignment', [OrderController::class, 'recordCourierConsignment'])->name('orders.courier.recordConsignment')->middleware(AdminSections::middleware('orders', 'update'))->scopeBindings();
    Route::post('orders/{order}/courier-submissions/{courierSubmission}/confirm-returned-consignment', [OrderController::class, 'confirmReturnedCourierConsignment'])->name('orders.courier.confirmReturnedConsignment')->middleware(AdminSections::middleware('orders', 'update'))->scopeBindings();
    Route::post('orders/{order}/courier-submissions/{courierSubmission}/confirm-not-created', [OrderController::class, 'confirmCourierNotCreated'])->name('orders.courier.confirmNotCreated')->middleware(AdminSections::middleware('orders', 'update'))->scopeBindings();
    // PAY-RETURN-001: mother-admin-only courier verification (no section permission is enough).
    Route::post('orders/{order}/delivery-verifications', [OrderDeliveryVerificationController::class, 'store'])->name('orders.deliveryVerifications.store')->middleware(['admin.mother', 'throttle:30,15'])->block(10, 10);
    Route::get('courier-readiness', [CourierVerificationReadinessController::class, 'index'])->name('courierReadiness.index')->middleware('admin.mother');
    Route::post('courier-readiness', [CourierVerificationReadinessController::class, 'store'])->name('courierReadiness.store')->middleware(['admin.mother', 'throttle:30,15'])->block(10, 10);

    Route::get('returns', [ReturnController::class, 'index'])->name('returns.index')->middleware(AdminSections::middleware('returns', 'view'));
    Route::patch('returns/{returnRequest}/approve', [ReturnController::class, 'approve'])->name('returns.approve')->middleware(AdminSections::middleware('returns', 'update'));
    Route::patch('returns/{returnRequest}/reject', [ReturnController::class, 'reject'])->name('returns.reject')->middleware(AdminSections::middleware('returns', 'update'));
    Route::patch('returns/{returnRequest}/refund', [ReturnController::class, 'markRefunded'])->name('returns.markRefunded')->middleware(AdminSections::middleware('returns', 'update'));

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index')->middleware(AdminSections::middleware('customers', 'view'));
    Route::get('customers/{user}', [CustomerController::class, 'show'])->name('customers.show')->middleware(AdminSections::middleware('customers', 'view'));
    Route::delete('customers/{user}', [CustomerController::class, 'destroy'])->name('customers.destroy')->middleware(AdminSections::middleware('customers', 'delete'));

    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index')->middleware(AdminSections::middleware('reviews', 'view'));
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve')->middleware(AdminSections::middleware('reviews', 'update'));
    Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject')->middleware(AdminSections::middleware('reviews', 'update'));
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy')->middleware(AdminSections::middleware('reviews', 'delete'));

    Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index')->middleware(AdminSections::middleware('contact_messages', 'view'));
    Route::delete('contact-messages/clear', [ContactMessageController::class, 'clear'])->name('contact-messages.clear')->middleware(AdminSections::middleware('contact_messages', 'delete'));
    Route::delete('contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('contact-messages.destroy')->middleware(AdminSections::middleware('contact_messages', 'delete'));

    // settings.update is one endpoint shared by six sidebar items (Settings,
    // Social Config, Courier Config, About Page, Contact Page, Referral
    // Program) — SettingController::update() checks permission per field
    // group itself instead of a single route-level gate.
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit')->middleware(AdminSections::middleware('settings', 'view'));
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('settings/social', [SettingController::class, 'socialConfig'])->name('settings.social')->middleware(AdminSections::middleware('social_config', 'view'));
    Route::get('settings/courier', [SettingController::class, 'courierConfig'])->name('settings.courier')->middleware(AdminSections::middleware('courier_config', 'view'));
    Route::get('settings/about-page', [SettingController::class, 'aboutPageConfig'])->name('settings.about-page')->middleware(AdminSections::middleware('about_page', 'view'));
    Route::get('settings/contact-page', [SettingController::class, 'contactPageConfig'])->name('settings.contact-page')->middleware(AdminSections::middleware('contact_page', 'view'));
    Route::get('settings/referral', [SettingController::class, 'referralConfig'])->name('settings.referral')->middleware(AdminSections::middleware('referral_program', 'view'));
    Route::get('settings/pathao/stores', [SettingController::class, 'pathaoStores'])->name('settings.pathao.stores')->middleware(AdminSections::middleware('courier_config', 'view'));
    Route::get('settings/steadfast/balance', [SettingController::class, 'steadfastBalance'])->name('settings.steadfast.balance')->middleware(AdminSections::middleware('courier_config', 'view'));

    // Security's update stays hardcoded mother-only inside the controller
    // (unchanged) regardless of this matrix; only `view` is gated here.
    Route::get('settings/security', [SecuritySettingsController::class, 'edit'])->name('settings.security.edit')->middleware(AdminSections::middleware('security', 'view'));
    Route::put('settings/security', [SecuritySettingsController::class, 'update'])->name('settings.security.update');
    Route::get('settings/sms-forwarder', [SmsForwarderSettingsController::class, 'index'])->name('settings.sms-forwarder.index')->middleware(AdminSections::middleware('sms_forwarder', 'view'));
    Route::post('settings/sms-forwarder/regenerate', [SmsForwarderSettingsController::class, 'regenerate'])->name('settings.sms-forwarder.regenerate')->middleware(AdminSections::middleware('sms_forwarder', 'update'));

    // Admins management is deliberately NOT part of the permission matrix —
    // see AdminSections's docblock. AdminController enforces its own
    // mother-only / self-only rules exactly as before this feature.
    Route::resource('admins', AdminController::class)->except(['show']);

    // Clearing the log is deliberately NOT part of the permission matrix
    // (AuditTrailController hardcodes mother-only) — only viewing is gated.
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index')->middleware(AdminSections::middleware('audit_trail', 'view'));
    Route::delete('audit-trail', [AuditTrailController::class, 'destroy'])->name('audit-trail.destroy')->middleware(['admin.mother', 'throttle:mother-actions']);

    Route::patch('popular-keywords/bulk-status', [PopularKeywordController::class, 'updateStatusMany'])->name('popular-keywords.updateStatusMany')->middleware(AdminSections::middleware('search_keywords', 'update'));
    Route::delete('popular-keywords/bulk-delete', [PopularKeywordController::class, 'destroyMany'])->name('popular-keywords.destroyMany')->middleware(AdminSections::middleware('search_keywords', 'delete'));
    $guardResource(Route::resource('popular-keywords', PopularKeywordController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['popular-keywords' => 'popularKeyword']), 'search_keywords');

    Route::patch('landing-pages/bulk-status', [AdminLandingPageController::class, 'updateStatusMany'])->name('landing-pages.updateStatusMany')->middleware(AdminSections::middleware('landing_pages', 'update'));
    Route::delete('landing-pages/bulk-delete', [AdminLandingPageController::class, 'destroyMany'])->name('landing-pages.destroyMany')->middleware(AdminSections::middleware('landing_pages', 'delete'));
    $guardResource(Route::resource('landing-pages', AdminLandingPageController::class)->except(['show'])->parameters(['landing-pages' => 'landingPage']), 'landing_pages');

    Route::get('received-sms', [ReceivedSmsController::class, 'index'])->name('received-sms.index')->middleware(AdminSections::middleware('received_sms', 'view'));
    Route::delete('received-sms/{receivedSms}', [ReceivedSmsController::class, 'destroy'])->name('received-sms.destroy')->middleware(AdminSections::middleware('received_sms', 'delete'));
    Route::get('sms/poll', [ReceivedSmsController::class, 'poll'])->name('sms.poll')->middleware(AdminSections::middleware('received_sms', 'view'));

    Route::get('sms-patterns', [SmsPatternController::class, 'index'])->name('sms-patterns.index')->middleware(AdminSections::middleware('sms_patterns', 'view'));
    Route::post('sms-patterns', [SmsPatternController::class, 'store'])->name('sms-patterns.store')->middleware(AdminSections::middleware('sms_patterns', 'create'));
    Route::patch('sms-patterns/bulk-status', [SmsPatternController::class, 'updateStatusMany'])->name('sms-patterns.updateStatusMany')->middleware(AdminSections::middleware('sms_patterns', 'update'));
    Route::delete('sms-patterns/bulk-delete', [SmsPatternController::class, 'destroyMany'])->name('sms-patterns.destroyMany')->middleware(AdminSections::middleware('sms_patterns', 'delete'));
    Route::patch('sms-patterns/{smsPattern}', [SmsPatternController::class, 'update'])->name('sms-patterns.update')->middleware(AdminSections::middleware('sms_patterns', 'update'));
    Route::delete('sms-patterns/{smsPattern}', [SmsPatternController::class, 'destroy'])->name('sms-patterns.destroy')->middleware(AdminSections::middleware('sms_patterns', 'delete'));

    Route::get('blocked-ips', [BlockedIpController::class, 'index'])->name('blocked-ips.index')->middleware(AdminSections::middleware('blocked_ips', 'view'));
    Route::post('blocked-ips', [BlockedIpController::class, 'store'])->name('blocked-ips.store')->middleware(AdminSections::middleware('blocked_ips', 'create'));
    Route::delete('blocked-ips/{blockedIp}', [BlockedIpController::class, 'destroy'])->name('blocked-ips.destroy')->middleware(AdminSections::middleware('blocked_ips', 'delete'));

    // Homepage Management
    Route::get('homepage', [HomepageController::class, 'index'])->name('homepage.index')->middleware(AdminSections::middleware('homepage', 'view'));
    Route::post('homepage/reorder', [HomepageController::class, 'reorder'])->name('homepage.reorder')->middleware(AdminSections::middleware('homepage', 'update'));
    Route::get('homepage/products/search', [HomepageController::class, 'searchProducts'])->name('homepage.products.search')->middleware(AdminSections::middleware('homepage', 'view'));
    Route::get('homepage/brands/search', [HomepageController::class, 'searchBrands'])->name('homepage.brands.search')->middleware(AdminSections::middleware('homepage', 'view'));
    Route::get('homepage/{setting}/edit', [HomepageController::class, 'edit'])->name('homepage.edit')->middleware(AdminSections::middleware('homepage', 'update'));
    Route::put('homepage/{setting}', [HomepageController::class, 'update'])->name('homepage.update')->middleware(AdminSections::middleware('homepage', 'update'));

    // Newsletter
    Route::get('newsletter', [AdminNewsletterController::class, 'index'])->name('newsletter.index')->middleware(AdminSections::middleware('newsletter', 'view'));
    Route::delete('newsletter/{subscriber}', [AdminNewsletterController::class, 'destroy'])->name('newsletter.destroy')->middleware(AdminSections::middleware('newsletter', 'delete'));
    Route::get('newsletter/compose', [AdminNewsletterController::class, 'compose'])->name('newsletter.compose')->middleware(AdminSections::middleware('newsletter', 'create'));
    Route::post('newsletter/send', [AdminNewsletterController::class, 'send'])->name('newsletter.send')->middleware(AdminSections::middleware('newsletter', 'create'));

    Route::patch('blogs/bulk-status', [BlogController::class, 'updateStatusMany'])->name('blogs.updateStatusMany')->middleware(AdminSections::middleware('blog', 'update'));
    Route::delete('blogs/bulk-delete', [BlogController::class, 'destroyMany'])->name('blogs.destroyMany')->middleware(AdminSections::middleware('blog', 'delete'));
    $guardResource(Route::resource('blogs', BlogController::class)->except(['show']), 'blog');
    // Shared rich-text-editor image upload helper used by several sections
    // (Blog, About Page, Contact Page, Landing Pages, ...) — deliberately
    // left to just the `admin` guard rather than any one section.
    Route::post('content-images', [ContentImageController::class, 'store'])->name('content-images.store');

    $guardResource(Route::resource('hero-slides', HeroSlideController::class)->except(['show']), 'homepage');
    Route::get('promotional-banners/products/search', [PromotionalBannerController::class, 'searchProducts'])->name('promotional-banners.products.search')->middleware(AdminSections::middleware('homepage', 'view'));
    $guardResource(Route::resource('promotional-banners', PromotionalBannerController::class)->except(['show']), 'homepage');
});
