<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SmsTestController;
use App\Http\Controllers\ZiniPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/search/suggestions', [SearchController::class, 'suggestions']);

// ZiniPay webhook — accepts GET and POST (ZiniPay uses GET with query params in practice)
Route::match(['GET', 'POST'], '/zinipay/webhook', [ZiniPayWebhookController::class, 'handle'])->name('zinipay.webhook');

// Temporary SMS forwarder test endpoint — no auth, no middleware
Route::post('/sms/receive', [SmsTestController::class, 'receive']);

Route::prefix('v1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});
