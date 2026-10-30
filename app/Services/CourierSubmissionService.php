<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Exceptions\CourierSubmissionBlockedException;
use App\Exceptions\CourierSubmissionException;
use App\Exceptions\CourierSubmissionFinalizationException;
use App\Models\Admin;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * COURIER-SEND-RACE-001: the only code that books an order with a courier
 * or changes a CourierSubmission.
 *
 * - claim() atomically reserves the order for one attempt *before* the
 *   courier is called: the Order row is locked, the order is checked
 *   (sendable status, no consignment yet, no unresolved attempt), and an
 *   attempt row is inserted with `active_order_id` = order id. The unique
 *   index on that column is a database-level backstop — a second unresolved
 *   attempt for the same order, for either courier, can never be inserted.
 *   The transaction commits before the courier call, so no lock is held
 *   while waiting on the network.
 * - The courier call's outcome is then recorded with a conditional
 *   transition under the Order lock. SENT clears the claim in the same
 *   transaction that writes the order's consignment column, so there is no
 *   moment where neither guards the order.
 * - An outcome that may have created a consignment (timeout, dropped
 *   connection, 5xx, odd success payload, crash) becomes UNKNOWN and keeps
 *   the claim: it is never retried automatically, and only an admin
 *   reconciliation (record the consignment, or confirm it was not created)
 *   can release it. A SUBMITTING attempt older than STALE_AFTER_MINUTES is
 *   treated as UNKNOWN — its process may have crashed after sending.
 * - A late outcome never overwrites a reconciliation: transitions only apply
 *   to unresolved, unreconciled attempts; anything else is logged and noted
 *   on the order for a human to check.
 *
 * Lock order everywhere: Order row first, then submission rows — the same
 * Order-first order as the cancellation/refund paths.
 */
class CourierSubmissionService
{
    /** @var list<OrderStatus> */
    private const UNSENDABLE_STATUSES = [OrderStatus::CANCELLED, OrderStatus::REFUNDED, OrderStatus::DELIVERED];

    public function __construct(
        private readonly PathaoCourierService $pathao,
        private readonly SteadfastCourierService $steadfast,
    ) {}

