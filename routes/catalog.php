<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductListingController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductListingController::class, 'index'])->name('products.index');
Route::get('/category/{slug}', [ProductListingController::class, 'category'])->name('category.show');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product.show');

Route::get('/search', [SearchController::class, 'index'])->name('search');

Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
Route::get('/brand/{brand:slug}', [BrandController::class, 'show'])->name('brand.show');

Route::get('/flash-sales', [FlashSaleController::class, 'index'])->name('flash-sales.index');
