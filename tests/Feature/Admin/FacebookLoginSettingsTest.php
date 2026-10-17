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

test('admin can save facebook oauth credentials from the settings page', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.update'), [
        'facebook_client_id' => '1234567890123456',
        'facebook_client_secret' => 'test-secret',
        'facebook_redirect_uri' => 'https://example.test/auth/facebook/callback',
    ]);

    $response->assertRedirect();

    expect(Setting::where('key', 'facebook_client_id')->first()->value)->toBe('1234567890123456');
    expect(Setting::where('key', 'facebook_client_secret')->first()->value)->toBe('test-secret');
    expect(Setting::where('key', 'facebook_redirect_uri')->first()->value)->toBe('https://example.test/auth/facebook/callback');
});

test('cms-configured facebook credentials override config and enable the login button', function () {
    Setting::create(['key' => 'facebook_client_id', 'value' => '1234567890123456', 'group' => 'social_login']);
    Setting::create(['key' => 'facebook_client_secret', 'value' => 'test-secret', 'group' => 'social_login']);
    Setting::create(['key' => 'facebook_redirect_uri', 'value' => 'https://example.test/auth/facebook/callback', 'group' => 'social_login']);

    (new AppServiceProvider(app()))->boot();

    expect(config('services.facebook.client_id'))->toBe('1234567890123456');
    expect(config('services.facebook.client_secret'))->toBe('test-secret');
    expect(config('services.facebook.redirect'))->toBe('https://example.test/auth/facebook/callback');

    $this->get('/login')->assertInertia(fn ($page) => $page->where('settings.facebook_login_enabled', true));
});

test('facebook login button is hidden when no client id is configured', function () {
    $this->get('/login')->assertInertia(fn ($page) => $page->where('settings.facebook_login_enabled', false));
});
