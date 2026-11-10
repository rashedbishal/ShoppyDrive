<?php

declare(strict_types=1);

use App\Enums\CourierEvidenceSource;
use App\Enums\CourierEvidenceType;
use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\CourierDeliveryEvidence;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Models\OrderDeliveryVerification;
use App\Models\OrderStatusHistory;
use App\Services\CourierEvidenceChain;
use App\Services\CourierOrderStatusMapper;
use App\Services\CourierSubmissionService;
use App\Services\CourierVerificationReadinessService;
use Illuminate\Support\Facades\DB;

function evidenceOrder(array $overrides = []): Order
{
    return Order::create(array_merge([
        'order_number' => 'ORD-EV-'.strtoupper(bin2hex(random_bytes(4))),
        'status' => OrderStatus::SHIPPED,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => ['name' => 'T', 'phone' => '01700000000'],
        'billing_address' => [],
    ], $overrides));
}

/** A consignment created by this app's own send: consignment column, sent attempt, send anchor. */
function sentOrder(string $provider = 'steadfast'): Order
{
    $order = evidenceOrder(['status' => OrderStatus::PENDING]);
    $submission = CourierSubmission::factory()->forOrder($order)->create(['courier' => $provider]);
    app(CourierSubmissionService::class)->markSent($submission, ['consignment_id' => "SF-{$order->id}", 'tracking_code' => 'T1']);

    return $order->refresh();
}

/**
 * Replica of the production (pre-PAY-RETURN-001, G1) mapper: no row lock, raw
 * update, then a separate history insert — and no evidence.
 */
function staleG1Sync(Order $order, string $provider, string $raw): void
{
    $field = $provider === 'pathao' ? 'pathao_order_status' : 'steadfast_status';
    DB::table('orders')->where('id', $order->id)->update([$field => $raw]);
    OrderStatusHistory::create(['order_id' => $order->id, 'status' => $order->status->value, 'notes' => ucfirst($provider).' reported "'.CourierOrderStatusMapper::label($raw).'".']);
}

/** Replica of pre-mapper (G0) code: raw column only, no history, no evidence. */
function staleG0Write(Order $order, string $provider, string $raw): void
{
    DB::table('orders')->where('id', $order->id)->update([$provider === 'pathao' ? 'pathao_order_status' : 'steadfast_status' => $raw]);
}

test('a courier report writes one evidence row linked to its history note, in the mapper transaction', function () {
    $order = sentOrder();

    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered', source: CourierEvidenceSource::STEADFAST_WEBHOOK);
    $row = CourierDeliveryEvidence::where('evidence_type', CourierEvidenceType::TRANSITION)->sole();

    expect($row->previous_raw_status)->toBeNull()
        ->and($row->raw_status)->toBe('delivered')
        ->and($row->mapped_order_status)->toBe('delivered')
        ->and($row->order_status_before)->toBe('pending')
        ->and($row->order_status_after)->toBe('delivered')
        ->and($row->is_full_delivery)->toBeTrue()
        ->and($row->is_partial)->toBeFalse()
        ->and($row->source)->toBe(CourierEvidenceSource::STEADFAST_WEBHOOK)
        ->and($row->orderStatusHistory->notes)->toStartWith('Steadfast reported "Delivered"');

    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered'); // repeated report
    expect(CourierDeliveryEvidence::where('order_id', $order->id)->count())->toBe(2); // anchor + transition only
});

test('a successful send writes a clean send anchor with the consignment, atomically', function () {
    $order = sentOrder();
    $anchor = CourierDeliveryEvidence::where('evidence_type', CourierEvidenceType::ANCHOR)->sole();

    expect($anchor->anchor_kind)->toBe('send')
        ->and($anchor->anchor_clean)->toBeTrue()
        ->and($anchor->consignment_id)->toBe($order->steadfast_consignment_id)
        ->and($anchor->history_watermark_id)->toBe((int) $order->statusHistories()->max('id'))
        ->and($anchor->source)->toBe(CourierEvidenceSource::COURIER_SEND);
});

