<?php

declare(strict_types=1);

test('a gclid query param sets the traffic source to google', function () {
    $response = $this->get('/?gclid=abc123');

    $response->assertOk();
    $response->assertCookie('sd_traffic_source', 'google');
});

test('an fbclid query param sets the traffic source to facebook', function () {
    $response = $this->get('/?fbclid=abc123');

    $response->assertOk();
    $response->assertCookie('sd_traffic_source', 'facebook');
});

test('a facebook utm_source sets the traffic source to facebook', function () {
    $response = $this->get('/?utm_source=Facebook&utm_medium=cpc');

    $response->assertCookie('sd_traffic_source', 'facebook');
});

test('an unrecognized utm_source is stored verbatim', function () {
    $response = $this->get('/?utm_source=newsletter');

    $response->assertCookie('sd_traffic_source', 'newsletter');
});

test('a google referrer sets the traffic source to google and records the referrer host', function () {
    $response = $this->get('/', ['Referer' => 'https://www.google.com/search?q=shoes']);

    $response->assertCookie('sd_traffic_source', 'google');
    $response->assertCookie('sd_traffic_referrer', 'google.com');
});

test('a facebook referrer sets the traffic source to facebook', function () {
    $response = $this->get('/', ['Referer' => 'https://m.facebook.com/']);

    $response->assertCookie('sd_traffic_source', 'facebook');
});

test('an instagram referrer sets the traffic source to instagram', function () {
    $response = $this->get('/', ['Referer' => 'https://l.instagram.com/']);

    $response->assertCookie('sd_traffic_source', 'instagram');
});

test('an unrecognized external referrer is classified as other, with its host recorded', function () {
    $response = $this->get('/', ['Referer' => 'https://some-blog.example.com/post']);

    $response->assertCookie('sd_traffic_source', 'other');
    $response->assertCookie('sd_traffic_referrer', 'some-blog.example.com');
});

test('no referrer and no params is classified as direct', function () {
    $response = $this->get('/');

    $response->assertCookie('sd_traffic_source', 'direct');
});

test('an internal referrer does not set a traffic source cookie', function () {
    config(['app.url' => 'https://shoppydrive.test']);

    $response = $this->get('/about', ['Referer' => 'https://shoppydrive.test/']);

    $response->assertCookieMissing('sd_traffic_source');
});

test('first-touch organic attribution is not overwritten by a later plain visit', function () {
    // Nothing is re-queued in the response — the existing browser cookie is
    // simply left alone, which is how "don't overwrite" has to look here.
    $this->withCookie('sd_traffic_source', 'facebook')
        ->withCookie('sd_traffic_referrer', 'facebook.com')
        ->get('/')
        ->assertCookieMissing('sd_traffic_source');
});

test('an explicit ad click overwrites a previous organic attribution', function () {
    $this->withCookie('sd_traffic_source', 'facebook')
        ->withCookie('sd_traffic_referrer', 'facebook.com')
        ->get('/?gclid=xyz789')
        ->assertCookie('sd_traffic_source', 'google');
});

test('admin routes are not tracked', function () {
    $response = $this->get('/admin/login');

    $response->assertCookieMissing('sd_traffic_source');
});
