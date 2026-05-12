<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionalBannerController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubCategoryController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('products', ProductController::class);
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

    // Homepage Management
    Route::get('homepage', [HomepageController::class, 'index'])->name('homepage.index');
    Route::get('homepage/products/search', [HomepageController::class, 'searchProducts'])->name('homepage.products.search');
    Route::get('homepage/brands/search', [HomepageController::class, 'searchBrands'])->name('homepage.brands.search');
    Route::get('homepage/{setting}/edit', [HomepageController::class, 'edit'])->name('homepage.edit');
    Route::put('homepage/{setting}', [HomepageController::class, 'update'])->name('homepage.update');

    Route::resource('hero-slides', HeroSlideController::class)->except(['show']);
    Route::get('promotional-banners/products/search', [PromotionalBannerController::class, 'searchProducts'])->name('promotional-banners.products.search');
    Route::resource('promotional-banners', PromotionalBannerController::class)->except(['show']);

    // TEMPORARY: ZiniPay diagnostics — remove after confirming payment works
    Route::get('zinipay-diagnostic', function () {
        $apiKey = config('zinipay.api_key', '');
        $baseUrl = config('zinipay.base_url', '');

        $masked = $apiKey === ''
            ? '❌ EMPTY — not loaded from .env'
            : '✅ '.substr($apiKey, 0, 6).'…'.substr($apiKey, -4).' ('.strlen($apiKey).' chars)';

        // Probe TCP connectivity to ZiniPay
        $canConnect = false;
        $connectError = '';
        try {
            $fp = @fsockopen('ssl://api.zinipay.com', 443, $errno, $errstr, 5);
            if ($fp) {
                $canConnect = true;
                fclose($fp);
            } else {
                $connectError = "errno={$errno} {$errstr}";
            }
        } catch (Throwable $e) {
            $connectError = $e->getMessage();
        }

        // Test actual API call with a minimal payload
        $apiTestResult = 'not attempted';
        if ($apiKey !== '') {
            try {
                $resp = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'zini-api-key' => $apiKey,
                ])->timeout(10)->post("{$baseUrl}/v1/payment/create", [
                    'amount' => 1,
                    'redirect_url' => url('/'),
                    'val_id' => 'DIAG-TEST-'.time(),
                ]);
                $apiTestResult = 'HTTP '.$resp->status().' → '.$resp->body();
            } catch (Throwable $e) {
                $apiTestResult = '❌ Exception: '.$e->getMessage();
            }
        }

        return response()->json([
            'api_key' => $masked,
            'base_url' => $baseUrl ?: '❌ EMPTY',
            'app_url' => config('app.url'),
            'app_env' => config('app.env'),
            'redirect_url_sample' => url('/payment/callback/ORD-TEST'),
            'webhook_url_sample' => url('/api/zinipay/webhook'),
            'tcp_to_zinipay' => $canConnect ? '✅ reachable' : '❌ blocked — '.$connectError,
            'api_call_result' => $apiTestResult,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    })->name('admin.zinipay.diagnostic');
});
