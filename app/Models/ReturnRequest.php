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

    /**
     * Sum, per order_item_id, how many units have actually been refunded
     * (status REFUNDED only — PENDING/APPROVED/REJECTED never count, even
     * though APPROVED already consumes quantity for eligibility purposes
     * via `committedQuantitiesByOrderItem()` above). This is the basis for
     * both full-return detection and the scheduled reward reduction.
     *
     * PAY-RETURN-001: pass `lockForUpdate: true` when called from a
     * race-sensitive caller (currently only `Admin\ReturnController::
     * markRefunded()`, which locks the parent Order first) — a locking
     * read bypasses this transaction's REPEATABLE READ snapshot and
     * always reads the latest committed sibling rows, regardless of
     * statement ordering, the same reasoning already applied to the
     * referral monthly-cap check in PROMO-LOY-001.
     *
     * @return array<int, int> order_item_id => refunded quantity
     */
    public static function refundedQuantitiesByOrderItem(Order $order, bool $lockForUpdate = false): array
    {
        $query = $order->returnRequests()->where('status', ReturnStatus::REFUNDED);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->flatMap(fn (self $returnRequest) => $returnRequest->items)
            ->groupBy('order_item_id')
            ->map(fn ($group) => (int) $group->sum('quantity'))
            ->all();
    }

    /**
     * Whether every unit of every item on this order has been returned and
     * refunded. Always uses the locking variant of
     * `refundedQuantitiesByOrderItem()` — must only be called with the
     * parent Order already locked by the caller (see that method's
     * docblock).
     */
    public static function isOrderFullyReturned(Order $order): bool
    {
        $refunded = self::refundedQuantitiesByOrderItem($order, lockForUpdate: true);

        return $order->items->every(
            fn (OrderItem $item) => ($refunded[$item->id] ?? 0) >= $item->quantity
        );
    }
}
