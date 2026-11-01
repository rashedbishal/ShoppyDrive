<?php

use App\Http\Middleware\RedirectToHttps;
use Illuminate\Http\Request;

// Same reasoning as RedirectToCanonicalDomainTest: the HTTP test client
// always roots requests at the configured APP_URL, so scheme/host can't be
// simulated through the usual get()/post() helpers — this exercises the
// middleware directly against an explicitly-built Request instead.
function httpsNextCallsThrough(): Closure
{
    return fn (Request $request) => response('ok');
}

test('a plain HTTP request in production redirects to the equivalent HTTPS URL, preserving path and query', function () {
    config(['app.env' => 'production', 'app.url' => 'https://shoppydrive.com']);

    $request = Request::create('http://shoppydrive.com/about?ref=ad', 'GET');

    $response = (new RedirectToHttps)->handle($request, httpsNextCallsThrough());

    expect($response->getStatusCode())->toBe(308);
    expect($response->headers->get('Location'))->toBe('https://shoppydrive.com/about?ref=ad');
});

test('a POST over plain HTTP in production redirects preserving the method via 308', function () {
    config(['app.env' => 'production', 'app.url' => 'https://shoppydrive.com']);

    $request = Request::create('http://shoppydrive.com/contact', 'POST');

    $response = (new RedirectToHttps)->handle($request, httpsNextCallsThrough());

    expect($response->getStatusCode())->toBe(308);
    expect($response->headers->get('Location'))->toBe('https://shoppydrive.com/contact');
});

test('an HTTPS request in production is not redirected again', function () {
    config(['app.env' => 'production', 'app.url' => 'https://shoppydrive.com']);

    $request = Request::create('https://shoppydrive.com/about', 'GET');

    $response = (new RedirectToHttps)->handle($request, httpsNextCallsThrough());

    expect($response->getStatusCode())->toBe(200);
});

test('outside production, a plain HTTP request is left alone', function (string $env) {
    config(['app.env' => $env, 'app.url' => 'https://shoppydrive.com']);

    $request = Request::create('http://shoppydrive.com/', 'GET');

    $response = (new RedirectToHttps)->handle($request, httpsNextCallsThrough());

    expect($response->getStatusCode())->toBe(200);
})->with(['local' => ['local'], 'testing' => ['testing']]);

test('an untrusted X-Forwarded-Proto header cannot be used to bypass HTTPS enforcement', function () {
    config(['app.env' => 'production', 'app.url' => 'https://shoppydrive.com']);

    $request = Request::create('http://shoppydrive.com/', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'https');

    $response = (new RedirectToHttps)->handle($request, httpsNextCallsThrough());

    expect($response->getStatusCode())->toBe(308);
    expect($response->headers->get('Location'))->toBe('https://shoppydrive.com/');
});

test('the redirect target host never comes from an untrusted incoming Host header', function () {
    config(['app.env' => 'production', 'app.url' => 'https://shoppydrive.com']);

    $request = Request::create('http://shoppydrive.com/account', 'GET');
    $request->headers->set('Host', 'evil.example.com');

    $response = (new RedirectToHttps)->handle($request, httpsNextCallsThrough());

    expect($response->getStatusCode())->toBe(308);
    expect($response->headers->get('Location'))->toBe('https://shoppydrive.com/account');
});
