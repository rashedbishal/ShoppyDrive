<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CourierSubmissionState;
use Database\Factories\CourierSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to book an order with a courier. Written only through
 * App\Services\CourierSubmissionService, which owns the state machine.
 */
class CourierSubmission extends Model
{
    /** @use HasFactory<CourierSubmissionFactory> */
    use HasFactory;

    public const COURIERS = ['pathao', 'steadfast'];

    public const STALE_AFTER_MINUTES = 5;

    protected $fillable = [
        'order_id',
        'courier',
        'state',
        'active_order_id',
        'source',
        'reference',
        'consignment_id',
        'tracking_code',
        'returned_consignment_id',
        'returned_tracking_code',
        'returned_at',
        'failure_kind',
        'http_status',
        'curl_errno',
        'request_bytes_sent',
        'error_message',
        'claimed_by_admin_id',
        'claimed_at',
        'finished_at',
        'resolution',
        'resolved_by_admin_id',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'state' => CourierSubmissionState::class,
            'claimed_at' => 'datetime',
            'returned_at' => 'datetime',
            'finished_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'claimed_by_admin_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    public function courierLabel(): string
    {
        return $this->courier === 'pathao' ? 'Pathao' : 'Steadfast';
    }
}
