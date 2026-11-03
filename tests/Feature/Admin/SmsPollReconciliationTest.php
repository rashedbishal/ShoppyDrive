<?php

declare(strict_types=1);

use App\Models\ReceivedSms;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * FSA-07: the admin sidebar badge reuses the existing 5-second poll
 * (useSmsPolling) instead of a new endpoint/table — this exercises the
 * unmatched_count field it now returns alongside the existing toast list.
 */
beforeEach(function () {
    $this->admin = createAdmin();
});

function receivedSmsAt(Carbon|CarbonImmutable $receivedAt, bool $matched = false): ReceivedSms
{
    return ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID '.uniqid(),
        'received_at' => $receivedAt, 'parsed_transaction_id' => uniqid('TXN'), 'parsed_amount' => 100,
        'is_matched' => $matched,
    ]);
}

test('unmatched_count includes only unmatched SMS past the staleness threshold', function () {
    receivedSmsAt(now()->subMinutes(45)); // stale, unmatched — counted
    receivedSmsAt(now()->subMinutes(31)); // stale, unmatched — counted
    receivedSmsAt(now()->subMinutes(5)); // recent, unmatched — excluded
    receivedSmsAt(now()->subMinutes(45), matched: true); // stale but matched — excluded

    actingAs($this->admin, 'admin')
        ->getJson(route('admin.sms.poll'))
        ->assertJson(['unmatched_count' => 2]);
});

test('unmatched_count is zero when every unmatched SMS is recent', function () {
    receivedSmsAt(now()->subMinutes(2));
    receivedSmsAt(now()->subMinutes(10));

    actingAs($this->admin, 'admin')
        ->getJson(route('admin.sms.poll'))
        ->assertJson(['unmatched_count' => 0]);
});

test('the poll response still returns the existing sms list unchanged alongside the new count', function () {
    $sms = receivedSmsAt(now());

    actingAs($this->admin, 'admin')
        ->getJson(route('admin.sms.poll'))
        ->assertJson(['unmatched_count' => 0])
        ->assertJsonFragment(['id' => $sms->id]);
});
