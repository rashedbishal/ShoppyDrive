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
 * account/admin route groups (routes/account.php, routes/admin.php — not
 * exercised here, that's the 'inertia.encrypt' middleware itself, already
 * covered by the package's own tests) and rotating the encryption key on
 * logout, which these tests cover. Inertia::clearHistory() stores a flag
 * in the session for exactly one subsequent request (Response::__construct()
 * pulls it) — asserting it lands in the post-logout session proves the
 * timing is right: session()->invalidate() (which flushes everything)
 * must run before clearHistory(), not after.
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

test('the account and admin route groups actually encrypt history', function () {
    expect(Route::getRoutes()->getByName('account.dashboard')->gatherMiddleware())
        ->toContain('inertia.encrypt');
    expect(Route::getRoutes()->getByName('admin.dashboard')->gatherMiddleware())
        ->toContain('inertia.encrypt');
});
