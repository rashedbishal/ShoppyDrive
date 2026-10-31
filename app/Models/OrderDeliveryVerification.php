<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PAY-RETURN-001: the mother admin's audited verification of one exact
 * consignment (full_delivery / not_delivered) or one failed courier
 * submission (not_created), with the evidence state she reviewed. Written
 * only by App\Services\OrderDeliveryVerificationService.
 */
class OrderDeliveryVerification extends Model
{
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    public const TARGET_CONSIGNMENT = 'consignment';

    public const TARGET_SUBMISSION = 'submission';

    public const OUTCOME_FULL_DELIVERY = 'full_delivery';

    public const OUTCOME_NOT_DELIVERED = 'not_delivered';

    public const OUTCOME_NOT_CREATED = 'not_created';

    protected $fillable = [
        'order_id',
        'target_type',
        'provider',
        'consignment_id',
        'courier_submission_id',
        'outcome',
        'verified_collected_amount',
        'timeline_reviewed',
        'evidence_max_id',
        'history_max_id',
        'raw_status_at_verification',
        'submission_state_snapshot',
        'chain_snapshot',
        'readiness_id',
        'admin_id',
        'admin_name',
        'admin_email',
        'note',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'verified_collected_amount' => 'decimal:2',
            'timeline_reviewed' => 'boolean',
            'submission_state_snapshot' => 'array',
            'chain_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function courierSubmission(): BelongsTo
    {
        return $this->belongsTo(CourierSubmission::class);
    }

    public function readiness(): BelongsTo
    {
        return $this->belongsTo(CourierVerificationReadiness::class, 'readiness_id');
    }
}
