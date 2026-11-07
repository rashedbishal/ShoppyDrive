<?php

use App\Enums\CourierSubmissionState;
use App\Models\Admin;
use App\Models\CourierSubmission;
use App\Models\CourierVerificationReadiness;
use App\Models\Order;
use App\Models\OrderDeliveryVerification;
use App\Models\Setting;
use App\Services\CourierEvidenceChain;
use App\Services\CourierVerificationReadinessService;
use App\Services\RefundCalculator;
use App\Support\AdminSections;
use App\Support\SmsForwarderToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * is_mother is deliberately not mass-assignable (see Admin::$fillable) —
 * tests that need a mother admin must set it via forceFill(), exactly
 * like the one legitimate place in the app that does (CreateMotherAdminCommand).
 *
 * @param  array<string, mixed>  $attributes
 */
function createMotherAdmin(array $attributes = []): Admin
{
    $admin = Admin::create(array_merge([
        'name' => 'Mother Admin',
        'email' => 'mother-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ], $attributes));

    $admin->forceFill(['is_mother' => true])->save();

    return $admin;
}

/**
 * A regular (non-mother) admin with every section fully granted. New admins
 * default to no section access at all (see AdminSections) once the mother
 * can hand-pick permissions, but almost every existing feature test here
 * predates that and just needs "a logged-in admin who can do anything a
 * non-mother admin is allowed to do" — tests that specifically exercise
 * restricted access build their own Admin with a narrower `permissions`
 * array instead of using this helper.
 *
 * @param  array<string, mixed>  $attributes
 */
function createAdmin(array $attributes = []): Admin
{
    $permissions = collect(AdminSections::SECTIONS)
        ->keys()
        ->mapWithKeys(fn (string $section): array => [$section => AdminSections::abilitiesFor($section)])
        ->all();

    return Admin::create(array_merge([
        'name' => 'Admin User',
        'email' => 'admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'permissions' => $permissions,
    ], $attributes));
}

/**
 * PAY-RETURN-001: pin the readiness service's live probes (release
 * fingerprint, deployment marker, OPcache, max_execution_time) so tests
 * control whether an attestation is "current". Bound in the container, so
 * every service resolved afterwards uses it.
 *
 * @param  array<string, mixed>  $probe
 */
function fakeCourierReadiness(array $probe = []): CourierVerificationReadinessService
{
    $fake = new class($probe) extends CourierVerificationReadinessService
    {
        /** @param  array<string, mixed>  $probe */
        public function __construct(public array $probe) {}

        public function liveFingerprint(): array
        {
            return $this->probe['live'] ?? ['fingerprint' => str_repeat('a', 64), 'count' => 1];
        }

        public function deploymentMarker(): array
        {
            return $this->probe['marker'] ?? ['config' => 1_000, 'route' => 1_000];
        }

        public function opcacheProbe(): array
        {
            return $this->probe['opcache'] ?? ['available' => true, 'enabled' => false];
        }

        public function maxExecutionTime(): int
        {
            return $this->probe['max_execution_time'] ?? 30;
        }
    };

    app()->instance(CourierVerificationReadinessService::class, $fake);

    return $fake;
}

/** PAY-RETURN-001: a fake release plus an accepted readiness row matching it. */
function makeCourierReadinessCurrent(): CourierVerificationReadiness
{
    fakeCourierReadiness();

    return CourierVerificationReadiness::factory()->create();
}

/** PAY-RETURN-001: link a consignment the way a successful send does (order column + sent attempt). */
function linkTestConsignment(Order $order, string $provider = 'steadfast', ?string $consignmentId = null): void
{
    $consignmentId ??= 'TEST-CONS-'.$order->id;
    $order->forceFill(["{$provider}_consignment_id" => $consignmentId])->save();

    CourierSubmission::factory()->forOrder($order)->create([
        'courier' => $provider,
        'state' => CourierSubmissionState::SENT,
        'active_order_id' => null,
        'consignment_id' => $consignmentId,
        'finished_at' => now(),
    ]);
}

/**
 * PAY-RETURN-001: a non-stale mother-admin verification of the order's
 * linked consignment, carrying the current snapshot (as the real service does).
 */
function verifyConsignmentForTest(Order $order, string $outcome = OrderDeliveryVerification::OUTCOME_FULL_DELIVERY, string $provider = 'steadfast'): OrderDeliveryVerification
{
    $order->refresh();
    $chain = app(CourierEvidenceChain::class);
    $readiness = app(CourierVerificationReadinessService::class)->current();
    $consignment = $order->{"{$provider}_consignment_id"};

    return OrderDeliveryVerification::factory()->create([
        'order_id' => $order->id,
        'provider' => $provider,
        'consignment_id' => $consignment,
        'outcome' => $outcome,
        'evidence_max_id' => $chain->evidenceMaxId($order, $provider, $consignment),
        'history_max_id' => $chain->courierHistoryMaxId($order, $provider),
        'raw_status_at_verification' => $order->{$provider === 'pathao' ? 'pathao_order_status' : 'steadfast_status'},
        'submission_state_snapshot' => $chain->submissionSnapshot($order),
        'readiness_id' => $readiness->id,
    ]);
}

/** PAY-RETURN-001: everything a delivered, courier-handled order needs before a refund can be recorded. */
function courierVerifiedForRefund(Order $order): OrderDeliveryVerification
{
    if (CourierVerificationReadiness::where('status', CourierVerificationReadiness::STATUS_ACCEPTED)->doesntExist()) {
        makeCourierReadinessCurrent();
    }

    if ($order->refresh()->steadfast_consignment_id === null && $order->pathao_consignment_id === null) {
        linkTestConsignment($order);
    }

    return verifyConsignmentForTest($order);
}

/**
 * PAY-RETURN-001: the payload a whole-order refund now requires — exactly
 * the remaining refundable amount, plus how it was paid back.
 *
 * @return array{refund_amount: float, refund_method: string}
 */
function wholeOrderRefundPayload(Order $order): array
{
    $remaining = app(RefundCalculator::class)->remainingPaisa($order->refresh());

    return ['refund_amount' => RefundCalculator::toTaka((int) $remaining), 'refund_method' => 'bKash'];
}

/**
 * The SMS-forwarder token lives in Setting, not config — this sets or
 * clears it the same way Admin > SMS Forwarder's regenerate action does.
 * Pass null (or omit) to simulate no token configured at all.
 */
function setSmsForwarderToken(?string $token = null): void
{
    if ($token === null) {
        Setting::where('key', SmsForwarderToken::KEY)->delete();

        return;
    }

    Setting::updateOrCreate(['key' => SmsForwarderToken::KEY], ['value' => $token, 'group' => 'sms_forwarder']);
}
