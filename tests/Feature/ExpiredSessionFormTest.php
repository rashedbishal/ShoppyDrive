<?php

declare(strict_types=1);

/*
 * FE-007: an expired session (CSRF 419) on an Inertia form submit sends the
 * customer back to the same form with a message, instead of replacing the
 * form with the error page (losing what they typed).
 */

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // CSRF checks are disabled in tests, so a route that throws the same
    // 419 a TokenMismatchException produces stands in for an expired session.
    Route::middleware('web')->post('/_test/expired-form', fn () => abort(419));
    Route::middleware('web')->get('/_test/expired-form', fn () => abort(419));
});

function inertiaHeaders(): array
{
    return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Referer' => url('/lp/promo')];
}

test('an expired Inertia form submit goes back to the form with a message', function () {
    $response = $this->post('/_test/expired-form', ['name' => 'Rahima'], inertiaHeaders());

    $response->assertRedirect(url('/lp/promo'));

    expect(Inertia\Inertia::getFlashed(request()))->toMatchArray([
        'toast' => [
            'type' => 'error',
            'message' => 'Your session expired for security. Your details are still here — please press the button again.',
        ],
    ]);
});

test('a non-Inertia request still gets the normal 419 response', function () {
    $this->post('/_test/expired-form', [], ['Referer' => url('/lp/promo')])->assertStatus(419);
});

test('a 419 on a GET request is not turned into a redirect', function () {
    // The current asset version, or Inertia answers a GET with 409 (reload).
    $headers = [...inertiaHeaders(), 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];

    $this->get('/_test/expired-form', $headers)->assertStatus(419);
});
