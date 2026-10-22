<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
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
