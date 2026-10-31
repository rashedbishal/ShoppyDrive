<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\CourierVerificationRefusedException;
use App\Models\Admin;
use App\Models\CourierDeliveryEvidence;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Models\OrderDeliveryVerification;
use App\Support\MotherPasswordGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PAY-RETURN-001: the mother admin's audited verification of exactly one
 * consignment (full_delivery / not_delivered) or one failed courier
 * submission (not_created), made against the courier's own record. Refused
 * without a current readiness attestation, while any partial evidence or
 * unresolved attempt exists, or for anything not exactly linked.
 */
class OrderDeliveryVerificationService
{
    public function __construct(
        private readonly CourierEvidenceChain $chain,
        private readonly CourierVerificationReadinessService $readiness,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws CourierVerificationRefusedException
     */
    public function verify(Admin $admin, Order $order, Request $request, array $input): OrderDeliveryVerification
    {
        if (! $admin->is_mother) {
            abort(403, 'Only the mother admin can verify courier deliveries.');
        }

        if (MotherPasswordGate::isLocked($admin)) {
            throw new CourierVerificationRefusedException(MotherPasswordGate::lockedMessage($admin), 'password');
        }

        if (! MotherPasswordGate::verify($admin, (string) ($input['password'] ?? ''))) {
            throw new CourierVerificationRefusedException('The password is incorrect.', 'password');
        }

        $current = $this->readiness->current();

        if ($current === null) {
            throw new CourierVerificationRefusedException('No current courier verification readiness attestation exists for the deployed release. Complete the readiness checks first.');
        }

        if (! (bool) ($input['timeline_reviewed'] ?? false)) {
            throw new CourierVerificationRefusedException("Confirm that you reviewed the courier's full status timeline.", 'timeline_reviewed');
        }

        $note = trim((string) ($input['note'] ?? ''));

        if (mb_strlen($note) < 10) {
            throw new CourierVerificationRefusedException('Describe what you verified in the courier portal (at least 10 characters).', 'note');
        }

        return DB::transaction(function () use ($admin, $order, $request, $input, $current, $note): OrderDeliveryVerification {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (($partial = $this->chain->partialBlockers($locked)) !== []) {
                throw new CourierVerificationRefusedException(implode(' ', $partial).' Partial deliveries cannot be verified.');
            }

            if (CourierSubmission::where('order_id', $locked->id)->get()->contains(fn (CourierSubmission $s): bool => $s->state->isUnresolved())) {
                throw new CourierVerificationRefusedException('A courier submission for this order is still unresolved — resolve it first.');
            }

            $target = (string) ($input['target_type'] ?? '');
            $provider = (string) ($input['provider'] ?? '');
            $outcome = (string) ($input['outcome'] ?? '');

            if (! array_key_exists($provider, CourierEvidenceChain::COLUMNS)) {
                throw new CourierVerificationRefusedException('Unknown courier.', 'provider');
            }

            [$consignmentColumn, $rawColumn] = CourierEvidenceChain::COLUMNS[$provider];
            $attributes = ['provider' => $provider, 'target_type' => $target, 'outcome' => $outcome];

            if ($target === OrderDeliveryVerification::TARGET_CONSIGNMENT) {
                $consignmentId = trim((string) ($input['consignment_id'] ?? ''));

                if ($locked->{$consignmentColumn} === null || $consignmentId !== $locked->{$consignmentColumn}) {
                    throw new CourierVerificationRefusedException('The consignment ID must exactly match the consignment linked to this order.', 'consignment_id');
                }

                if (! in_array($outcome, [OrderDeliveryVerification::OUTCOME_FULL_DELIVERY, OrderDeliveryVerification::OUTCOME_NOT_DELIVERED], true)) {
                    throw new CourierVerificationRefusedException('A consignment can only be verified as fully delivered or not delivered.', 'outcome');
                }

                $collected = null;

                if ($outcome === OrderDeliveryVerification::OUTCOME_FULL_DELIVERY) {
                    if ($locked->delivered_at === null && $locked->status !== OrderStatus::DELIVERED) {
                        throw new CourierVerificationRefusedException('This order is not recorded as delivered.', 'outcome');
                    }

                    if ($locked->payment_method === PaymentMethod::COD) {
                        $expected = (int) round((float) $locked->total);
                        $entered = RefundCalculator::toPaisa($input['verified_collected_amount'] ?? null);

                        if ($entered !== $expected * 100) {
                            throw new CourierVerificationRefusedException("The courier-collected COD amount must be exactly ৳{$expected} (the amount the courier was asked to collect).", 'verified_collected_amount');
                        }

                        $collected = $expected;
                    }
                }

                $attributes += [
                    'consignment_id' => $consignmentId,
                    'verified_collected_amount' => $collected,
                    'evidence_max_id' => $this->chain->evidenceMaxId($locked, $provider, $consignmentId),
                    'history_max_id' => $this->chain->courierHistoryMaxId($locked, $provider),
                    'raw_status_at_verification' => $locked->{$rawColumn},
                ];
            } elseif ($target === OrderDeliveryVerification::TARGET_SUBMISSION) {
                $submission = CourierSubmission::where('order_id', $locked->id)->whereKey((int) ($input['courier_submission_id'] ?? 0))->first();

                if ($submission === null || $submission->courier !== $provider) {
                    throw new CourierVerificationRefusedException('That courier submission does not belong to this order.', 'courier_submission_id');
                }

                if ($submission->state !== CourierSubmissionState::FAILED || $submission->returned_consignment_id !== null) {
                    throw new CourierVerificationRefusedException('Only a failed submission for which the courier returned no consignment can be verified as not created.', 'courier_submission_id');
                }

                if ($outcome !== OrderDeliveryVerification::OUTCOME_NOT_CREATED) {
                    throw new CourierVerificationRefusedException('A failed submission can only be verified as not created.', 'outcome');
                }

                $attributes += ['courier_submission_id' => $submission->id];
            } else {
                throw new CourierVerificationRefusedException('Choose a consignment or a failed submission to verify.', 'target_type');
            }

            $verification = OrderDeliveryVerification::create([
                ...$attributes,
                'order_id' => $locked->id,
                'timeline_reviewed' => true,
                'submission_state_snapshot' => $this->chain->submissionSnapshot($locked),
                'chain_snapshot' => [
                    'evidence_ids' => CourierDeliveryEvidence::where('order_id', $locked->id)->orderBy('id')->pluck('id')->all(),
                    'gap_ids' => CourierDeliveryEvidence::where('order_id', $locked->id)->where('evidence_type', 'gap')->orderBy('id')->pluck('id')->all(),
                    'raw_statuses' => ['pathao' => $locked->pathao_order_status, 'steadfast' => $locked->steadfast_status],
                ],
                'readiness_id' => $current->id,
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'admin_email' => $admin->email,
                'note' => mb_substr($note, 0, 2000),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);

            activity('orders')
                ->causedBy($admin)
                ->performedOn($locked)
                ->event('delivery.verified')
                ->withProperties(['verification_id' => $verification->id, 'target_type' => $target, 'provider' => $provider, 'outcome' => $outcome])
                ->log("Mother admin verified {$provider} {$target} for order {$locked->order_number}: {$outcome}.");

            return $verification;
        }, 3);
    }
}
