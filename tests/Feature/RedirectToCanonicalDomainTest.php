<?php

use App\Http\Middleware\RedirectToCanonicalDomain;
use Illuminate\Http\Request;

// Laravel's HTTP test client always roots requests at the configured
// APP_URL, so a www.* incoming host can't be simulated through the usual
// get()/post() helpers here — this exercises the middleware directly
// against a Request built with an explicit Host header instead.
function nextCallsThrough(): Closure
{
    return fn (Request $request) => response('ok');
}

test('a www request is redirected to the bare domain preserving path and query', function () {
    $request = Request::create('http://www.shoppydrive.com/about?ref=ad', 'GET');

    $response = (new RedirectToCanonicalDomain)->handle($request, nextCallsThrough());

    expect($response->getStatusCode())->toBe(308);
    expect($response->headers->get('Location'))->toBe('http://shoppydrive.com/about?ref=ad');
});

test('a www POST request is redirected preserving the method via 308', function () {
    $request = Request::create('http://www.shoppydrive.com/contact', 'POST');

    $response = (new RedirectToCanonicalDomain)->handle($request, nextCallsThrough());

    expect($response->getStatusCode())->toBe(308);
    expect($response->headers->get('Location'))->toBe('http://shoppydrive.com/contact');
});

test('a request to the bare domain is not redirected', function () {
    $request = Request::create('http://shoppydrive.com/', 'GET');

    $response = (new RedirectToCanonicalDomain)->handle($request, nextCallsThrough());

    expect($response->getStatusCode())->toBe(200);
});

test('a request to an unrelated host is not redirected', function () {
    $request = Request::create('http://localhost/', 'GET');

    $response = (new RedirectToCanonicalDomain)->handle($request, nextCallsThrough());

    expect($response->getStatusCode())->toBe(200);
});
