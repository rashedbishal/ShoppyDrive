<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\SmsPattern;
use App\Models\User;
use App\Services\SmsParserService;
use App\Support\SmsRegex;
use Database\Seeders\SmsPatternSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

const BKASH_SMS = 'You have received Tk 100.00 from 01867603414. Fee Tk 0.00. Balance Tk 500.00. TrxID DED66E1DFW at 01/11/2026 10:00';
const NAGAD_SMS = "Money Received.\nAmount: Tk 100.00\nSender: 01867603414\nTxnID: 75AIB4WJ\nBalance: Tk 900.00";

beforeEach(function () {
    config(['services.sms_forwarder.token' => 'phone-token']);
    $this->seed(SmsPatternSeeder::class);
    Cache::forget('sms_patterns_active');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function patternPayload(array $overrides = []): array
{
    return array_merge([
        'provider' => 'bkash',
        'name' => 'New Pattern',
        'identifier' => 'NewMarker',
        'txn_id_regex' => 'TrxID\s+([A-Z0-9]+)',
        'amount_regex' => 'Tk\s+([\d,]+(?:\.\d+)?)',
        'sort_order' => 5,
    ], $overrides);
}

/**
 * Stored the way a pattern saved before this validation existed would be.
 *
 * @param  array<string, mixed>  $overrides
 */
function legacyPattern(array $overrides): SmsPattern
{
    SmsPattern::query()->delete();

    return SmsPattern::create(array_merge(patternPayload(['identifier' => 'TrxID', 'is_active' => true, 'sort_order' => 0]), $overrides));
}

function forwardSms(string $content): TestResponse
{
    return test()->withToken('phone-token')->postJson('/api/sms/receive', ['from' => 'bKash', 'content' => $content]);
}

function pendingMobileCheckout(User $user, float $price = 100): PendingCheckout
{
    $product = Product::factory()->create(['price' => $price, 'stock_quantity' => 10, 'free_delivery' => true]);
    Cart::query()->where('user_id', $user->id)->delete();

    actingAs($user)->post(route('checkout.store'), [
        'name' => 'Sms Buyer', 'email' => $user->email, 'phone' => '01700000000', 'address_line1' => 'House 1',
        'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh', 'payment_method' => 'mobile_banking',
        'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ])->assertRedirect();

    return PendingCheckout::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

function submitTransaction(User $user, PendingCheckout $pending, string $provider, string $transactionId): TestResponse
{
    return actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => $provider, 'transaction_id' => $transactionId]);
}

// ── Saving patterns ──────────────────────────────────────────────────────────

test('every seeded pattern passes validation', function () {
    foreach (SmsPattern::all() as $pattern) {
        expect(SmsRegex::problem($pattern->txn_id_regex))->toBeNull();
        expect(SmsRegex::problem($pattern->amount_regex))->toBeNull();
    }
});

test('a valid pattern can be created and updated, and is audited with its actor', function () {
    $admin = createAdmin(['name' => 'Pattern Admin', 'email' => 'pattern@example.com', 'password' => bcrypt('password')]);
    actingAs($admin, 'admin');

    $this->post(route('admin.sms-patterns.store'), patternPayload())->assertSessionHasNoErrors();

    $pattern = SmsPattern::where('identifier', 'NewMarker')->firstOrFail();
    $created = Activity::query()->where('subject_type', SmsPattern::class)->where('event', 'created')->where('subject_id', $pattern->id)->firstOrFail();

    expect($created->causer->is($admin))->toBeTrue();
    expect($created->properties['attributes']['txn_id_regex'])->toBe('TrxID\s+([A-Z0-9]+)');

    $this->patch(route('admin.sms-patterns.update', $pattern), patternPayload(['txn_id_regex' => 'Ref\s+([A-Z0-9]+)', 'is_active' => true]))->assertSessionHasNoErrors();

    $updated = Activity::query()->where('subject_type', SmsPattern::class)->where('event', 'updated')->where('subject_id', $pattern->id)->firstOrFail();

    expect($updated->causer->is($admin))->toBeTrue();
    expect($updated->properties['old']['txn_id_regex'])->toBe('TrxID\s+([A-Z0-9]+)');
    expect($updated->properties['attributes']['txn_id_regex'])->toBe('Ref\s+([A-Z0-9]+)');
});

// ── Sender allowlist (FSA-02) ────────────────────────────────────────────────

test('a blank sender_regex is saved as null, leaving the pattern unrestricted', function () {
    actingAs(createAdmin(['name' => 'Pattern Admin', 'email' => 'pattern@example.com', 'password' => bcrypt('password')]), 'admin');

    $this->post(route('admin.sms-patterns.store'), patternPayload())->assertSessionHasNoErrors();

    expect(SmsPattern::where('identifier', 'NewMarker')->firstOrFail()->sender_regex)->toBeNull();
});

test('an unsafe sender_regex is rejected when creating and updating', function (string $regex) {
    actingAs(createAdmin(['name' => 'Pattern Admin', 'email' => 'pattern@example.com', 'password' => bcrypt('password')]), 'admin');
    $pattern = SmsPattern::firstOrFail();
    $original = $pattern->sender_regex;

    $this->post(route('admin.sms-patterns.store'), patternPayload(['sender_regex' => $regex]))->assertSessionHasErrors('sender_regex');
    $this->patch(route('admin.sms-patterns.update', $pattern), patternPayload(['sender_regex' => $regex, 'is_active' => true]))->assertSessionHasErrors('sender_regex');

    expect(SmsPattern::where('identifier', 'NewMarker')->exists())->toBeFalse();
    expect($pattern->refresh()->sender_regex)->toBe($original);
})->with([
    'unclosed group' => ['(unclosed'],
    'no capture group' => ['bKash'],
    'catastrophic nesting' => ['(a+)+$'],
]);

test('deleting patterns is audited and takes effect at once', function (string $how) {
    actingAs(createAdmin(['name' => 'Pattern Admin', 'email' => 'pattern@example.com', 'password' => bcrypt('password')]), 'admin');
    $pattern = SmsPattern::where('provider', 'bkash')->firstOrFail();

    expect(app(SmsParserService::class)->parse(BKASH_SMS)['provider'])->toBe('bkash');

    $how === 'single'
        ? $this->delete(route('admin.sms-patterns.destroy', $pattern))
        : $this->delete(route('admin.sms-patterns.destroyMany'), ['ids' => [$pattern->id]]);

    $deleted = Activity::query()->where('subject_type', SmsPattern::class)->where('event', 'deleted')->where('subject_id', $pattern->id)->firstOrFail();

    expect($deleted->properties['old']['amount_regex'])->toBe($pattern->amount_regex);
    expect(app(SmsParserService::class)->parse(BKASH_SMS)['provider'])->toBe('unknown');
})->with(['single', 'bulk']);

test('an unsafe regex is rejected when creating and updating', function (string $regex) {
    actingAs(createAdmin(['name' => 'Pattern Admin', 'email' => 'pattern@example.com', 'password' => bcrypt('password')]), 'admin');
    $pattern = SmsPattern::firstOrFail();
    $original = $pattern->txn_id_regex;

    $this->post(route('admin.sms-patterns.store'), patternPayload(['txn_id_regex' => $regex]))->assertSessionHasErrors('txn_id_regex');
    $this->patch(route('admin.sms-patterns.update', $pattern), patternPayload(['amount_regex' => $regex, 'is_active' => true]))->assertSessionHasErrors('amount_regex');

    expect(SmsPattern::where('identifier', 'NewMarker')->exists())->toBeFalse();
    expect($pattern->refresh()->txn_id_regex)->toBe($original);
})->with([
    'unclosed group' => ['(unclosed'],
    'unclosed class' => ['[A-Z'],
    'unescaped delimiter' => ['Trx/ID ([A-Z]+)'],
    'injected modifier' => ['([A-Z]+)/e'],
    'no capture group' => ['TrxID\s+[A-Z0-9]+'],
    'catastrophic nesting' => ['(a+)+$'],
    'catastrophic alternation' => ['(a|a)*$'],
    'hidden by a required literal' => ['(\d+)+x'],
]);

test('a regex too long for its column is rejected rather than failing on save', function () {
    actingAs(createAdmin(['name' => 'Pattern Admin', 'email' => 'pattern@example.com', 'password' => bcrypt('password')]), 'admin');

    $this->post(route('admin.sms-patterns.store'), patternPayload(['txn_id_regex' => '('.str_repeat('a', 300).')']))->assertSessionHasErrors('txn_id_regex');
});

// ── Parsing ──────────────────────────────────────────────────────────────────

test('real bKash and Nagad messages still parse', function (string $sms, string $provider, string $transactionId) {
    expect(app(SmsParserService::class)->parse($sms))->toEqual(['provider' => $provider, 'transaction_id' => $transactionId, 'amount' => 100.0]);
})->with([
    'bKash' => [BKASH_SMS, 'bkash', 'DED66E1DFW'],
    'Nagad' => [NAGAD_SMS, 'nagad', '75AIB4WJ'],
]);

test('a sender matching the pattern allowlist is accepted', function () {
    SmsPattern::where('provider', 'bkash')->update(['sender_regex' => '(bKash)']);
    Cache::forget('sms_patterns_active');

    expect(app(SmsParserService::class)->parse(BKASH_SMS, 'bKash'))
        ->toEqual(['provider' => 'bkash', 'transaction_id' => 'DED66E1DFW', 'amount' => 100.0]);
});

test('a sender not matching the pattern allowlist is rejected, with no fallback to body-only matching', function () {
    SmsPattern::where('provider', 'bkash')->update(['sender_regex' => '(bKash)']);
    Cache::forget('sms_patterns_active');

    expect(app(SmsParserService::class)->parse(BKASH_SMS, '01700000000'))
        ->toEqual(['provider' => 'unknown', 'transaction_id' => null, 'amount' => null]);
});

test('a legacy unsafe pattern parses nothing, and logs without the SMS', function (array $regexes) {
    legacyPattern($regexes);
    $logged = [];
    Log::shouldReceive('warning')->atLeast()->once()->andReturnUsing(function (string $message, array $context) use (&$logged): void {
        $logged[] = $message.json_encode($context);
    });

    $parsed = app(SmsParserService::class)->parse('TrxID '.str_repeat('a', 1500).'! DED66E1DFW 01867603414 Tk 100');

    expect($parsed['transaction_id'])->toBeNull();

    $text = implode(' ', $logged);

    expect($text)->not->toContain('DED66E1DFW')->not->toContain('01867603414')->not->toContain('TrxID');
})->with([
    'invalid syntax' => [['txn_id_regex' => '(unclosed', 'amount_regex' => '(unclosed']],
    'catastrophic' => [['txn_id_regex' => '(a+)+$', 'amount_regex' => '(a+)+$']],
]);

test('a pathological regex is stopped quickly and leaves the limit as it was', function () {
    $before = ini_get('pcre.backtrack_limit');
    $started = microtime(true);

    SmsRegex::firstCapture('(a+)+$', str_repeat('a', SmsRegex::MAX_CONTENT_LENGTH).'!', $failure);

    expect($failure)->not->toBeNull();
    expect(microtime(true) - $started)->toBeLessThan(0.5);
    expect(ini_get('pcre.backtrack_limit'))->toBe($before);
});

test('oversized content is ignored without storing or parsing it', function () {
    Log::spy();

    $oversized = str_repeat('x', SmsRegex::MAX_CONTENT_LENGTH + 1).' TrxID DED66E1DFW received Tk 100';

    forwardSms($oversized)->assertOk()->assertJson(['status' => 'ignored']);

    expect(ReceivedSms::query()->count())->toBe(0);
    expect(app(SmsParserService::class)->parse(str_repeat('x', SmsRegex::MAX_CONTENT_LENGTH + 1).' TrxID ABC')['provider'])->toBe('unknown');
    Log::shouldHaveReceived('warning')->once()->with('[SMS] Oversized message ignored', ['length' => strlen($oversized)]);
});

test('content at the limit is still processed', function () {
    $padded = str_pad(BKASH_SMS, SmsRegex::MAX_CONTENT_LENGTH);

    forwardSms($padded)->assertOk()->assertJson(['status' => 'ok']);

    expect(ReceivedSms::query()->firstOrFail()->parsed_transaction_id)->toBe('DED66E1DFW');
});

// ── Payment safety, through the real forwarder and payment endpoints ─────────

test('a matching payment is accepted for each provider', function (string $sms, string $provider, string $transactionId) {
    $user = User::factory()->create();
    $pending = pendingMobileCheckout($user);

    forwardSms($sms)->assertOk();
    submitTransaction($user, $pending, $provider, $transactionId)->assertRedirect();

    expect(Order::query()->firstOrFail()->payment_status->value)->toBe('paid');
})->with([
    'bKash' => [BKASH_SMS, 'bkash', 'DED66E1DFW'],
    'Nagad' => [NAGAD_SMS, 'nagad', '75AIB4WJ'],
]);

test('once a sender allowlist is configured, a non-allowlisted sender never pays an order', function () {
    SmsPattern::where('provider', 'bkash')->update(['sender_regex' => '(bKash)']);
    Cache::forget('sms_patterns_active');

    $user = User::factory()->create();
    $pending = pendingMobileCheckout($user);

    test()->withToken('phone-token')->postJson('/api/sms/receive', ['from' => '01700000000', 'content' => BKASH_SMS])->assertOk();

    expect(ReceivedSms::query()->firstOrFail()->parsed_transaction_id)->toBeNull();

    submitTransaction($user, $pending, 'bkash', 'DED66E1DFW')->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
});

test('a legacy pattern that cannot run never pays an order', function (array $regexes) {
    legacyPattern($regexes);
    $user = User::factory()->create();
    $pending = pendingMobileCheckout($user);

    forwardSms(BKASH_SMS)->assertOk();
    submitTransaction($user, $pending, 'bkash', 'DED66E1DFW')->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
    expect($pending->fresh()->order_number)->toBeNull();
})->with([
    'broken transaction regex' => [['txn_id_regex' => '(unclosed']],
    'broken amount regex' => [['amount_regex' => '(unclosed']],
    'both broken' => [['txn_id_regex' => '(unclosed', 'amount_regex' => '(unclosed']],
]);

test('a wrong amount, transaction or provider never pays an order', function (string $provider, string $transactionId, string $price) {
    $user = User::factory()->create();
    $pending = pendingMobileCheckout($user, (float) $price);

    forwardSms(BKASH_SMS)->assertOk();
    submitTransaction($user, $pending, $provider, $transactionId)->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
})->with([
    'wrong amount' => ['bkash', 'DED66E1DFW', '150'],
    'wrong transaction' => ['bkash', 'NOTREAL123', '100'],
    'wrong provider' => ['nagad', 'DED66E1DFW', '100'],
]);

test('the sender number is not part of verification', function () {
    $user = User::factory()->create(['phone' => '01999999999']);
    $pending = pendingMobileCheckout($user);

    forwardSms(BKASH_SMS)->assertOk();
    submitTransaction($user, $pending, 'bkash', 'DED66E1DFW')->assertRedirect();

    expect(Order::query()->count())->toBe(1);
});

test('a duplicate SMS keeps one record and pays one order', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $firstPending = pendingMobileCheckout($first);
    $secondPending = pendingMobileCheckout($second);

    forwardSms(BKASH_SMS)->assertOk();
    forwardSms(BKASH_SMS)->assertOk()->assertJson(['status' => 'ok']);

    expect(ReceivedSms::query()->count())->toBe(1);

    submitTransaction($first, $firstPending, 'bkash', 'DED66E1DFW')->assertRedirect();
    submitTransaction($second, $secondPending, 'bkash', 'DED66E1DFW')->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(1);
});
