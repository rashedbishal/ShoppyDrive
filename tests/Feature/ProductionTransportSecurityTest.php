<?php

declare(strict_types=1);

/*
 * SEC-013: production sends HSTS (30 days since DISC-15, HTTPS responses only) and an
 * enforced CSP, every response limits browser features, and /api/*
 * refuses plain HTTP so a misconfigured caller can't send its token
 * unencrypted. CSP reports are logged once per distinct violation a day.
 */

use App\Models\Setting;
use App\Support\CspNonce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;

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

test('production HTTPS responses carry 30-day HSTS for the main domain, without subdomains or preload', function () {
    // DISC-15 (owner, 2026-11-13): raised from one day (86400) to 30 days.
    asProduction();

    $response = $this->get('https://localhost/up');

    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=2592000');
    expect($response->headers->get('Strict-Transport-Security'))->not->toContain('includeSubDomains')
        ->and($response->headers->get('Strict-Transport-Security'))->not->toContain('preload');
});

test('SEC-013: production enforces a CSP where scripts need a nonce and inline scripts are not trusted', function () {
    asProduction();

    $headers = $this->get('https://localhost/up')->headers;
    $policy = (string) $headers->get('Content-Security-Policy');

    preg_match('/script-src ([^;]+);/', $policy, $scriptSources);

    expect($headers->has('Content-Security-Policy-Report-Only'))->toBeFalse()
        ->and($scriptSources[1])->toMatch("/'nonce-[A-Za-z0-9]+' 'strict-dynamic'/")
        ->and($scriptSources[1])->not->toContain('unsafe-inline')
        ->and($scriptSources[1])->not->toContain('unsafe-eval')
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->toContain('report-uri /api/csp-report');
});

test('forms may only submit to this site and to the Meta Pixel endpoint', function () {
    asProduction();

    preg_match('/form-action ([^;]+);/', (string) $this->get('https://localhost/up')->headers->get('Content-Security-Policy'), $formAction);

    expect($formAction[1])->toBe("'self' https://www.facebook.com");
});

test('SEC-013: every request gets its own nonce', function () {
    asProduction();

    $nonceFrom = function (): string {
        preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $this->get('https://localhost/up')->headers->get('Content-Security-Policy'), $match);

        return $match[1];
    };

    expect($nonceFrom())->not->toBe($nonceFrom());
});

test('SEC-013: every executable inline script on a page, including pasted custom code, carries the header nonce', function () {
    asProduction();

    Setting::create(['key' => 'custom_header_code', 'value' => '<script async src="https://www.googletagmanager.com/gtm.js?id=GTM-TEST"></script><script>window.dataLayer = [];</script>', 'group' => 'marketing']);
    Setting::create(['key' => 'custom_footer_code', 'value' => '<SCRIPT>window.footerRan = true;</SCRIPT>', 'group' => 'marketing']);

    $response = $this->get('https://localhost/login');

    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);
    $nonce = $match[1];

    preg_match_all('/<script\b([^>]*)>/i', $response->getContent(), $tags);

    $executableTags = array_filter($tags[1], fn (string $attributes): bool => ! preg_match('#type="application/(json|ld\+json)"#', $attributes));

    expect($response->getContent())->toContain('gtm.js?id=GTM-TEST')
        ->and($response->getContent())->toContain('window.footerRan')
        ->and(count($executableTags))->toBeGreaterThanOrEqual(5);

    foreach ($executableTags as $attributes) {
        expect($attributes)->toContain('nonce="'.$nonce.'"');
    }
});

test('SEC-013: a pasted script that already has a nonce keeps it and is not given a second', function () {
    Vite::useCspNonce('request-nonce');

    expect(CspNonce::addToScripts('<script nonce="own">a()</script><script src="/b.js"></script>'))
        ->toBe('<script nonce="own">a()</script><script nonce="request-nonce" src="/b.js"></script>');
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

    Log::shouldHaveReceived('warning')->once()->with('CSP violation', [
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
