<?php

use App\Models\Admin;
use App\Models\HomepageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->setting = HomepageSetting::create([
        'section_key' => 'hero_slider',
        'section_name' => 'Hero Slider',
        'title' => 'Hero Slider',
        'is_visible' => true,
        'sort_order' => 1,
    ]);
});

test('admin can set the hero slider autoplay interval in seconds', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.homepage.update', $this->setting), [
        'title' => 'Hero Slider',
        'description' => '',
        'eyebrow' => '',
        'action_label' => '',
        'action_href' => '',
        'is_visible' => true,
        'sort_order' => 1,
        'metadata' => ['autoplay_seconds' => 10],
    ]);

    $response->assertRedirect();

    expect($this->setting->refresh()->metadata['autoplay_seconds'])->toBe(10);
});

test('autoplay_seconds must stay within the allowed range', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.homepage.update', $this->setting), [
        'title' => 'Hero Slider',
        'description' => '',
        'eyebrow' => '',
        'action_label' => '',
        'action_href' => '',
        'is_visible' => true,
        'sort_order' => 1,
        'metadata' => ['autoplay_seconds' => 1],
    ]);

    $response->assertInvalid(['metadata.autoplay_seconds']);
});

test('homepage shares the configured autoplay interval with the storefront', function () {
    $this->setting->update(['metadata' => ['autoplay_seconds' => 8]]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('homepage_settings.hero_slider.metadata.autoplay_seconds', 8)
    );
});
