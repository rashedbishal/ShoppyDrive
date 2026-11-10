<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * PERF-006 (web push follow-up): one row per browser/device an admin has
 * opted SMS-arrival push notifications on — see resources/js/hooks/useWebPushSms.ts.
 */
beforeEach(function () {
    $this->admin = createAdmin();
});

function subscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/example-endpoint'): array
{
    return [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => 'a-public-key', 'auth' => 'an-auth-token'],
    ];
}

test('an admin can subscribe to push notifications', function () {
    actingAs($this->admin, 'admin')
        ->postJson(route('admin.push-subscriptions.store'), subscriptionPayload())
        ->assertOk();

    $subscription = PushSubscription::where('admin_id', $this->admin->id)->sole();
    expect($subscription->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/example-endpoint')
        ->and($subscription->public_key)->toBe('a-public-key')
        ->and($subscription->auth_token)->toBe('an-auth-token')
        ->and($subscription->endpoint_hash)->toBe(hash('sha256', 'https://fcm.googleapis.com/fcm/send/example-endpoint'));
});

test('subscribing twice with the same endpoint updates the one row instead of duplicating it', function () {
    actingAs($this->admin, 'admin')->postJson(route('admin.push-subscriptions.store'), subscriptionPayload());

    $payload = subscriptionPayload();
    $payload['keys']['auth'] = 'a-rotated-auth-token';
    actingAs($this->admin, 'admin')->postJson(route('admin.push-subscriptions.store'), $payload)->assertOk();

    expect(PushSubscription::where('admin_id', $this->admin->id)->count())->toBe(1)
        ->and(PushSubscription::where('admin_id', $this->admin->id)->sole()->auth_token)->toBe('a-rotated-auth-token');
});

test('two different admins can each subscribe without clashing', function () {
    $other = createAdmin();

    actingAs($this->admin, 'admin')->postJson(route('admin.push-subscriptions.store'), subscriptionPayload('https://fcm.googleapis.com/fcm/send/admin-one'))->assertOk();
    actingAs($other, 'admin')->postJson(route('admin.push-subscriptions.store'), subscriptionPayload('https://fcm.googleapis.com/fcm/send/admin-two'))->assertOk();

    expect(PushSubscription::count())->toBe(2);
});

test('subscribing requires an endpoint and both keys', function () {
    actingAs($this->admin, 'admin')
        ->postJson(route('admin.push-subscriptions.store'), ['endpoint' => '', 'keys' => []])
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

test('an admin without the received_sms permission cannot subscribe', function () {
    $restricted = Admin::create([
        'name' => 'Restricted', 'email' => 'restricted-'.uniqid().'@example.com', 'password' => bcrypt('password'),
    ]);

    actingAs($restricted, 'admin')
        ->postJson(route('admin.push-subscriptions.store'), subscriptionPayload())
        ->assertForbidden();

    expect(PushSubscription::count())->toBe(0);
});

test('an admin can unsubscribe, removing only their own matching row', function () {
    $other = createAdmin();
    actingAs($this->admin, 'admin')->postJson(route('admin.push-subscriptions.store'), subscriptionPayload('https://fcm.googleapis.com/fcm/send/shared-looking-url'));
    actingAs($other, 'admin')->postJson(route('admin.push-subscriptions.store'), subscriptionPayload('https://fcm.googleapis.com/fcm/send/other-endpoint'));

    actingAs($this->admin, 'admin')
        ->deleteJson(route('admin.push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/shared-looking-url'])
        ->assertOk();

    expect(PushSubscription::where('admin_id', $this->admin->id)->count())->toBe(0)
        ->and(PushSubscription::where('admin_id', $other->id)->count())->toBe(1);
});

test('deleting an admin cascades to their push subscriptions', function () {
    actingAs($this->admin, 'admin')->postJson(route('admin.push-subscriptions.store'), subscriptionPayload());
    $admin = $this->admin;

    $admin->delete();

    expect(PushSubscription::count())->toBe(0);
});
