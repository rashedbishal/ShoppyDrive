<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductListingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Models\Brand;
use App\Models\Product;
use App\Models\SlugRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductListingController::class, 'index'])->middleware('throttle:product-browsing')->name('products.index');
Route::get('/category/{slug}', [ProductListingController::class, 'category'])->middleware('throttle:product-browsing')->name('category.show');
// DISC-91: an unknown product slug may be an old one of a renamed product:
// 301 to its current URL if it's still on sale, otherwise 404 as before.
// Only this GET route; the review POST below keeps the plain 404.
Route::get('/product/{product:slug}', [ProductController::class, 'show'])
    ->missing(fn (Request $request) => SlugRedirect::redirectOrNotFound(Product::SLUG_REDIRECT_NAMESPACE, (string) $request->route('product'), $request))
    ->name('product.show');
Route::get('/products/recently-viewed', [ProductController::class, 'recentlyViewed'])->middleware('throttle:product-browsing')->name('products.recently-viewed');
Route::post('/product/{product:slug}/reviews', [ReviewController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:review', 'auth.session', 'account.active'])
    ->name('product.reviews.store');

Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:product-browsing')->name('search');

Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
// DISC-92: an unknown brand slug may be an old one of a renamed brand (see product.show above).
Route::get('/brand/{brand:slug}', [BrandController::class, 'show'])
    ->missing(fn (Request $request) => SlugRedirect::redirectOrNotFound(Brand::SLUG_REDIRECT_NAMESPACE, (string) $request->route('brand'), $request))
    ->name('brand.show');

Route::get('/flash-sales', [FlashSaleController::class, 'index'])->name('flash-sales.index');

Route::get('/bundle-deals', [BundleController::class, 'index'])->name('bundles.index');
