<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Models\CourierDeliveryEvidence;
use App\Models\CourierSubmission;
use App\Models\CourierVerificationReadiness;
use App\Models\Order;
use App\Models\OrderDeliveryVerification;
use App\Models\OrderStatusHistory;

/**
 * PAY-RETURN-001 (verification-only mode): decides whether an order's
 * courier situation allows recording a refund. Read-only — evidence is
 * written only by the mapper / submission service.
 *
 * Fail-closed: an order that ever entered courier processing is refundable
 * only with a current readiness attestation and a valid, non-stale mother
 * admin verification of every consignment / failed submission; any partial
 * evidence, unresolved or unlinked attempt blocks it. Automatic courier
 * eligibility is not enabled.
 */
class CourierEvidenceChain
{
    /** Order statuses that may use the no-courier path (an allowlist — anything else fails closed). */
    public const NO_COURIER_STATUSES = [OrderStatus::PENDING, OrderStatus::PROCESSING];

    /** Mapper partial notes can't be system-written before this; legacy webhook notes before the second. */
    private const MAPPER_EARLIEST = '2026-10-18 12:40:16';

    private const LEGACY_WEBHOOK_EARLIEST = '2026-10-18 10:19:12';

    /** @var array<string, string> provider => [consignment column, raw status column] */
    public const COLUMNS = [
        'pathao' => ['pathao_consignment_id', 'pathao_order_status'],
        'steadfast' => ['steadfast_consignment_id', 'steadfast_status'],
    ];

    public function __construct(private readonly CourierVerificationReadinessService $readiness) {}

    /**
     * True if ANY courier signal exists — an OR over every signal, so a
     * missing consignment ID alone never puts an order on the no-courier path.
     */
    public function isCourierInvolved(Order $order): bool
    {
        if (! in_array($order->status, self::NO_COURIER_STATUSES, true) || $order->shipped_at !== null || $order->delivered_at !== null) {
            return true;
        }

        foreach (self::COLUMNS as [$consignment, $raw]) {
            if ($order->{$consignment} !== null || $order->{$raw} !== null) {
                return true;
            }
        }

        return CourierSubmission::where('order_id', $order->id)->exists()
            || CourierDeliveryEvidence::where('order_id', $order->id)->exists();
    }

    /**
     * Partial-delivery evidence from any system-written source. Text can
     * only ever block, never unlock.
     *
     * @return list<string>
     */
    public function partialBlockers(Order $order): array
    {
        $reasons = [];

        if (CourierDeliveryEvidence::where('order_id', $order->id)->where('is_partial', true)->exists()) {
            $reasons[] = 'Partial-delivery courier evidence exists for this order.';
        }

        foreach (self::COLUMNS as $provider => [, $raw]) {
            if (str_contains(strtolower((string) $order->{$raw}), 'partial')) {
                $reasons[] = ucfirst($provider).' currently reports a partial delivery.';
            }
        }

        $notes = OrderStatusHistory::where('order_id', $order->id)->whereRaw("LOWER(COALESCE(notes, '')) LIKE '%partial%'")->get(['id', 'notes', 'created_at']);

        foreach ($notes as $note) {
            $created = (string) $note->created_at;

            if (($created >= self::MAPPER_EARLIEST && preg_match('/^(Pathao|Steadfast) reported "[^"]*partial[^"]*"/i', (string) $note->notes) === 1)
                || ($created >= self::LEGACY_WEBHOOK_EARLIEST && preg_match('/^Steadfast status updated to "[^"]*partial[^"]*"\./i', (string) $note->notes) === 1)) {
                $reasons[] = 'A courier report of a partial delivery is recorded in the order history.';
                break;
            }
        }

        return $reasons;
    }

    /**
     * Highest id of a courier-format history note for a provider (display
     * text, used only to detect later unlogged activity).
     */
    public function courierHistoryMaxId(Order $order, string $provider): int
    {
        return (int) OrderStatusHistory::where('order_id', $order->id)
            ->where(fn ($q) => $provider === 'pathao'
                ? $q->where('notes', 'like', 'Pathao reported "%')
                : $q->where('notes', 'like', 'Steadfast reported "%')->orWhere('notes', 'like', 'Steadfast status updated to "%'))
            ->max('id');
    }

    public function evidenceMaxId(Order $order, string $provider, ?string $consignmentId): int
    {
        return (int) CourierDeliveryEvidence::where('order_id', $order->id)
            ->where('provider', $provider)
            ->where('consignment_id', $consignmentId)
            ->max('id');
    }