test('a reconciliation-linked consignment gets an untrusted reconcile anchor', function () {
    $order = evidenceOrder(['status' => OrderStatus::PENDING]);
    $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create(['courier' => 'pathao', 'returned_consignment_id' => 'PA-1']);

    app(CourierSubmissionService::class)->confirmReturnedConsignment($submission, Admin::create(['name' => 'A', 'email' => 'a@example.test', 'password' => bcrypt('x')]), 'Linked from the returned ID.');

    $anchor = CourierDeliveryEvidence::sole();
    expect($anchor->anchor_kind)->toBe('reconcile')->and($anchor->anchor_clean)->toBeFalse()->and($anchor->source)->toBe(CourierEvidenceSource::COURIER_RECONCILE_RETURNED);
});

test('a raw status changed without evidence is recorded as a raw-mismatch gap on the next sync', function () {
    $order = sentOrder();
    CourierOrderStatusMapper::sync($order, 'steadfast', 'in_review');
    staleG0Write($order, 'steadfast', 'something_else');

    CourierOrderStatusMapper::sync($order->refresh(), 'steadfast', 'something_else'); // unchanged now — gap still recorded

    $gap = CourierDeliveryEvidence::where('evidence_type', CourierEvidenceType::GAP)->sole();
    expect($gap->gap_reason)->toBe('raw_mismatch')->and($gap->previous_raw_status)->toBe('in_review')->and($gap->raw_status)->toBe('something_else');
});

test('a stale G1 writer after deployment leaves an unlinked courier note, recorded as a gap', function () {
    $order = sentOrder();
    staleG1Sync($order, 'steadfast', 'in_review');
    CourierOrderStatusMapper::sync($order->refresh(), 'steadfast', 'delivered');

    expect(CourierDeliveryEvidence::where('gap_reason', 'unlinked_history')->count())->toBe(1)
        ->and(CourierDeliveryEvidence::where('gap_reason', 'raw_mismatch')->count())->toBe(1);
});

