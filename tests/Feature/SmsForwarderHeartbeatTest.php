<?php

declare(strict_types=1);

use App\Models\ReceivedSms;
use App\Support\SmsForwarderHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Tests/TestCase records a fresh ping for every test; these start from none.
    Cache::store(config('cache.limiter'))->flush();
});

/*
 * OPS-005: the forwarder app's foreground service now pings this endpoint
 * every 1-2 minutes purely to prove the phone is alive, so the server can
 * tell "the phone is off" apart from "no bKash/Nagad payment happened to
 * arrive" within minutes instead of hours.
 */

test('a correct token records a heartbeat', function (): void {
    setSmsForwarderToken('real-forwarder-token');

    expect(SmsForwarderHeartbeat::lastHeartbeatAt())->toBeNull();

    $this->withToken('real-forwarder-token')
        ->postJson('/api/sms/heartbeat')
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect(SmsForwarderHeartbeat::lastHeartbeatAt())->not->toBeNull();
    expect(SmsForwarderHeartbeat::isDown())->toBeFalse();
});

test('a wrong token is rejected and records nothing', function (): void {
    setSmsForwarderToken('real-forwarder-token');

    $this->withToken('wrong-guess')
        ->postJson('/api/sms/heartbeat')
        ->assertUnauthorized()
        ->assertJson(['error' => 'Unauthorized']);

    expect(SmsForwarderHeartbeat::lastHeartbeatAt())->toBeNull();
});

test('a missing server token configuration returns 401, never 500', function (): void {
    setSmsForwarderToken(null);

    $this->withToken('anything')
        ->postJson('/api/sms/heartbeat')
        ->assertUnauthorized()
        ->assertJson(['error' => 'Unauthorized']);
});

test('isDown is false right after a heartbeat and true once it is stale', function (): void {
    SmsForwarderHeartbeat::record();
    expect(SmsForwarderHeartbeat::isDown())->toBeFalse();

    $this->travel(6)->minutes();
    expect(SmsForwarderHeartbeat::isDown())->toBeTrue();
});

test('with no heartbeat on record the phone counts as down, whatever SMS history there is', function (): void {
    expect(SmsForwarderHeartbeat::lastHeartbeatAt())->toBeNull();

    // No SMS ever, and no ping: not known to be on, so not offered.
    expect(SmsForwarderHeartbeat::isDown())->toBeTrue();

    // A recent SMS does not change that: only a ping proves the phone is on now.
    ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'x',
        'received_at' => now(), 'parsed_transaction_id' => uniqid('TXN'), 'parsed_amount' => 100,
    ]);

    expect(SmsForwarderHeartbeat::isDown())->toBeTrue();

    // The first ping brings it back.
    SmsForwarderHeartbeat::record();
    expect(SmsForwarderHeartbeat::isDown())->toBeFalse();
});

test('a stale heartbeat means down even if an SMS recently arrived', function (): void {
    ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'x',
        'received_at' => now(), 'parsed_transaction_id' => uniqid('TXN'), 'parsed_amount' => 100,
    ]);

    SmsForwarderHeartbeat::record();
    $this->travel(6)->minutes();

    // Only the heartbeat's own freshness counts, never SMS traffic.
    expect(SmsForwarderHeartbeat::isDown())->toBeTrue();
});

/*
 * A deploy runs `cache:clear`, which used to erase the heartbeat, so a phone
 * that was already off stayed "available" (slow 6-hour fallback) after every
 * deploy. The heartbeat now lives in the durable limiter store instead.
 */
test('a recorded heartbeat survives cache:clear, so an off phone stays detected after a deploy', function (): void {
    // Production shape: default cache and limiter cache are different stores.
    config(['cache.default' => 'array', 'cache.limiter' => 'database']);

    SmsForwarderHeartbeat::record();
    $this->travel(10)->minutes();

    $this->artisan('cache:clear')->assertSuccessful();

    expect(SmsForwarderHeartbeat::lastHeartbeatAt())->not->toBeNull()
        ->and(SmsForwarderHeartbeat::isDown())->toBeTrue();
});

test('the admin SMS forwarder page reports the phone status', function (): void {
    $admin = createMotherAdmin(['email' => 'mother-sms@example.com']);

    // No ping on record: shown as offline, with the explanation.
    $this->actingAs($admin, 'admin')->get(route('admin.settings.sms-forwarder.index'))
        ->assertInertia(fn ($page) => $page
            ->where('forwarderStatus.has_heartbeat', false)
            ->where('forwarderStatus.is_down', true)
            ->where('forwarderStatus.last_heartbeat_at', null));

    SmsForwarderHeartbeat::record();

    $this->actingAs($admin, 'admin')->get(route('admin.settings.sms-forwarder.index'))
        ->assertInertia(fn ($page) => $page
            ->where('forwarderStatus.has_heartbeat', true)
            ->where('forwarderStatus.is_down', false)
            ->where('forwarderStatus.minutes_since_heartbeat', 0));

    $this->travel(7)->minutes();

    $this->actingAs($admin, 'admin')->get(route('admin.settings.sms-forwarder.index'))
        ->assertInertia(fn ($page) => $page
            ->where('forwarderStatus.is_down', true)
            ->where('forwarderStatus.minutes_since_heartbeat', 7)
            ->where('forwarderStatus.down_after_minutes', 5));
});

test('the checkout page offers bKash/Nagad only while the phone is pinging', function (): void {
    createMotherAdmin();

    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('mobile_banking_available', false));

    SmsForwarderHeartbeat::record();

    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('mobile_banking_available', true));
});
