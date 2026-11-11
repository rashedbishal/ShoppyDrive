<?php

use Illuminate\Support\Facades\Route;

/*
 * The starter-kit /settings pages were merged into the customer's Account
 * area (routes/account.php), which is linked from the header menu. These keep
 * old bookmarks and links working (GET only: the old form endpoints are gone).
 * Not under any auth middleware on purpose: the destination applies its own
 * login check.
 */
foreach ([
    'settings' => '/account/profile',
    'settings/profile' => '/account/profile',
    'settings/connections' => '/account/connections',
    'settings/security' => '/account/password',
    'settings/appearance' => '/account/profile',
] as $old => $new) {
    Route::get($old, fn () => redirect($new, 301));
}
