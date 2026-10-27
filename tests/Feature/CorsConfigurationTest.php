<?php

declare(strict_types=1);

test('a request from an allowed origin receives the matching Access-Control-Allow-Origin header', function () {
    config(['cors.allowed_origins' => ['https://trusted.example.com']]);

    $response = $this->withHeaders(['Origin' => 'https://trusted.example.com'])
        ->get('/api/search/suggestions?q=shirt');

    $response->assertHeader('Access-Control-Allow-Origin', 'https://trusted.example.com');
});

test('a request from an origin not on the allow-list never gets that origin reflected back', function () {
    config(['cors.allowed_origins' => ['https://trusted.example.com']]);

    $response = $this->withHeaders(['Origin' => 'https://evil.example.com'])
        ->get('/api/search/suggestions?q=shirt');

    // With exactly one configured origin, the underlying CORS library
    // echoes that one fixed value on every response rather than checking
    // the request's actual Origin (a documented optimization: with only
    // one possible value, there's nothing to branch on). This is still
    // safe — browsers compare the header against their own page's origin,
    // so evil.example.com's script is rejected regardless — but the
    // header must never equal the untrusted request's own origin.
    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://evil.example.com');
});

test('a request from an origin not on a multi-entry allow-list receives no CORS header', function () {
    config(['cors.allowed_origins' => ['https://trusted-one.example.com', 'https://trusted-two.example.com']]);

    $response = $this->withHeaders(['Origin' => 'https://evil.example.com'])
        ->get('/api/search/suggestions?q=shirt');

    $response->assertHeaderMissing('Access-Control-Allow-Origin');
});

test('with no configured origins (the project default), nothing is cross-origin accessible', function () {
    config(['cors.allowed_origins' => []]);

    $response = $this->withHeaders(['Origin' => 'https://anything.example.com'])
        ->get('/api/search/suggestions?q=shirt');

    $response->assertHeaderMissing('Access-Control-Allow-Origin');
});

test('a preflight OPTIONS request for an allowed origin gets a successful CORS preflight response', function () {
    config(['cors.allowed_origins' => ['https://trusted.example.com']]);

    $response = $this->withHeaders([
        'Origin' => 'https://trusted.example.com',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/search/suggestions');

    $response->assertSuccessful();
    $response->assertHeader('Access-Control-Allow-Origin', 'https://trusted.example.com');
    $response->assertHeader('Access-Control-Allow-Methods');
});

test('a preflight OPTIONS request for an origin not on a multi-entry allow-list is not granted CORS access', function () {
    config(['cors.allowed_origins' => ['https://trusted-one.example.com', 'https://trusted-two.example.com']]);

    $response = $this->withHeaders([
        'Origin' => 'https://evil.example.com',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/search/suggestions');

    $response->assertHeaderMissing('Access-Control-Allow-Origin');
});

test('CORS never adds credentials support, even for an allowed origin', function () {
    config(['cors.allowed_origins' => ['https://trusted.example.com']]);

    $response = $this->withHeaders(['Origin' => 'https://trusted.example.com'])
        ->get('/api/search/suggestions?q=shirt');

    $response->assertHeaderMissing('Access-Control-Allow-Credentials');
});

test('CORS configuration is scoped to api/* and never touches session-cookie-backed web routes', function () {
    config(['cors.allowed_origins' => ['https://trusted.example.com']]);

    $response = $this->withHeaders(['Origin' => 'https://trusted.example.com'])->get('/');

    $response->assertHeaderMissing('Access-Control-Allow-Origin');
});

test('legitimate same-origin requests to the public api are unaffected by CORS configuration', function () {
    config(['cors.allowed_origins' => []]);

    $this->get('/api/search/suggestions?q=shirt')->assertSuccessful();
});
