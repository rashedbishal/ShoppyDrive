<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'status',
        'items',
        'admin_note',
        'refund_amount',
        'refund_method',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'items' => 'array',
            'refund_amount' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Statuses that permanently consume returnable quantity against the
     * order item they reference. A rejected request never happened — the
     * goods never actually left the customer — so it's deliberately
     * excluded here, freeing that quantity back up for a new request.
     *
     * @return array<int, ReturnStatus>
     */
    public static function quantityConsumingStatuses(): array
    {
        return [ReturnStatus::PENDING, ReturnStatus::APPROVED, ReturnStatus::REFUNDED];
    }

    /**
     * Sum, per order_item_id, how many units are already spoken for by
     * this order's own non-rejected return requests. Callers that need
     * this to be race-safe against a concurrent submission for the same
     * order must call it after locking the order row in the same
     * transaction (see ReturnRequestController::store()) — this method
     * itself does not lock anything.
     *
     * @return array<int, int> order_item_id => committed quantity
     */
    public static function committedQuantitiesByOrderItem(Order $order): array
    {
        return $order->returnRequests()
            ->whereIn('status', self::quantityConsumingStatuses())
            ->get()
            ->flatMap(fn (self $returnRequest) => $returnRequest->items)
            ->groupBy('order_item_id')
            ->map(fn ($group) => (int) $group->sum('quantity'))
            ->all();
    }
}
