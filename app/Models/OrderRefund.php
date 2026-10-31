<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PAY-RETURN-001: one amount actually paid back to the customer. The sum of
 * an order's rows is the authoritative cumulative refunded amount; written
 * only by App\Services\RefundService (and the backfill migration).
 */
class OrderRefund extends Model
{
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    public const SOURCE_RETURN = 'return';

    public const SOURCE_ORDER = 'order';

    protected $fillable = [
        'order_id',
        'return_request_id',
        'source',
        'amount',
        'method',
        'previous_order_status',
        'resulting_order_status',
        'previous_payment_status',
        'resulting_payment_status',
        'admin_id',
        'admin_name',
        'admin_email',
        'note',
        'is_backfilled',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_backfilled' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}