    /**
     * @throws CourierSubmissionBlockedException
     */
    public function claim(Order $order, string $courier, ?Admin $admin): CourierSubmission
    {
        $this->assertKnownCourier($courier);

        // Outside the claim transaction on purpose: a refused claim rolls its
        // transaction back, and the stale attempt must stay marked unknown.
        $this->expireStaleSubmissions($order->id);

        try {
            return DB::transaction(function () use ($order, $courier, $admin): CourierSubmission {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first()
                    ?? throw new CourierSubmissionBlockedException('This order no longer exists.');

                if (in_array($lockedOrder->status, self::UNSENDABLE_STATUSES, true)) {
                    throw new CourierSubmissionBlockedException("A {$lockedOrder->status->value} order can't be sent to a courier.");
                }

                foreach (['pathao' => 'Pathao', 'steadfast' => 'Steadfast'] as $sentCourier => $label) {
                    if ($lockedOrder->{"{$sentCourier}_consignment_id"} !== null) {
                        throw new CourierSubmissionBlockedException("This order has already been sent to {$label}.");
                    }
                }

                $this->assertNoUnresolvedSubmission($lockedOrder->id, 'sent to a courier');

                return CourierSubmission::create([
                    'order_id' => $lockedOrder->id,
                    'courier' => $courier,
                    'state' => CourierSubmissionState::SUBMITTING,
                    'active_order_id' => $lockedOrder->id,
                    'source' => 'app',
                    'reference' => $lockedOrder->order_number,
                    'claimed_by_admin_id' => $admin?->id,
                    'claimed_at' => now(),
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new CourierSubmissionBlockedException($this->unresolvedMessage('sent to a courier'));
        }
    }

    /**
     * Calls the courier for a claimed attempt and records the outcome.
     *
     * @return array<string, mixed> the courier's create-order result
     *
     * @throws CourierSubmissionException when nothing usable came back — check
     *                                    isOutcomeUnknown() for whether the claim was kept
     */
    public function submit(CourierSubmission $submission, Order $order): array
    {
        $service = $submission->courier === 'pathao' ? $this->pathao : $this->steadfast;

        try {
            $result = $service->createOrder($order);
        } catch (CourierSubmissionException $e) {
            $e->isOutcomeUnknown() ? $this->markUnknown($submission, $e) : $this->markFailed($submission, $e);

            throw $e;
        } catch (Throwable $e) {
            // Unexpected: the request may or may not have gone out.
            $unknown = CourierSubmissionException::unknown('unexpected_error', $submission->courierLabel().': '.$e->getMessage(), previous: $e);
            $this->markUnknown($submission, $unknown);
            report($e);

            throw $unknown;
        }

        // COURIER-SEND-RACE-002 (H1): save what the courier returned on the
        // attempt first, in its own write, so a failure while finalizing below
        // can't lose it. It doesn't touch the state or the claim.
        $this->recordReturnedConsignment($submission, $result);

        try {
            $this->markSent($submission, $result);
        } catch (Throwable $e) {
            Log::warning('[Courier] Consignment created but could not be recorded on the order', [
                'submission_id' => $submission->id,
                'order_id' => $order->id,
                'courier' => $submission->courier,
                'consignment_id' => $result['consignment_id'],
                'tracking_code' => $result['tracking_code'] ?? null,
                'exception' => $e::class,
            ]);
            report($e);

            // Make it reconcilable right away instead of after the stale
            // window; if the database is still failing, staleness covers it.
            try {
                $this->markUnknown($submission, CourierSubmissionException::unknown('finalization_failed', $submission->courierLabel()." created consignment {$result['consignment_id']}, but recording it on the order failed: ".$e->getMessage(), previous: $e));
            } catch (Throwable) {
                // Already logged above.
            }

            throw new CourierSubmissionFinalizationException($submission->courier, (string) $result['consignment_id'], $e);
        }

        return $result;
    }

    /**
     * First write wins and only the returned_* columns change, so this can
     * never overwrite a reconciliation or release a claim. Best effort: if
     * the database refuses even this, the warning log carries the ID.
     *
     * @param  array<string, mixed>  $result
     */
    public function recordReturnedConsignment(CourierSubmission $submission, array $result): void
    {
        try {
            CourierSubmission::whereKey($submission->id)
                ->whereNull('returned_consignment_id')
                ->update([
                    'returned_consignment_id' => $result['consignment_id'],
                    'returned_tracking_code' => $result['tracking_code'] ?? null,
                    'returned_at' => now(),
                ]);
        } catch (Throwable $e) {
            Log::warning('[Courier] Could not save the courier-returned consignment ID on the attempt', [
                'submission_id' => $submission->id,
                'courier' => $submission->courier,
                'consignment_id' => $result['consignment_id'],
                'exception' => $e::class,
            ]);
            report($e);
        }
    }

    /**
     * SUBMITTING/UNKNOWN → SENT, unless the attempt was already reconciled
     * (then the late result is reported as an anomaly instead).
     *
     * @param  array<string, mixed>  $result
     */
    public function markSent(CourierSubmission $submission, array $result): bool
    {
        return DB::transaction(function () use ($submission, $result): bool {
            $order = Order::withTrashed()->whereKey($submission->order_id)->lockForUpdate()->firstOrFail();
            $locked = CourierSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $consignmentColumn = "{$locked->courier}_consignment_id";

            if (! $locked->state->isUnresolved() || $locked->resolution !== null || $order->{$consignmentColumn} !== null) {
                $this->reportLateOutcome($order, $locked, "returned consignment {$result['consignment_id']}");

                return false;
            }

            $locked->update([
                'state' => CourierSubmissionState::SENT,
                'active_order_id' => null,
                'consignment_id' => $result['consignment_id'],
                'tracking_code' => $result['tracking_code'] ?? null,
                'finished_at' => now(),
            ]);

            $this->recordConsignmentOnOrder($order, $locked->courier, $result);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $order->status->value,
                'notes' => "Sent to {$locked->courierLabel()} courier. Consignment ID: {$result['consignment_id']}.",
            ]);

            return true;
        }, 3);
    }

    /**
     * SUBMITTING → FAILED (claim released). A definite failure reported for
     * an attempt that is already UNKNOWN or reconciled is recorded as
     * evidence only — it never releases a claim retroactively.
     */
    public function markFailed(CourierSubmission $submission, CourierSubmissionException $e): void
    {
        DB::transaction(function () use ($submission, $e): void {
            Order::withTrashed()->whereKey($submission->order_id)->lockForUpdate()->first();
            $locked = CourierSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->state !== CourierSubmissionState::SUBMITTING || $locked->resolution !== null) {
                $locked->update(['error_message' => $this->appendEvidence($locked, $e)]);

                return;
            }

            $locked->update([
                'state' => CourierSubmissionState::FAILED,
                'active_order_id' => null,
                'finished_at' => now(),
                ...$this->failureDetails($e),
            ]);
        }, 3);
    }

    /**
     * SUBMITTING → UNKNOWN (claim kept). Evidence is appended if the
     * attempt is already UNKNOWN; a reconciled attempt is never touched.
     */
    public function markUnknown(CourierSubmission $submission, CourierSubmissionException $e): void
    {
        DB::transaction(function () use ($submission, $e): void {
            Order::withTrashed()->whereKey($submission->order_id)->lockForUpdate()->first();
            $locked = CourierSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->resolution !== null || ! $locked->state->isUnresolved()) {
                $locked->update(['error_message' => $this->appendEvidence($locked, $e)]);

                return;
            }

            $locked->update([
                'state' => CourierSubmissionState::UNKNOWN,
                'finished_at' => now(),
                ...$this->failureDetails($e),
            ]);
        }, 3);
    }

