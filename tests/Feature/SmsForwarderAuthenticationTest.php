<?php

declare(strict_types=1);

use App\Models\ReceivedSms;
use App\Models\SmsPattern;

/*
 * FSA-19: config('services.sms_forwarder.token') can resolve to null (a
 * stale config:cache predating this key, or the key missing entirely) —
 * hash_equals() throws a TypeError for a null argument under
 * declare(strict_types=1), which previously surfaced as an uncaught 500
 * instead of the endpoint's own uniform 401. A missing/empty server token
 * must now fail closed into that same 401, never a 500, and never create a
 * ReceivedSms row.
 */

test('a missing server token configuration with a caller token present returns 401, never 500', function (): void {
    config(['services.sms_forwarder.token' => null]);

    $this->withToken('anything')->postJson('/api/sms/receive', ['from' => '01700000000', 'content' => 'test'])
        ->assertUnauthorized()
        ->assertJson(['error' => 'Unauthorized']);

    expect(ReceivedSms::count())->toBe(0);
});

test('a missing server token configuration with no caller token returns 401, never 500', function (): void {
    config(['services.sms_forwarder.token' => null]);

    $this->postJson('/api/sms/receive', ['from' => '01700000000', 'content' => 'test'])
        ->assertUnauthorized()
        ->assertJson(['error' => 'Unauthorized']);

    expect(ReceivedSms::count())->toBe(0);
});

test('an empty server token configuration with a caller token present returns 401', function (): void {
    config(['services.sms_forwarder.token' => '']);

    $this->withToken('anything')->postJson('/api/sms/receive', ['from' => '01700000000', 'content' => 'test'])
        ->assertUnauthorized()
        ->assertJson(['error' => 'Unauthorized']);

    expect(ReceivedSms::count())->toBe(0);
});

test('the correct server token and the correct caller token still succeeds exactly as before', function (): void {
    SmsPattern::create([
        'provider' => 'bkash', 'name' => 'Bkash Money Received', 'identifier' => 'TrxID',
        'txn_id_regex' => 'TrxID\s+([A-Z0-9]+)', 'amount_regex' => 'received Tk\s+([\d,]+(?:\.\d+)?)',
        'is_active' => true, 'sort_order' => 1,
    ]);
    config(['services.sms_forwarder.token' => 'real-forwarder-token']);

    $content = 'You have received Tk 100.00 from 01867603414. Ref. Payment. TrxID FSA19OK1 at 01/01/2026 10:00';

    $this->withToken('real-forwarder-token')
        ->postJson('/api/sms/receive', ['from' => '01867603414', 'content' => $content])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect(ReceivedSms::where('parsed_transaction_id', 'FSA19OK1')->exists())->toBeTrue();
});

test('the correct server token with a wrong caller token still returns 401', function (): void {
    config(['services.sms_forwarder.token' => 'real-forwarder-token']);

    $this->withToken('wrong-guess')
        ->postJson('/api/sms/receive', ['from' => '01700000000', 'content' => 'test'])
        ->assertUnauthorized()
        ->assertJson(['error' => 'Unauthorized']);

    expect(ReceivedSms::count())->toBe(0);
});
