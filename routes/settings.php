<?php

use App\Http\Controllers\Settings\ConnectionsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'account.active'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->middleware('throttle:account-action')->name('profile.update');
    // SEC-009: confirm (or cancel) an email change with the code sent to the current address.
    Route::post('settings/profile/email/confirm', [ProfileController::class, 'confirmEmailChange'])->middleware('throttle:verification-code')->name('profile.email.confirm');
    Route::delete('settings/profile/email/pending', [ProfileController::class, 'cancelEmailChange'])->middleware('throttle:account-action')->name('profile.email.cancel');

    Route::get('settings/connections', [ConnectionsController::class, 'edit'])->name('connections.edit');
    Route::put('settings/connections/password', [ConnectionsController::class, 'setPassword'])->middleware('throttle:password-check')->name('connections.password.update');
    Route::delete('settings/connections/{socialAccount}', [ConnectionsController::class, 'destroy'])->middleware('throttle:account-action')->name('connections.destroy');
});

Route::middleware(['auth', 'verified', 'auth.session', 'account.active'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->middleware('throttle:account-action')->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