    /**
     * @return list<array{id: int, courier: string, state: string, resolution: string|null, consignment_id: string|null, returned_consignment_id: string|null}>
     */
    public function submissionSnapshot(Order $order): array
    {
        return CourierSubmission::where('order_id', $order->id)->orderBy('id')->get()
            ->map(fn (CourierSubmission $s): array => [
                'id' => $s->id,
                'courier' => $s->courier,
                'state' => $s->state->value,
                'resolution' => $s->resolution,
                'consignment_id' => $s->consignment_id,
                'returned_consignment_id' => $s->returned_consignment_id,
            ])->all();
    }

    /**
     * Recursively key-sorted copy (list order is kept), so a snapshot read
     * back from a JSON column compares equal to a freshly built one.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? self::canonical($item) : $item, $value);
    }

    /**
     * Why a verification no longer covers the current state (null = still valid).
     */
    public function staleReason(OrderDeliveryVerification $verification, Order $order, ?CourierVerificationReadiness $current): ?string
    {
        if ($current === null || $verification->readiness_id !== $current->id) {
            return 'it was made under an outdated readiness attestation (a deployment or cache rebuild happened since)';
        }

        // Compared canonically: MySQL's JSON type re-orders object keys on storage.
        if (self::canonical($this->submissionSnapshot($order)) !== self::canonical($verification->submission_state_snapshot ?? [])) {
            return 'a courier submission changed since';
        }

        if ($verification->target_type === OrderDeliveryVerification::TARGET_SUBMISSION) {
            return null;
        }

        [, $raw] = self::COLUMNS[$verification->provider];

        if ($this->evidenceMaxId($order, $verification->provider, $verification->consignment_id) > $verification->evidence_max_id) {
            return 'new courier evidence arrived since';
        }

        if ($this->courierHistoryMaxId($order, $verification->provider) > $verification->history_max_id) {
            return 'a new courier report was recorded since';
        }

        if ($order->{$raw} !== $verification->raw_status_at_verification) {
            return 'the courier status changed since';
        }

        return null;
    }

    public function latestVerification(Order $order, string $targetType, string $provider, ?string $consignmentId = null, ?int $submissionId = null): ?OrderDeliveryVerification
    {
        return OrderDeliveryVerification::where('order_id', $order->id)
            ->where('target_type', $targetType)
            ->where('provider', $provider)
            ->when($targetType === OrderDeliveryVerification::TARGET_CONSIGNMENT, fn ($q) => $q->where('consignment_id', $consignmentId))
            ->when($targetType === OrderDeliveryVerification::TARGET_SUBMISSION, fn ($q) => $q->where('courier_submission_id', $submissionId))
            ->latest('id')
            ->first();
    }

    /**
     * Read-only summary for the admin order page.
     *
     * @return array<string, mixed>
     */
    public function displaySummary(Order $order): array
    {
        $current = $this->readiness->current();
        $consignments = [];

        foreach (self::COLUMNS as $provider => [$consignmentColumn, $rawColumn]) {
            if ($order->{$consignmentColumn} === null) {
                continue;
            }

            $verification = $this->latestVerification($order, OrderDeliveryVerification::TARGET_CONSIGNMENT, $provider, $order->{$consignmentColumn});
            $anchor = CourierDeliveryEvidence::where('order_id', $order->id)->where('provider', $provider)->where('consignment_id', $order->{$consignmentColumn})->where('evidence_type', 'anchor')->first();

            $consignments[] = [
                'provider' => $provider,
                'consignment_id' => $order->{$consignmentColumn},
                'raw_status' => $order->{$rawColumn},
                'anchor_kind' => $anchor?->anchor_kind,
                'gap_count' => CourierDeliveryEvidence::where('order_id', $order->id)->where('provider', $provider)->where('consignment_id', $order->{$consignmentColumn})->where('evidence_type', 'gap')->count(),
                'verification' => $verification === null ? null : [
                    'outcome' => $verification->outcome,
                    'by' => $verification->admin_name,
                    'at' => $verification->created_at?->toIso8601String(),
                    'stale_reason' => $this->staleReason($verification, $order, $current),
                ],
            ];
        }

        $eligibility = $this->eligibility($order);

        return [
            'courier_involved' => $eligibility['courier_involved'],
            'eligible' => $eligibility['eligible'],
            'reasons' => $eligibility['reasons'],
            'readiness_current' => $current !== null,
            'partial_blockers' => $this->partialBlockers($order),
            'consignments' => $consignments,
            'verifiable_submissions' => CourierSubmission::where('order_id', $order->id)->where('state', CourierSubmissionState::FAILED)->whereNull('returned_consignment_id')->orderBy('id')
                ->get(['id', 'courier', 'resolution'])->map(fn (CourierSubmission $s): array => ['id' => $s->id, 'courier' => $s->courier, 'resolution' => $s->resolution])->all(),
        ];
    }