    /**
     * Any SUBMITTING attempt for the order claimed longer ago than
     * STALE_AFTER_MINUTES becomes UNKNOWN (its process may have crashed
     * after sending). Conditional, so it can't race a late markSent().
     */
    public function expireStaleSubmissions(int $orderId): void
    {
        CourierSubmission::where('active_order_id', $orderId)
            ->where('state', CourierSubmissionState::SUBMITTING)
            ->where('claimed_at', '<', now()->subMinutes(CourierSubmission::STALE_AFTER_MINUTES))
            ->update([
                'state' => CourierSubmissionState::UNKNOWN,
                'failure_kind' => 'stale',
                'error_message' => 'No outcome was recorded within '.CourierSubmission::STALE_AFTER_MINUTES.' minutes — the sending process may have stopped after the request reached the courier.',
                'finished_at' => now(),
            ]);
    }

    /**
     * Must be called inside a transaction that already holds the Order row
     * lock. A plain read on purpose (COURIER-SEND-RACE-002, M1): every write
     * that sets or clears an order's `active_order_id` takes that same Order
     * lock first, so the lock already serializes them, and the read is the
     * transaction's first consistent read — its snapshot is taken after the
     * lock is granted, so it sees every committed claim. A locking read here
     * would add a gap lock on the unique index that two claims for
     * *different* orders can deadlock on. The unique index stays the backstop.
     */
    public function hasUnresolvedSubmission(int $orderId): bool
    {
        return CourierSubmission::where('active_order_id', $orderId)->exists();
    }

    /**
     * Must be called inside a transaction that already holds the Order row lock.
     *
     * @throws CourierSubmissionBlockedException
     */
    public function assertNoUnresolvedSubmission(int $orderId, string $action): void
    {
        if ($this->hasUnresolvedSubmission($orderId)) {
            throw new CourierSubmissionBlockedException($this->unresolvedMessage($action));
        }
    }

    public function unresolvedMessage(string $action): string
    {
        return "This order can't be {$action} while a courier submission is in progress or its outcome is unresolved. Resolve it in the Courier panel first.";
    }

    /**
     * Reconciliation, UNKNOWN → SENT: the admin found the consignment in the
     * courier's portal. Steadfast only — `status_by_cid` proves the
     * consignment exists, the admin's portal check and note prove it belongs
     * to this order. Pathao is disabled until its order-info response is
     * confirmed to identify the order reference.
     *
     * @throws CourierSubmissionBlockedException
     */
    public function recordConsignment(CourierSubmission $submission, Admin $admin, string $consignmentId, ?string $trackingCode, string $note): void
    {
        if ($submission->courier === 'pathao') {
            throw new CourierSubmissionBlockedException("Recording a Pathao consignment ID by hand isn't available yet: Pathao's order lookup hasn't been verified to identify the order. If Pathao returned a consignment ID for this attempt, link that one; if the parcel was not created in the Pathao portal, use \"Confirm not created\".");
        }

        $this->expireStaleSubmissions($submission->order_id);
        $this->assertReconcilable($submission->fresh());

        try {
            $this->steadfast->getOrderInfo($consignmentId);
        } catch (RuntimeException|ConnectionException $e) {
            throw new CourierSubmissionBlockedException("Steadfast could not confirm consignment {$consignmentId}: {$e->getMessage()}");
        }

        try {
            $this->linkManualConsignment($submission, $admin, $consignmentId, $trackingCode, $note);
        } catch (UniqueConstraintViolationException) {
            throw new CourierSubmissionBlockedException("Consignment {$consignmentId} is already recorded on another order.");
        }
    }

