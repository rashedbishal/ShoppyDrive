<?php

declare(strict_types=1);

use App\Models\ReceivedSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function smsForSearch(string $sender, string $transactionId, bool $matched): ReceivedSms
{
    return ReceivedSms::create([
        'sender' => $sender, 'provider' => 'bkash', 'content' => 'payment', 'received_at' => now(),
        'parsed_transaction_id' => $transactionId, 'parsed_amount' => 100, 'is_matched' => $matched,
    ]);
}

test('a search stays inside the unmatched filter, whether it matches by sender or transaction id', function (string $search) {
    smsForSearch('bKash', 'TRXPAID1', matched: true);
    $unmatched = smsForSearch('bKash', 'TRXPAID2', matched: false);

    actingAs(createAdmin(), 'admin');

    $this->get(route('admin.received-sms.index', ['search' => $search, 'matched' => 'no']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sms.data', 1)
            ->where('sms.data.0.id', $unmatched->id));
})->with(['by sender' => 'bKash', 'by transaction id' => 'TRXPAID']);