    /**
     * The courier side of refund eligibility. Call with a row-locked order
     * inside the refund transaction (or unlocked, for display only).
     *
     * @return array{eligible: bool, courier_involved: bool, reasons: list<string>}
     */
    public function eligibility(Order $order): array
    {
        if (! $this->isCourierInvolved($order)) {
            return ['eligible' => true, 'courier_involved' => false, 'reasons' => []];
        }

        $reasons = [];
        $current = $this->readiness->current();

        if ($current === null) {
            $reasons[] = 'No current courier verification readiness attestation exists for the deployed release.';
        }

        $reasons = [...$reasons, ...$this->partialBlockers($order)];
        $linked = [];

        foreach (self::COLUMNS as $provider => [$consignmentColumn, $rawColumn]) {
            if ($order->{$consignmentColumn} !== null) {
                $linked[$provider] = $order->{$consignmentColumn};
            } elseif ($order->{$rawColumn} !== null) {
                $reasons[] = ucfirst($provider).' reported a status but no consignment is linked to the order.';
            }
        }

        foreach (CourierSubmission::where('order_id', $order->id)->orderBy('id')->get() as $submission) {
            $label = ucfirst($submission->courier)." attempt #{$submission->id}";
            $linkedId = $linked[$submission->courier] ?? null;

            if ($submission->state->isUnresolved()) {
                $reasons[] = "{$label} is still {$submission->state->value} — resolve it first.";

                continue;
            }

            if ($submission->returned_consignment_id !== null && $submission->returned_consignment_id !== $linkedId) {
                $reasons[] = "{$label}: the courier returned consignment {$submission->returned_consignment_id}, which is not linked to this order.";

                continue;
            }

            if ($submission->state === CourierSubmissionState::SENT) {
                if ($submission->consignment_id === null || $submission->consignment_id !== $linkedId) {
                    $reasons[] = "{$label} was sent but its consignment is not the one linked to this order.";
                }

                continue; // a sent + linked attempt is covered by its consignment's verification
            }

            // FAILED, no returned ID: the mother admin must confirm "not created".
            $verification = $this->latestVerification($order, OrderDeliveryVerification::TARGET_SUBMISSION, $submission->courier, submissionId: $submission->id);
            $stale = $verification === null ? 'it has not been verified' : $this->staleReason($verification, $order, $current);

            if ($verification === null || $verification->outcome !== OrderDeliveryVerification::OUTCOME_NOT_CREATED || $stale !== null) {
                $reasons[] = "{$label} failed — needs a current \"not created\" verification ({$stale}).";
            }
        }

        $delivered = $order->delivered_at !== null || $order->status === OrderStatus::DELIVERED;
        $shipped = $delivered || $order->shipped_at !== null || $order->status === OrderStatus::SHIPPED;

        if ($linked === [] && $shipped) {
            $reasons[] = 'The order was shipped or delivered without a linked courier consignment, so it cannot be verified.';
        }

        $outcomes = [];

        foreach ($linked as $provider => $consignmentId) {
            $verification = $this->latestVerification($order, OrderDeliveryVerification::TARGET_CONSIGNMENT, $provider, $consignmentId);
            $stale = $verification === null ? 'it has not been verified' : $this->staleReason($verification, $order, $current);

            if ($verification === null || $stale !== null) {
                $reasons[] = ucfirst($provider)." consignment {$consignmentId} needs a current mother-admin verification ({$stale}).";

                continue;
            }

            $outcomes[] = $verification->outcome;
        }

        if (count($outcomes) === count($linked) && $linked !== []) {
            $full = count(array_filter($outcomes, fn (string $o): bool => $o === OrderDeliveryVerification::OUTCOME_FULL_DELIVERY));
            $notDelivered = count(array_filter($outcomes, fn (string $o): bool => $o === OrderDeliveryVerification::OUTCOME_NOT_DELIVERED));

            if ($delivered && ! ($full === 1 && $full + $notDelivered === count($outcomes))) {
                $reasons[] = 'A delivered order needs exactly one consignment verified as fully delivered and every other one verified as not delivered.';
            }

            if (! $delivered && $notDelivered !== count($outcomes)) {
                $reasons[] = 'An undelivered order needs every consignment verified as not delivered.';
            }
        }

        return ['eligible' => $reasons === [], 'courier_involved' => true, 'reasons' => $reasons];
    }
}
