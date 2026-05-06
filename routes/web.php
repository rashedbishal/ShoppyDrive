<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/about', fn () => inertia('static/About'))->name('about');
Route::get('/contact', fn () => inertia('static/Contact'))->name('contact');
Route::post('/contact', fn () => back())->name('contact.store');
Route::get('/page/{page:slug}', fn () => inertia('static/Page'))->name('page.show');

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/catalog.php';
require __DIR__.'/commerce.php';
require __DIR__.'/account.php';
