<?php

use App\Models\DailyVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('visiting the storefront creates a daily visit row and sets a cookie', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertCookie('sd_last_seen', now('Asia/Dhaka')->toDateString());

    $visit = DailyVisit::whereDate('date', now('Asia/Dhaka')->toDateString())->first();
    expect($visit)->not->toBeNull();
    expect($visit->visitor_count)->toBe(1);
    expect($visit->page_view_count)->toBe(1);
});

test('a second page view from the same visitor the same day only increments page views', function () {
    $today = now('Asia/Dhaka')->toDateString();

    $this->withCookie('sd_last_seen', $today)->get('/');
    $this->withCookie('sd_last_seen', $today)->get('/about');

    $visit = DailyVisit::whereDate('date', $today)->first();
    expect($visit->visitor_count)->toBe(0);
    expect($visit->page_view_count)->toBe(2);
});

test('a new visitor without the cookie is counted even on a day with existing visits', function () {
    $today = now('Asia/Dhaka')->toDateString();
    DailyVisit::create(['date' => $today, 'visitor_count' => 3, 'page_view_count' => 10]);

    $this->get('/');

    $visit = DailyVisit::whereDate('date', $today)->first();
    expect($visit->visitor_count)->toBe(4);
    expect($visit->page_view_count)->toBe(11);
});

test('a late-evening UTC visit is counted on the next Dhaka day, not the UTC day', function () {
    // 21:30 UTC on 5 Nov is 03:30 on 6 Nov in Dhaka (BE-009).
    $this->travelTo(Carbon::parse('2026-11-05 21:30:00', 'UTC'));

    $this->get('/')->assertCookie('sd_last_seen', '2026-11-06');

    expect(DailyVisit::whereDate('date', '2026-11-06')->value('visitor_count'))->toBe(1);
    expect(DailyVisit::whereDate('date', '2026-11-05')->exists())->toBeFalse();
});

test('admin routes are not tracked', function () {
    $this->get('/admin/login');

    expect(DailyVisit::count())->toBe(0);
});

test('bot requests are not tracked', function () {
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->get('/');

    expect(DailyVisit::count())->toBe(0);
});

test('chrome devtools probe requests are not tracked', function () {
    $this->get('/.well-known/appspecific/com.chrome.devtools.json')->assertOk();

    expect(DailyVisit::count())->toBe(0);
});
