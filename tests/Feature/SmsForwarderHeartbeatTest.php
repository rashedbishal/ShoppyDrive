<?php

declare(strict_types=1);

use App\Models\ReceivedSms;
use App\Support\SmsForwarderHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

test('before any heartbeat has ever been recorded, it falls back to the no-SMS-at-all signal', function (): void {
    expect(SmsForwarderHeartbeat::lastHeartbeatAt())->toBeNull();

    // No SMS ever — not this phone's problem to report yet.
    expect(SmsForwarderHeartbeat::isDown())->toBeFalse();

    ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'x',
        'received_at' => now()->subHours(7), 'parsed_transaction_id' => uniqid('TXN'), 'parsed_amount' => 100,
    ]);

    // Old-app phones that never send a heartbeat still fall back to the
    // original 6-hour silence check.
    expect(SmsForwarderHeartbeat::isDown())->toBeTrue();
});

test('a real heartbeat takes priority over the fallback even if an SMS recently arrived', function (): void {
    ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'x',
        'received_at' => now(), 'parsed_transaction_id' => uniqid('TXN'), 'parsed_amount' => 100,
    ]);

    SmsForwarderHeartbeat::record();
    $this->travel(6)->minutes();

    // A recent SMS alone would say "not down" under the fallback, but once
    // a heartbeat exists, only the heartbeat's own freshness counts.
    expect(SmsForwarderHeartbeat::isDown())->toBeTrue();
});
