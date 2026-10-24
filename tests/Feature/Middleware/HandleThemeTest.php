<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('global mode shares the configured theme', function () {
    Setting::create([
        'key' => 'theme_config',
        'value' => ['mode' => 'global', 'global_theme' => 'glassmorphism'],
        'group' => 'theme',
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('theme', 'glassmorphism'));
});

test('a fresh storefront document applies the configured theme to the html element', function () {
    Setting::create([
        'key' => 'theme_config',
        'value' => ['mode' => 'global', 'global_theme' => 'glassmorphism'],
        'group' => 'theme',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme="glassmorphism"', false);
});

test('with no theme_config row it defaults to the default theme', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('theme', 'default'));
});

test('ab test mode assigns and then stickily reuses a cookie', function () {
    Setting::create([
        'key' => 'theme_config',
        'value' => [
            'mode' => 'ab_test',
            'ab_test' => ['variants' => [
                ['theme' => 'neomorphism', 'weight' => 100],
                ['theme' => 'aurora', 'weight' => 0],
            ]],
        ],
        'group' => 'theme',
    ]);

    $first = $this->get(route('home'));
    $first->assertOk();
    $first->assertInertia(fn ($page) => $page->where('theme', 'neomorphism'));
    $cookie = $first->headers->getCookies()[0] ?? null;

    expect($cookie)->not->toBeNull();
    expect($cookie->getName())->toBe('theme_variant');
    expect($cookie->getValue())->toBe('neomorphism');

    $second = $this->withCookie('theme_variant', 'neomorphism')->get(route('home'));
    $second->assertOk();
    $second->assertInertia(fn ($page) => $page->where('theme', 'neomorphism'));
});

test('ab test mode falls back when the cookie theme is no longer a variant', function () {
    Setting::create([
        'key' => 'theme_config',
        'value' => [
            'mode' => 'ab_test',
            'ab_test' => ['variants' => [
                ['theme' => 'aurora', 'weight' => 100],
            ]],
        ],
        'group' => 'theme',
    ]);

    $response = $this->withCookie('theme_variant', 'neomorphism')->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('theme', 'aurora'));
});

test('region mode falls back to the default region when geolocation fails', function () {
    Http::fake(fn () => Http::response(['status' => 'fail'], 200));

    Setting::create([
        'key' => 'theme_config',
        'value' => [
            'mode' => 'region',
            'region_map' => ['Dhaka' => 'glassmorphism', 'default' => 'aurora'],
        ],
        'group' => 'theme',
    ]);

    $response = $this->get(route('home'), ['REMOTE_ADDR' => '203.0.113.5']);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('theme', 'aurora'));
});

test('admin preview override takes precedence and skips the cookie', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    actingAs($admin, 'admin');

    $response = $this->get(route('home', ['preview_theme' => 'aurora']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('theme', 'aurora'));
    $themeCookie = array_filter($response->headers->getCookies(), fn ($cookie) => $cookie->getName() === 'theme_variant');
    expect($themeCookie)->toBeEmpty();
});

test('preview_theme query param is ignored for guests', function () {
    $response = $this->get(route('home', ['preview_theme' => 'aurora']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('theme', 'default'));
});
