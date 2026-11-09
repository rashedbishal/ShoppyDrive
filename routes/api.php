<?php

use App\Http\Controllers\CspReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SmsTestController;
use App\Http\Controllers\SteadfastWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->middleware('throttle:search-suggestions');

// Steadfast courier webhook — delivery status and other parcel/account events
Route::post('/steadfast/webhook', [SteadfastWebhookController::class, 'handle'])->middleware('throttle:webhook')->name('steadfast.webhook');

// SEC-013: reports from the report-only Content Security Policy.
Route::post('/csp-report', CspReportController::class)->middleware('throttle:client-errors');

// SMS forwarder phone — authenticated by its bearer token (see SmsTestController)
Route::post('/sms/receive', [SmsTestController::class, 'receive'])->middleware('throttle:sms-forwarder');

// OPS-005: liveness-only heartbeat from the same phone, pinged every 1-2
// minutes regardless of SMS traffic (see SmsForwarderHeartbeat).
Route::post('/sms/heartbeat', [SmsTestController::class, 'heartbeat'])->middleware('throttle:sms-forwarder-heartbeat');
