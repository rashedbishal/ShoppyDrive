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

test('admin can view the social config page', function () {
    actingAs($this->admin, 'admin');

    $response = $this->get(route('admin.settings.social'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/settings/social'));
});

test('admin can view the courier config page', function () {
    actingAs($this->admin, 'admin');

    $response = $this->get(route('admin.settings.courier'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/settings/courier'));
});

test('saving the google card does not clobber facebook or courier settings', function () {
    actingAs($this->admin, 'admin');

    Setting::insert([
        ['key' => 'facebook_client_id', 'value' => json_encode('existing-fb-id'), 'group' => 'social_login'],
        ['key' => 'pathao_client_id', 'value' => json_encode('existing-pathao-id'), 'group' => 'courier'],
    ]);

    $response = $this->put(route('admin.settings.update'), [
        'google_client_id' => 'new-google-id.apps.googleusercontent.com',
        'google_client_secret' => 'new-google-secret',
        'google_redirect_uri' => 'https://example.test/auth/google/callback',
    ]);

    $response->assertRedirect();

    expect(Setting::where('key', 'google_client_id')->first()->value)->toBe('new-google-id.apps.googleusercontent.com');
    expect(Setting::where('key', 'facebook_client_id')->first()->value)->toBe('existing-fb-id');
    expect(Setting::where('key', 'pathao_client_id')->first()->value)->toBe('existing-pathao-id');
});