    private function linkManualConsignment(CourierSubmission $submission, Admin $admin, string $consignmentId, ?string $trackingCode, string $note): void
    {
        DB::transaction(function () use ($submission, $admin, $consignmentId, $trackingCode, $note): void {
            $order = Order::withTrashed()->whereKey($submission->order_id)->lockForUpdate()->firstOrFail();
            $locked = CourierSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();

            $this->assertReconcilable($locked);

            $usedElsewhere = Order::withTrashed()
                ->where('steadfast_consignment_id', $consignmentId)
                ->whereKeyNot($order->id)
                ->exists();

            if ($usedElsewhere || $order->steadfast_consignment_id !== null) {
                throw new CourierSubmissionBlockedException("Consignment {$consignmentId} is already recorded on an order.");
            }

            $locked->update([
                'state' => CourierSubmissionState::SENT,
                'active_order_id' => null,
                'consignment_id' => $consignmentId,
                'tracking_code' => $trackingCode,
                'resolution' => 'consignment_verified_in_portal',
                'resolved_by_admin_id' => $admin->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            $this->recordConsignmentOnOrder($order, 'steadfast', [
                'consignment_id' => $consignmentId,
                'tracking_code' => $trackingCode,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $order->status->value,
                'notes' => "Steadfast consignment {$consignmentId} recorded by {$admin->name} after verifying it in the Steadfast portal. Note: {$note}",
            ]);
        }, 3);
    }

    /**
     * Reconciliation, UNKNOWN → SENT using the consignment ID the courier
     * itself returned for this exact attempt (COURIER-SEND-RACE-002, H1) —
     * evidence from the courier's own response, so no invoice lookup is
     * needed and it works for Pathao too. Admin-typed IDs still go through
     * recordConsignment().
     *
     * @throws CourierSubmissionBlockedException
     */
    public function confirmReturnedConsignment(CourierSubmission $submission, Admin $admin, string $note): void
    {
        $this->expireStaleSubmissions((int) $submission->order_id);

        try {
            DB::transaction(function () use ($submission, $admin, $note): void {
                $order = Order::withTrashed()->whereKey($submission->order_id)->lockForUpdate()->firstOrFail();
                $locked = CourierSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();

                $this->assertReconcilable($locked);

                if ($locked->returned_consignment_id === null) {
                    throw new CourierSubmissionBlockedException("{$locked->courierLabel()} didn't return a consignment ID for this attempt — check the {$locked->courierLabel()} portal instead.");
                }

                $consignmentColumn = "{$locked->courier}_consignment_id";

                if ($order->{$consignmentColumn} !== null) {
                    throw new CourierSubmissionBlockedException("This order already has a {$locked->courierLabel()} consignment recorded.");
                }

                $locked->update([
                    'state' => CourierSubmissionState::SENT,
                    'active_order_id' => null,
                    'consignment_id' => $locked->returned_consignment_id,
                    'tracking_code' => $locked->returned_tracking_code,
                    'resolution' => 'courier_returned_id_confirmed',
                    'resolved_by_admin_id' => $admin->id,
                    'resolved_at' => now(),
                    'resolution_note' => $note,
                ]);

                $this->recordConsignmentOnOrder($order, $locked->courier, [
                    'consignment_id' => $locked->returned_consignment_id,
                    'tracking_code' => $locked->returned_tracking_code,
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => $order->status->value,
                    'notes' => "{$locked->courierLabel()} consignment {$locked->returned_consignment_id} — returned by {$locked->courierLabel()} for this order's send attempt — linked by {$admin->name}. Note: {$note}",
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new CourierSubmissionBlockedException("Consignment {$submission->fresh()?->returned_consignment_id} is already recorded on another order.");
        }
    }

    /**
     * Reconciliation, UNKNOWN → FAILED (claim released): the admin confirmed
     * in the courier's portal that no consignment was created.
     *
     * @throws CourierSubmissionBlockedException
     */
    public function confirmNotCreated(CourierSubmission $submission, Admin $admin, string $note): void
    {
        $this->expireStaleSubmissions($submission->order_id);

        DB::transaction(function () use ($submission, $admin, $note): void {
            $order = Order::withTrashed()->whereKey($submission->order_id)->lockForUpdate()->firstOrFail();
            $locked = CourierSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();

            $this->assertReconcilable($locked);

            $locked->update([
                'state' => CourierSubmissionState::FAILED,
                'active_order_id' => null,
                'resolution' => 'confirmed_not_created',
                'resolved_by_admin_id' => $admin->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $order->status->value,
                'notes' => "{$admin->name} confirmed in the {$locked->courierLabel()} portal that no consignment was created for {$locked->reference}; the order can be sent again. Note: {$note}",
            ]);
        }, 3);
    }

    private function assertReconcilable(CourierSubmission $submission): void
    {
        if ($submission->state !== CourierSubmissionState::UNKNOWN || $submission->resolution !== null) {
            throw new CourierSubmissionBlockedException($submission->state === CourierSubmissionState::SUBMITTING
                ? 'This submission is still in progress. It can be resolved once it has been unresolved for '.CourierSubmission::STALE_AFTER_MINUTES.' minutes.'
                : 'This submission is no longer awaiting reconciliation.');
        }
    }

    /**
     * Same order-column writes the send actions have always made.
     *
     * @param  array<string, mixed>  $result
     */
    private function recordConsignmentOnOrder(Order $order, string $courier, array $result): void
    {
        if ($courier === 'pathao') {
            $order->update([
                'pathao_consignment_id' => $result['consignment_id'],
                'pathao_delivery_fee' => $result['delivery_fee'] ?? null,
                'pathao_sent_at' => now(),
                'tracking_number' => $order->tracking_number ?? $result['consignment_id'],
            ]);

            return;
        }

        $order->update([
            'steadfast_consignment_id' => $result['consignment_id'],
            'steadfast_tracking_code' => $result['tracking_code'] ?? null,
            'steadfast_sent_at' => now(),
            'tracking_number' => $order->tracking_number ?? ($result['tracking_code'] ?? $result['consignment_id']),
        ]);
    }

    private function reportLateOutcome(Order $order, CourierSubmission $submission, string $outcome): void
    {
        $context = $submission->resolution !== null
            ? "it was already resolved as \"{$submission->resolution}\""
            : "it was already {$submission->state->value}";

        Log::critical('[Courier] Late outcome for an already-settled submission', [
            'submission_id' => $submission->id,
            'order_id' => $order->id,
            'courier' => $submission->courier,
            'outcome' => $outcome,
            'state' => $submission->state->value,
            'resolution' => $submission->resolution,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $order->status->value,
            'notes' => "Attention: {$submission->courierLabel()} {$outcome} for {$submission->reference} after {$context}. Nothing was changed — check the {$submission->courierLabel()} portal for a possible duplicate consignment.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function failureDetails(CourierSubmissionException $e): array
    {
        return [
            'failure_kind' => $e->failureKind,
            'http_status' => $e->httpStatus,
            'curl_errno' => $e->curlErrno,
            'request_bytes_sent' => $e->requestBytesSent,
            'error_message' => mb_substr($e->getMessage(), 0, 2000),
        ];
    }

    private function appendEvidence(CourierSubmission $submission, CourierSubmissionException $e): string
    {
        $entry = '['.now()->toDateTimeString()."] Late outcome ({$e->failureKind}): ".$e->getMessage();

        return mb_substr(trim(($submission->error_message ?? '')."\n".$entry), 0, 4000);
    }

    private function assertKnownCourier(string $courier): void
    {
        if (! in_array($courier, CourierSubmission::COURIERS, true)) {
            throw new CourierSubmissionBlockedException("Unknown courier \"{$courier}\".");
        }
    }
}