test('V2: a stale unmapped partial report then a trusted full delivery is never refundable', function () {
    makeCourierReadinessCurrent();
    $order = sentOrder();
    staleG1Sync($order, 'steadfast', 'partial_delivered'); // unmapped: raw + note, no evidence
    CourierOrderStatusMapper::sync($order->refresh(), 'steadfast', 'delivered'); // trusted full transition

    $chain = app(CourierEvidenceChain::class);
    expect($order->refresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and($chain->partialBlockers($order))->not->toBeEmpty()
        ->and($chain->eligibility($order)['eligible'])->toBeFalse();
});

test('V1: a historical consignment (no anchor) whose partial report was overwritten needs a verification', function () {
    makeCourierReadinessCurrent();
    $order = evidenceOrder(['steadfast_consignment_id' => 'OLD-1']);
    staleG0Write($order, 'steadfast', 'partial_delivered');
    staleG0Write($order, 'steadfast', 'in_review'); // overwritten, no history
    CourierOrderStatusMapper::sync($order->refresh(), 'steadfast', 'delivered');

    $eligibility = app(CourierEvidenceChain::class)->eligibility($order->refresh());
    expect($eligibility['eligible'])->toBeFalse()->and(implode(' ', $eligibility['reasons']))->toContain('needs a current mother-admin verification');
});

test('documented limitation: a G0 round trip A -> partial -> A with no history leaves no trace', function () {
    $order = sentOrder();
    CourierOrderStatusMapper::sync($order, 'steadfast', 'in_review');
    staleG0Write($order, 'steadfast', 'partial_delivered');
    staleG0Write($order, 'steadfast', 'in_review');
    CourierOrderStatusMapper::sync($order->refresh(), 'steadfast', 'in_review');

    // Undetectable by the application — only verification-only mode (courier timeline review) covers it.
    expect(CourierDeliveryEvidence::where('evidence_type', CourierEvidenceType::GAP)->count())->toBe(0)
        ->and(app(CourierEvidenceChain::class)->partialBlockers($order->refresh()))->toBe([]);
});

test('partial evidence from every system source blocks; admin free text never does', function (Closure $arrange, bool $blocks) {
    $order = evidenceOrder(['steadfast_consignment_id' => 'P-1', 'status' => OrderStatus::DELIVERED, 'delivered_at' => now()]);
    $arrange($order);

    expect(app(CourierEvidenceChain::class)->partialBlockers($order->refresh()) !== [])->toBe($blocks);
})->with([
    'partial evidence row' => [fn (Order $o) => CourierOrderStatusMapper::sync($o, 'steadfast', 'partial_delivered'), true],
    'raw column partial' => [fn (Order $o) => staleG0Write($o, 'steadfast', 'partial_delivered'), true],
    'mapper partial note' => [fn (Order $o) => OrderStatusHistory::create(['order_id' => $o->id, 'status' => 'delivered', 'notes' => 'Pathao reported "Partial Delivery" — order status updated to delivered.']), true],
    'legacy webhook partial note' => [fn (Order $o) => OrderStatusHistory::create(['order_id' => $o->id, 'status' => 'shipped', 'notes' => 'Steadfast status updated to "partial_delivered".']), true],
    'admin free text' => [fn (Order $o) => OrderStatusHistory::create(['order_id' => $o->id, 'status' => 'delivered', 'notes' => 'Partial payment received from customer.']), false],
]);

test('every consignment must be verified, and the outcome shape must be exactly one full delivery', function () {
    makeCourierReadinessCurrent();
    $order = evidenceOrder(['status' => OrderStatus::DELIVERED, 'delivered_at' => now(), 'payment_status' => 'paid']);
    linkTestConsignment($order, 'steadfast', 'SF-M1');
    linkTestConsignment($order, 'pathao', 'PA-M1');
    $chain = app(CourierEvidenceChain::class);

    verifyConsignmentForTest($order, 'full_delivery', 'steadfast');
    expect($chain->eligibility($order->refresh())['eligible'])->toBeFalse(); // pathao unverified

    verifyConsignmentForTest($order, 'full_delivery', 'pathao');
    expect($chain->eligibility($order->refresh())['eligible'])->toBeFalse(); // two full deliveries

    verifyConsignmentForTest($order, 'not_delivered', 'pathao');
    expect($chain->eligibility($order->refresh())['eligible'])->toBeTrue();
});

test('submission states fail closed exactly as classified', function (array $submission, string $reason) {
    makeCourierReadinessCurrent();
    $order = evidenceOrder(['status' => OrderStatus::DELIVERED, 'delivered_at' => now(), 'payment_status' => 'paid']);
    linkTestConsignment($order, 'steadfast', 'SF-S1');
    CourierSubmission::factory()->forOrder($order)->create(array_merge(['courier' => 'steadfast', 'active_order_id' => null], $submission));
    verifyConsignmentForTest($order);

    $eligibility = app(CourierEvidenceChain::class)->eligibility($order->refresh());
    expect($eligibility['eligible'])->toBeFalse()->and(implode(' ', $eligibility['reasons']))->toContain($reason);
})->with([
    'submitting' => [['state' => CourierSubmissionState::SUBMITTING], 'still submitting'],
    'unknown' => [['state' => CourierSubmissionState::UNKNOWN], 'still unknown'],
    'sent but not the linked consignment' => [['state' => CourierSubmissionState::SENT, 'consignment_id' => 'OTHER'], 'not the one linked'],
    'returned ID not linked' => [['state' => CourierSubmissionState::FAILED, 'resolution' => 'confirmed_not_created', 'returned_consignment_id' => 'LATE-1'], 'not linked to this order'],
    'failed, not verified' => [['state' => CourierSubmissionState::FAILED], 'not created'],
]);

test('evidence rows are append-only in the application', function () {
    $order = sentOrder();
    $row = CourierDeliveryEvidence::firstOrFail();

    expect(fn () => $row->update(['raw_status' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class)
        ->and(CourierDeliveryEvidence::where('order_id', $order->id)->count())->toBe(1);
});

test('the backfill records partial evidence only from system-written formats inside their windows', function () {
    $migration = require database_path('migrations/2026_10_30_214611_create_courier_delivery_evidence_tables.php');
    $migration->down();

    $raw = evidenceOrder(['steadfast_status' => 'partial_delivered']);
    $notes = evidenceOrder();
    $note = fn (string $text, string $at) => DB::table('order_status_histories')->insert(['order_id' => $notes->id, 'status' => 'shipped', 'notes' => $text, 'created_at' => $at, 'updated_at' => $at]);
    $note('Pathao reported "Partial Delivery" — order status updated to delivered.', '2026-10-20 10:00:00'); // mapper, in window
    $note('Pathao reported "Partial Delivery".', '2026-10-17 10:00:00'); // mapper format before the mapper existed
    $note('Steadfast status updated to "partial_delivered".', '2026-10-18 11:00:00'); // legacy webhook, in window
    $note('Partial payment received.', '2026-10-25 10:00:00'); // admin free text

    $migration->up();

    expect(CourierDeliveryEvidence::where('order_id', $raw->id)->where('evidence_type', CourierEvidenceType::LEGACY_PARTIAL)->count())->toBe(1)
        ->and(CourierDeliveryEvidence::where('order_id', $notes->id)->where('evidence_type', CourierEvidenceType::LEGACY_PARTIAL)->count())->toBe(2);
});

test('the evidence migration refuses to roll back once application evidence exists', function () {
    sentOrder();
    $migration = require database_path('migrations/2026_10_30_214611_create_courier_delivery_evidence_tables.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
});

test('a verification goes stale on new evidence, a new courier note, or a raw-status change without history', function (Closure $change) {
    makeCourierReadinessCurrent();
    $order = sentOrder();
    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered');
    $order->forceFill(['payment_status' => 'paid'])->save();
    $verification = verifyConsignmentForTest($order->refresh());
    $chain = app(CourierEvidenceChain::class);
    expect($chain->staleReason($verification, $order, app(CourierVerificationReadinessService::class)->current()))->toBeNull();

    $change($order);

    expect($chain->staleReason($verification, $order->refresh(), app(CourierVerificationReadinessService::class)->current()))->not->toBeNull()
        ->and($chain->eligibility($order)['eligible'])->toBeFalse();
})->with([
    'new courier report' => [fn (Order $o) => CourierOrderStatusMapper::sync($o, 'steadfast', 'cancelled')],
    'unlogged raw change' => [fn (Order $o) => staleG0Write($o, 'steadfast', 'something_else')],
    'stale G1 courier note' => [fn (Order $o) => staleG1Sync($o, 'steadfast', 'waiting_approval')],
]);

test('a verification made under an older release (fingerprint change) is unusable', function () {
    makeCourierReadinessCurrent();
    $order = evidenceOrder(['status' => OrderStatus::DELIVERED, 'delivered_at' => now(), 'payment_status' => 'paid']);
    linkTestConsignment($order);
    verifyConsignmentForTest($order);
    expect(app(CourierEvidenceChain::class)->eligibility($order->refresh())['eligible'])->toBeTrue();

    fakeCourierReadiness(['live' => ['fingerprint' => str_repeat('c', 64), 'count' => 2]]); // a new deployment

    expect(app(CourierEvidenceChain::class)->eligibility($order)['eligible'])->toBeFalse();
});

test('the same deployment marker change (identical content, caches rebuilt) also outdates readiness', function () {
    makeCourierReadinessCurrent();
    fakeCourierReadiness(['marker' => ['config' => 2_000, 'route' => 2_000]]);

    expect(app(CourierVerificationReadinessService::class)->current())->toBeNull();
});

test('a verification row cannot be edited or deleted', function () {
    makeCourierReadinessCurrent();
    $order = evidenceOrder(['status' => OrderStatus::DELIVERED, 'delivered_at' => now()]);
    linkTestConsignment($order);
    $verification = verifyConsignmentForTest($order);

    expect(fn () => $verification->update(['outcome' => 'not_delivered']))->toThrow(LogicException::class)
        ->and(OrderDeliveryVerification::count())->toBe(1);
});

test('a verification snapshot read back with a different key order (MySQL JSON) is still valid', function () {
    makeCourierReadinessCurrent();
    $order = evidenceOrder(['status' => OrderStatus::DELIVERED, 'delivered_at' => now()]);
    linkTestConsignment($order);
    $verification = verifyConsignmentForTest($order);
    $reordered = array_map(fn (array $s): array => array_reverse($s, true), $verification->submission_state_snapshot);
    DB::table('order_delivery_verifications')->where('id', $verification->id)->update(['submission_state_snapshot' => json_encode($reordered)]);

    expect(app(CourierEvidenceChain::class)->staleReason($verification->fresh(), $order->refresh(), app(CourierVerificationReadinessService::class)->current()))->toBeNull();
});
