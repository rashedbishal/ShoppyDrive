<?php

declare(strict_types=1);

/*
 * The VAPID key pair for instant SMS notifications is created from the admin
 * panel (mother admin only) and stored as a Setting, with .env as a fallback.
 */

use App\Models\Admin;
use App\Models\PushSubscription;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Notifications\NewSmsReceived;
use App\Support\WebPushConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function pushSubscriptionFor(Admin $admin): PushSubscription
{
    return $admin->pushSubscriptions()->create([
        'endpoint' => 'https://push.example.test/'.uniqid(), 'endpoint_hash' => hash('sha256', uniqid()),
        'public_key' => 'pk', 'auth_token' => 'auth',
    ]);
}

beforeEach(function () {
    config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
});

// ── Generating ───────────────────────────────────────────────────────────────

test('the mother admin can generate the keys, and they are saved and usable', function () {
    $mother = createMotherAdmin();

    expect(WebPushConfig::isConfigured())->toBeFalse();

    actingAs($mother, 'admin')
        ->post(route('admin.settings.sms-forwarder.push-keys.generate'))
        ->assertRedirect(route('admin.settings.sms-forwarder.index'))
        ->assertSessionHas('success');

    expect(WebPushConfig::isConfigured())->toBeTrue()
        ->and(WebPushConfig::source())->toBe('cms')
        ->and(WebPushConfig::publicKey())->toMatch('/^[A-Za-z0-9_-]{60,100}$/')
        ->and(WebPushConfig::privateKey())->toMatch('/^[A-Za-z0-9_-]{40,60}$/');
});

test('regenerating replaces the keys and removes the subscriptions made with the old ones', function () {
    $mother = createMotherAdmin();
    actingAs($mother, 'admin')->post(route('admin.settings.sms-forwarder.push-keys.generate'));
    $firstPublic = WebPushConfig::publicKey();
    pushSubscriptionFor($mother);
    pushSubscriptionFor(createAdmin(['email' => 'other-push@example.com']));

    actingAs($mother, 'admin')->post(route('admin.settings.sms-forwarder.push-keys.generate'))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '2 browser(s)'));

    expect(WebPushConfig::publicKey())->not->toBe($firstPublic)
        ->and(PushSubscription::count())->toBe(0);
});

test('an ordinary admin cannot generate the keys', function () {
    $staff = createAdmin(['email' => 'staff-push@example.com']);

    actingAs($staff, 'admin')
        ->post(route('admin.settings.sms-forwarder.push-keys.generate'))
        ->assertForbidden();

    expect(WebPushConfig::isConfigured())->toBeFalse();
});

// ── Where the keys come from ─────────────────────────────────────────────────

test('keys saved in the admin panel win over .env, and .env still works on its own', function () {
    config(['services.webpush.public_key' => 'env-public', 'services.webpush.private_key' => 'env-private']);

    expect(WebPushConfig::publicKey())->toBe('env-public')->and(WebPushConfig::source())->toBe('env');

    Setting::create(['key' => WebPushConfig::PUBLIC_KEY_SETTING, 'value' => 'cms-public', 'group' => 'notifications']);
    Setting::create(['key' => WebPushConfig::PRIVATE_KEY_SETTING, 'value' => 'cms-private', 'group' => 'notifications']);

    expect(WebPushConfig::publicKey())->toBe('cms-public')
        ->and(WebPushConfig::privateKey())->toBe('cms-private')
        ->and(WebPushConfig::source())->toBe('cms');
});

test('blank values count as not configured', function () {
    Setting::create(['key' => WebPushConfig::PUBLIC_KEY_SETTING, 'value' => '   ', 'group' => 'notifications']);

    expect(WebPushConfig::isConfigured())->toBeFalse()->and(WebPushConfig::source())->toBeNull();
});

// ── What the browser is given ────────────────────────────────────────────────

test('admins get the public key (so the Enable banner appears) and never the private one', function () {
    $mother = createMotherAdmin();
    actingAs($mother, 'admin')->post(route('admin.settings.sms-forwarder.push-keys.generate'));
    $public = WebPushConfig::publicKey();
    $private = WebPushConfig::privateKey();

    $response = actingAs($mother, 'admin')->get(route('admin.settings.sms-forwarder.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('vapid_public_key', $public)
        ->where('pushStatus.configured', true)
        ->where('pushStatus.source', 'cms')
        ->where('pushStatus.can_manage', true));
    expect($response->getContent())->not->toContain($private);
});

test('with no keys the admin page says so and offers no key to the browser', function () {
    actingAs(createMotherAdmin(), 'admin')->get(route('admin.settings.sms-forwarder.index'))
        ->assertInertia(fn ($page) => $page
            ->where('vapid_public_key', null)
            ->where('pushStatus.configured', false));
});

test('an ordinary admin sees the status but cannot manage it', function () {
    actingAs(createAdmin(['email' => 'staff-push2@example.com']), 'admin')->get(route('admin.settings.sms-forwarder.index'))
        ->assertInertia(fn ($page) => $page->where('pushStatus.can_manage', false));
});

test('the storefront is never given a key', function () {
    actingAs(createMotherAdmin(), 'admin')->post(route('admin.settings.sms-forwarder.push-keys.generate'));
    auth('admin')->logout();

    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('vapid_public_key', null));
});

// ── Sending ──────────────────────────────────────────────────────────────────

test('the SMS push is sent immediately, not through the queue', function () {
    expect((new ReflectionClass(NewSmsReceived::class))->implementsInterface(ShouldQueue::class))->toBeFalse();
});

test('with no keys, a notification attempt is skipped quietly instead of failing', function () {
    Log::spy();
    $admin = createMotherAdmin();
    pushSubscriptionFor($admin);
    $sms = ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => 'x',
        'received_at' => now(), 'parsed_transaction_id' => 'T1', 'parsed_amount' => 10,
    ]);

    $admin->notifyNow(new NewSmsReceived($sms));

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'no VAPID keys'))->once();
});
