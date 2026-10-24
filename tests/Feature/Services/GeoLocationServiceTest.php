<?php

declare(strict_types=1);

use App\Services\GeoLocationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('resolves a known division from a successful lookup', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'success', 'regionName' => 'Dhaka Division']),
    ]);

    $service = new GeoLocationService;

    expect($service->divisionForIp('203.0.113.5'))->toBe('Dhaka');
});

test('normalizes a known alias', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'success', 'regionName' => 'Chittagong Division']),
    ]);

    $service = new GeoLocationService;

    expect($service->divisionForIp('203.0.113.6'))->toBe('Chattogram');
});

test('returns null on a failed lookup', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'fail']),
    ]);

    $service = new GeoLocationService;

    expect($service->divisionForIp('203.0.113.7'))->toBeNull();
});

test('returns null when the request throws', function () {
    Http::fake(function () {
        throw new ConnectionException('timed out');
    });

    $service = new GeoLocationService;

    expect($service->divisionForIp('203.0.113.8'))->toBeNull();
});

test('returns null immediately for a local ip without calling out', function () {
    Http::fake();

    $service = new GeoLocationService;

    expect($service->divisionForIp('127.0.0.1'))->toBeNull();
    Http::assertNothingSent();
});

test('caches a lookup so a second call for the same ip skips the http request', function () {
    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'success', 'regionName' => 'Sylhet Division']),
    ]);

    $service = new GeoLocationService;

    expect($service->divisionForIp('203.0.113.9'))->toBe('Sylhet');
    expect($service->divisionForIp('203.0.113.9'))->toBe('Sylhet');

    Http::assertSentCount(1);
});
