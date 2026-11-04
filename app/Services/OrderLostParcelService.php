<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CourierVerificationRefusedException;
use App\Models\Admin;
use App\Models\Order;
use App\Support\MotherPasswordGate;
use Illuminate\Support\Facades\DB;

/**
 * FSA-11: records a mother-admin's confirmed-lost determination for a
 * parcel. This never changes the order's own status, never issues a
 * refund, and never restores stock itself — it only sets `lost_confirmed_at`,
 * which makes `InventoryService::restoreForOrder()` skip restocking the
 * *next* time this order is cancelled or refunded through the existing,
 * unchanged admin actions (the goods are gone, not returned, so restocking
 * them would be wrong). Confirming a parcel lost requires that existing
 * courier evidence already supports it — not an unrecognized status alone
 * — by reusing `CourierEvidenceChain::eligibility()`'s own existing
 * requirement that every linked consignment already carry a current
 * mother-admin "not delivered" verification.
 */
class OrderLostParcelService
{
    public function __construct(private readonly CourierEvidenceChain $chain) {}

    /**
     * @throws CourierVerificationRefusedException
     */
    public function confirm(Admin $admin, Order $order, string $password, string $note): Order
    {
        if (! $admin->is_mother) {
            abort(403, 'Only the mother admin can confirm a parcel as lost.');
        }

        if (MotherPasswordGate::isLocked($admin)) {
            throw new CourierVerificationRefusedException(MotherPasswordGate::lockedMessage($admin), 'password');
        }

        if (! MotherPasswordGate::verify($admin, $password)) {
            throw new CourierVerificationRefusedException('The password is incorrect.', 'password');
        }

        $trimmedNote = trim($note);

        if (mb_strlen($trimmedNote) < 10) {
            throw new CourierVerificationRefusedException('Describe the courier\'s confirmation of loss (at least 10 characters).', 'note');
        }

        return DB::transaction(function () use ($admin, $order, $trimmedNote): Order {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->lost_confirmed_at !== null) {
                throw new CourierVerificationRefusedException('This order was already confirmed lost.');
            }

            // Not "an unrecognized status" — the same existing eligibility
            // check used before any refund already requires every linked
            // consignment to carry a current "not delivered" verification.
            $eligibility = $this->chain->eligibility($locked);

            if (! $eligibility['courier_involved']) {
                throw new CourierVerificationRefusedException('This order has no courier involvement to confirm as lost.');
            }

            if (! $eligibility['eligible']) {
                throw new CourierVerificationRefusedException('Existing courier evidence does not yet support a lost determination: '.implode(' ', $eligibility['reasons']));
            }

            $locked->forceFill(['lost_confirmed_at' => now()])->save();

            // Deliberately an OrderStatusHistory note only, not a new
            // CourierDeliveryEvidence row — that table's writers are a
            // closed, arch-tested allowlist (CourierOrderStatusMapper,
            // CourierSubmissionService) this service is correctly not
            // part of; the existing history trail already satisfies "how
            // the decision is recorded in order history."
            $locked->statusHistories()->create([
                'status' => $locked->status->value,
                'notes' => "Mother admin {$admin->name} ({$admin->email}) confirmed this parcel as lost. {$trimmedNote} Stock will not be automatically restored when this order is cancelled or refunded.",
            ]);

            return $locked;
        });
    }
}
