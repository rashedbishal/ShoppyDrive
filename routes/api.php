<?php

use App\Http\Controllers\SearchController;
use App\Http\Controllers\SmsTestController;
use App\Http\Controllers\SteadfastWebhookController;
use App\Http\Controllers\ZiniPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->middleware('throttle:search-suggestions');

// ZiniPay webhook — accepts GET and POST (ZiniPay uses GET with query params in practice)
Route::match(['GET', 'POST'], '/zinipay/webhook', [ZiniPayWebhookController::class, 'handle'])->middleware('throttle:webhook')->name('zinipay.webhook');

// Steadfast courier webhook — delivery status and other parcel/account events
Route::post('/steadfast/webhook', [SteadfastWebhookController::class, 'handle'])->middleware('throttle:webhook')->name('steadfast.webhook');

// SMS forwarder phone — authenticated by its bearer token (see SmsTestController)
Route::post('/sms/receive', [SmsTestController::class, 'receive'])->middleware('throttle:sms-forwarder');
