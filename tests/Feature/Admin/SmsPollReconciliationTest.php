<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\ReceivedSms;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * FSA-07: the admin sidebar badge used to come from the removed
 * GET /admin/sms/poll endpoint (replaced by web push, PERF-006's
 * follow-up) — it's now the unmatched_sms_count shared Inertia prop,
 * exercised here by visiting any admin page, same mechanism as
 * unseen_orders_count (see UnseenOrdersTest).
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

test('unmatched_sms_count includes only unmatched SMS past the staleness threshold', function () {
    receivedSmsAt(now()->subMinutes(45)); // stale, unmatched — counted
    receivedSmsAt(now()->subMinutes(31)); // stale, unmatched — counted
    receivedSmsAt(now()->subMinutes(5)); // recent, unmatched — excluded
    receivedSmsAt(now()->subMinutes(45), matched: true); // stale but matched — excluded

    actingAs($this->admin, 'admin')
        ->get(route('admin.received-sms.index'))
        ->assertInertia(fn (Assert $page) => $page->where('unmatched_sms_count', 2));
});

test('unmatched_sms_count is zero when every unmatched SMS is recent', function () {
    receivedSmsAt(now()->subMinutes(2));
    receivedSmsAt(now()->subMinutes(10));

    actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('unmatched_sms_count', 0));
});

test('unmatched_sms_count is null for an admin without the received_sms permission', function () {
    receivedSmsAt(now()->subMinutes(45));
    $restricted = Admin::create([
        'name' => 'Restricted', 'email' => 'restricted-'.uniqid().'@example.com', 'password' => bcrypt('password'),
    ]);

    actingAs($restricted, 'admin')
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('unmatched_sms_count', null));
});

test('unmatched_sms_count is null for a guest or a customer', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('unmatched_sms_count', null));
});

test('the removed sms poll endpoint no longer exists', function () {
    actingAs($this->admin, 'admin')->getJson('/admin/sms/poll')->assertNotFound();
});
