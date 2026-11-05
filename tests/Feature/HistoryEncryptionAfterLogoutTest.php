<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * SEC-021: Inertia keeps rendered pages in the browser's history state, so
 * a shared PC's next user pressing Back after logout could see the
 * previous customer's/admin's pages straight from history, with no server
 * request at all. The fix has two parts: encrypting history for the
 * account/admin route groups (routes/account.php, routes/admin.php, via
 * the 'history.encrypt' alias → ConditionallyEncryptHistory) and rotating
 * the encryption key on logout, which these tests cover. Inertia::clearHistory()
 * stores a flag in the session for exactly one subsequent request
 * (Response::__construct() pulls it) — asserting it lands in the
 * post-logout session proves the timing is right: session()->invalidate()
 * (which flushes everything) must run before clearHistory(), not after.
 *
 * 'history.encrypt' is this app's own alias, not a same-named override of
 * inertia-laravel's own 'inertia.encrypt' (that package's service provider
 * registers its alias during boot, after bootstrap/app.php's middleware
 * config runs, and would silently win that conflict) — it wraps
 * Inertia::encryptHistory() behind a check that the connection is
 * actually secure, since history encryption needs window.crypto.subtle,
 * which plain HTTP (anywhere but localhost) doesn't have. Telling the
 * client to encrypt anyway doesn't fail quietly — Inertia's own
 * encryptHistory() throws ("Unable to encrypt history") as an uncaught
 * promise rejection that stops the page visit from completing, which is
 * exactly the regression this guards: these two route groups, visited
 * over plain HTTP, would otherwise get stuck "loading" forever.
 */
test('customer logout flags the next response to clear history', function () {
    $user = User::factory()->create();
    actingAs($user);

    $response = $this->post(route('logout'));

    $response->assertRedirect();
    expect($response->getSession()->get('inertia.clear_history'))->toBeTrue();
});

test('admin logout flags the next response to clear history', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    expect($response->getSession()->get('inertia.clear_history'))->toBeTrue();
});

test('the account and admin route groups carry the history-encryption middleware', function () {
    expect(Route::getRoutes()->getByName('account.dashboard')->gatherMiddleware())
        ->toContain('history.encrypt');
    expect(Route::getRoutes()->getByName('admin.dashboard')->gatherMiddleware())
        ->toContain('history.encrypt');
});

test('SEC-021 regression: history encryption does not turn on over plain HTTP for a customer', function () {
    // encryptHistory is a top-level page-object key, a sibling of props —
    // assertInertia()/AssertableInertia only ever wraps props, so reading
    // the raw page array directly is the only way to check it.
    //
    // Plain HTTP (every local/LAN dev visit, and the default in tests) —
    // encryptHistory must NOT be requested, or the browser throws trying
    // to honor it (no window.crypto.subtle without HTTPS/localhost).
    $user = User::factory()->create();
    actingAs($user);

    $response = $this->get(route('account.dashboard'));
    expect($response->viewData('page')['encryptHistory'] ?? false)->toBeFalse();
});

test('SEC-021 regression: history encryption does not turn on over plain HTTP for an admin', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $response = $this->get(route('admin.dashboard'));
    expect($response->viewData('page')['encryptHistory'] ?? false)->toBeFalse();
});

test('history encryption turns on over an actually secure connection', function () {
    // Symfony's Request::create() derives HTTPS from the URL's own scheme
    // and unsets whatever withServerVariables(['HTTPS' => 'on']) set if
    // the URL itself is http:// (which route() always generates here) —
    // the URL has to actually be https:// for $request->secure() to see it.
    $user = User::factory()->create();
    actingAs($user);

    $url = str_replace('http://', 'https://', route('account.dashboard'));
    $response = $this->get($url);

    expect($response->viewData('page')['encryptHistory'] ?? false)->toBeTrue();
});
