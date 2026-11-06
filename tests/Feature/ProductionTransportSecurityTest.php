<?php

declare(strict_types=1);

/*
 * SEC-013: production sends HSTS (one day, HTTPS responses only) and a
 * report-only CSP, every response limits browser features, and /api/*
 * refuses plain HTTP so a misconfigured caller can't send its token
 * unencrypted. CSP reports are logged once per distinct violation a day.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

function asProduction(): void
{
    app()->detectEnvironment(fn () => 'production');
}

afterEach(function () {
    app()->detectEnvironment(fn () => 'testing');
    // Production mode also activates TrustHosts, whose list is a static
    // that would otherwise leak into later tests using other host names.
    Request::setTrustedHosts([]);
});

test('every response limits browser features', function () {
    $this->get('/up')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
});

test('production HTTPS responses carry one-day HSTS for the main domain and a report-only CSP', function () {
    asProduction();

    $response = $this->get('https://localhost/up');

    $response->assertHeader('Strict-Transport-Security', 'max-age=86400');
    expect($response->headers->get('Strict-Transport-Security'))->not->toContain('includeSubDomains')
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toContain('report-uri /api/csp-report')
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

test('HSTS is never sent over plain HTTP', function () {
    asProduction();

    expect($this->get('http://localhost/up')->headers->has('Strict-Transport-Security'))->toBeFalse();
});

test('production refuses plain-HTTP API calls before the token is looked at', function () {
    asProduction();

    $this->postJson('http://localhost/api/sms/receive', ['message' => 'x'], ['Authorization' => 'Bearer whatever'])
        ->assertStatus(426)
        ->assertExactJson(['error' => 'HTTPS is required.']);
    $this->postJson('http://localhost/api/steadfast/webhook', [])->assertStatus(426);
});

test('production HTTPS API calls still reach their controllers', function () {
    asProduction();

    $this->postJson('https://localhost/api/sms/receive', [])->assertStatus(401);
    $this->postJson('https://localhost/api/steadfast/webhook', [])->assertStatus(401);
});

test('outside production plain-HTTP API calls keep working for local development', function () {
    $this->postJson('http://localhost/api/sms/receive', [])->assertStatus(401);
});

test('a CSP report is logged once per distinct violation, without query strings', function () {
    Log::spy();
    $report = json_encode(['csp-report' => [
        'effective-directive' => 'script-src',
        'blocked-uri' => 'https://evil.example/x.js?token=secret',
        'document-uri' => 'https://shoppydrive.com/checkout?coupon=SECRET',
    ]]);

    foreach ([1, 2] as $ignored) {
        $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], $report)->assertNoContent();
    }

    Log::shouldHaveReceived('warning')->once()->with('CSP violation (report-only)', [
        'directive' => 'script-src',
        'blocked' => 'https://evil.example/x.js',
        'page' => 'https://shoppydrive.com/checkout',
    ]);
});

test('a junk CSP report is ignored', function () {
    Log::spy();

    $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], 'not json')->assertNoContent();

    Log::shouldNotHaveReceived('warning');
});
