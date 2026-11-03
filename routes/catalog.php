<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductListingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductListingController::class, 'index'])->middleware('throttle:product-browsing')->name('products.index');
Route::get('/category/{slug}', [ProductListingController::class, 'category'])->middleware('throttle:product-browsing')->name('category.show');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product.show');
Route::post('/product/{product:slug}/reviews', [ReviewController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:review', 'auth.session', 'account.active'])
    ->name('product.reviews.store');

Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:product-browsing')->name('search');

Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
Route::get('/brand/{brand:slug}', [BrandController::class, 'show'])->name('brand.show');

Route::get('/flash-sales', [FlashSaleController::class, 'index'])->name('flash-sales.index');

Route::get('/bundle-deals', [BundleController::class, 'index'])->name('bundles.index');
