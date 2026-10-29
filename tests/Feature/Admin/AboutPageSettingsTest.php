<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can view the about page config page', function () {
    actingAs($this->admin, 'admin');

    $response = $this->get(route('admin.settings.about-page'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/settings/about-page'));
});

test('admin can save about page content, including repeatable sections', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.update'), [
        'about_hero_title' => 'Custom Hero Title',
        'about_hero_title_highlight' => 'Custom Highlight',
        'about_stats' => [
            ['value' => '10,000+', 'label' => 'Custom Stat'],
        ],
        'about_values' => [
            ['icon' => 'star', 'title' => 'Custom Value', 'description' => 'Custom description'],
        ],
        'about_team' => [
            ['name' => 'Jane Doe', 'role' => 'Founder', 'initials' => 'JD'],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    expect(Setting::where('key', 'about_hero_title')->first()->value)->toBe('Custom Hero Title');
    expect(Setting::where('key', 'about_stats')->first()->value)->toBe([
        ['value' => '10,000+', 'label' => 'Custom Stat'],
    ]);
    expect(Setting::where('key', 'about_values')->first()->value)->toBe([
        ['icon' => 'star', 'title' => 'Custom Value', 'description' => 'Custom description'],
    ]);
    expect(Setting::where('key', 'about_team')->first()->value)->toBe([
        ['name' => 'Jane Doe', 'role' => 'Founder', 'initials' => 'JD'],
    ]);
});

test('saving about page content does not clobber unrelated settings', function () {
    actingAs($this->admin, 'admin');

    Setting::insert([
        ['key' => 'site_name', 'value' => json_encode('Existing Site Name'), 'group' => 'general'],
    ]);

    $response = $this->put(route('admin.settings.update'), [
        'about_cta_title' => 'New CTA Title',
    ]);

    $response->assertRedirect();

    expect(Setting::where('key', 'about_cta_title')->first()->value)->toBe('New CTA Title');
    expect(Setting::where('key', 'site_name')->first()->value)->toBe('Existing Site Name');
});

test('saving about_mission_body strips malicious HTML', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.update'), [
        'about_mission_body' => '<p>Our mission</p><script>alert(1)</script><img src="x" onerror="alert(1)">',
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    $stored = Setting::where('key', 'about_mission_body')->first()->value;

    expect($stored)->not->toContain('<script');
    expect($stored)->not->toContain('onerror');
    expect($stored)->toContain('Our mission');
});

test('saving about_mission_body alongside an API secret leaves the secret byte-for-byte unchanged', function () {
    actingAs($this->admin, 'admin');

    $secret = 'sk_live_<not-actually-html>&unchanged';

    $response = $this->put(route('admin.settings.update'), [
        'about_mission_body' => '<script>alert(1)</script><p>Mission</p>',
        'google_client_secret' => $secret,
        'shipping_rate_inside_dhaka' => '75',
    ]);

    $response->assertRedirect();

    expect(Setting::where('key', 'about_mission_body')->first()->value)->not->toContain('<script');
    expect(Setting::where('key', 'google_client_secret')->first()->value)->toBe($secret);
    expect(Setting::where('key', 'shipping_rate_inside_dhaka')->first()->value)->toBe('75');
});
