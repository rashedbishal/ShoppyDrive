<?php

use App\Models\Admin;
use App\Models\Setting;
use App\Providers\AppServiceProvider;
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

test('admin can save google oauth credentials from the settings page', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.update'), [
        'google_client_id' => 'test-client-id.apps.googleusercontent.com',
        'google_client_secret' => 'test-secret',
        'google_redirect_uri' => 'https://example.test/auth/google/callback',
    ]);

    $response->assertRedirect();

    expect(Setting::where('key', 'google_client_id')->first()->value)->toBe('test-client-id.apps.googleusercontent.com');
    expect(Setting::where('key', 'google_client_secret')->first()->value)->toBe('test-secret');
    expect(Setting::where('key', 'google_redirect_uri')->first()->value)->toBe('https://example.test/auth/google/callback');
});

test('cms-configured google credentials override config and enable the login button', function () {
    Setting::create(['key' => 'google_client_id', 'value' => 'test-client-id', 'group' => 'social_login']);
    Setting::create(['key' => 'google_client_secret', 'value' => 'test-secret', 'group' => 'social_login']);
    Setting::create(['key' => 'google_redirect_uri', 'value' => 'https://example.test/auth/google/callback', 'group' => 'social_login']);

    (new AppServiceProvider(app()))->boot();

    expect(config('services.google.client_id'))->toBe('test-client-id');
    expect(config('services.google.client_secret'))->toBe('test-secret');
    expect(config('services.google.redirect'))->toBe('https://example.test/auth/google/callback');

    $this->get('/login')->assertInertia(fn ($page) => $page->where('settings.google_login_enabled', true));
});

test('google login button is hidden when no client id is configured', function () {
    $this->get('/login')->assertInertia(fn ($page) => $page->where('settings.google_login_enabled', false));
});
