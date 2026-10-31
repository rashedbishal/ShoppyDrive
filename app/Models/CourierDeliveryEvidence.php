<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\AppendOnly;
use App\Enums\CourierEvidenceSource;
use App\Enums\CourierEvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PAY-RETURN-001: one structured piece of courier evidence for an order.
 * Written only by App\Services\CourierOrderStatusMapper, the anchor writes
 * in App\Services\CourierSubmissionService and the backfill migration
 * (enforced by tests/Unit/CourierDeliveryEvidenceArchTest). The display
 * text in order_status_histories is never refund evidence.
 */
class CourierDeliveryEvidence extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    public const CODE_GENERATION = 1;

    protected $table = 'courier_delivery_evidence';

    protected $fillable = [
        'order_id',
        'provider',
        'consignment_id',
        'courier_submission_id',
        'order_status_history_id',
        'evidence_type',
        'anchor_kind',
        'source',
        'code_generation',
        'previous_raw_status',
        'raw_status',
        'normalized_status',
        'mapped_order_status',
        'order_status_before',
        'order_status_after',
        'is_partial',
        'is_full_delivery',
        'anchor_clean',
        'history_watermark_id',
        'gap_reason',
        'anchor_key',
        'gap_key',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_type' => CourierEvidenceType::class,
            'source' => CourierEvidenceSource::class,
            'is_partial' => 'boolean',
            'is_full_delivery' => 'boolean',
            'anchor_clean' => 'boolean',
            'received_at' => 'datetime',
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

    public function orderStatusHistory(): BelongsTo
    {
        return $this->belongsTo(OrderStatusHistory::class);
    }
}
