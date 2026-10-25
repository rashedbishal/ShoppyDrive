<?php

use App\Models\DailyVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('visiting the storefront creates a daily visit row and sets a cookie', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertCookie('sd_last_seen', now()->toDateString());

    $visit = DailyVisit::whereDate('date', now())->first();
    expect($visit)->not->toBeNull();
    expect($visit->visitor_count)->toBe(1);
    expect($visit->page_view_count)->toBe(1);
});

test('a second page view from the same visitor the same day only increments page views', function () {
    $today = now()->toDateString();

    $this->withCookie('sd_last_seen', $today)->get('/');
    $this->withCookie('sd_last_seen', $today)->get('/about');

    $visit = DailyVisit::whereDate('date', $today)->first();
    expect($visit->visitor_count)->toBe(0);
    expect($visit->page_view_count)->toBe(2);
});

test('a new visitor without the cookie is counted even on a day with existing visits', function () {
    $today = now()->toDateString();
    DailyVisit::create(['date' => $today, 'visitor_count' => 3, 'page_view_count' => 10]);

    $this->get('/');

    $visit = DailyVisit::whereDate('date', $today)->first();
    expect($visit->visitor_count)->toBe(4);
    expect($visit->page_view_count)->toBe(11);
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
