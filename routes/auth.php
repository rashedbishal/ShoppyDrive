<?php

use App\Http\Controllers\Auth\EmailVerificationCodeController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'facebook'])
    ->name('social.redirect');

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'facebook'])
    ->name('social.callback');

Route::post('/email/verify-code', [EmailVerificationCodeController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.verify-code');
