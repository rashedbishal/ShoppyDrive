<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Notifications\NewSmsReceived;
use Database\Seeders\SmsPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
 * PERF-006 (web push follow-up): a new SMS pushes App\Notifications\NewSmsReceived
 * to every admin who can view the received_sms section — replacing the
 * admin layout's old 15-second poll. SmsAdminNotifier is the single place
 * this decision is made.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    setSmsForwarderToken('token-123');
    $this->seed(SmsPatternSeeder::class);
    Cache::forget('sms_patterns_active');
});

function bkashSmsText(string $trxId, float $amount = 500.00): string
{
    $formattedAmount = number_format($amount, 2, '.', '');

    return "You have received Tk {$formattedAmount} from 01712345678. Fee Tk 0.00. Balance Tk 1000.00. TrxID {$trxId} at 01/11/2026 10:00";
}

function postSms(array $overrides = []): TestResponse
{
    return test()->postJson('/api/sms/receive', array_merge([
        'from' => '01712345678', 'content' => bkashSmsText('TEST123'),
    ], $overrides), ['Authorization' => 'Bearer token-123']);
}

test('a new SMS notifies every admin who can view received SMS, and no one else', function () {
    Notification::fake();
    $canView = createAdmin();
    $cannotView = Admin::create([
        'name' => 'Restricted', 'email' => 'restricted-'.uniqid().'@example.com', 'password' => bcrypt('password'),
    ]);
    $mother = createMotherAdmin();

    postSms()->assertOk();

    Notification::assertSentTo($canView, NewSmsReceived::class);
    Notification::assertSentTo($mother, NewSmsReceived::class);
    Notification::assertNotSentTo($cannotView, NewSmsReceived::class);
});

test('a duplicate SMS delivery does not send a second notification', function () {
    Notification::fake();
    $admin = createAdmin();

    postSms(['content' => bkashSmsText('DUPTEST')])->assertOk();
    postSms(['content' => bkashSmsText('DUPTEST')])->assertOk();

    Notification::assertSentToTimes($admin, NewSmsReceived::class, 1);
});

test('the push payload carries the same fields the old toast showed', function () {
    Notification::fake();
    $admin = createAdmin();

    postSms(['content' => bkashSmsText('PAYLOAD1', 250.50)])->assertOk();

    Notification::assertSentTo($admin, NewSmsReceived::class, function (NewSmsReceived $notification) use ($admin) {
        $payload = $notification->toWebPush($admin);

        return $payload['data']['sender'] === '01712345678'
            && $payload['data']['parsed_transaction_id'] === 'PAYLOAD1'
            && (float) $payload['data']['parsed_amount'] === 250.50;
    });
});
