<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ParentCategoryController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('parent-categories', ParentCategoryController::class)->parameters(['parent-categories' => 'parentCategory']);
    Route::resource('brands', BrandController::class);
    Route::resource('coupons', CouponController::class);
    Route::resource('banners', BannerController::class);
    Route::resource('flash-sales', FlashSaleController::class)->parameters(['flash-sales' => 'flashSale']);

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{user}', [CustomerController::class, 'show'])->name('customers.show');

    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
});
