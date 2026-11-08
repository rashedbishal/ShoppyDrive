<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Exceptions\ReturnQuantityExceededException;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The customer self-service return-request submission rule —
 * ReturnRequestController::store() and the AI assistant's
 * submit_return_request tool both call this, so eligibility and the
 * race-safe remaining-quantity check are enforced exactly once. Does not
 * touch approval, rejection or refund (Admin\ReturnController,
 * RefundService) — those are mother/admin-only and out of scope here.
 */
class ReturnRequestService
{
    /**
     * How long after delivery a customer may still request a return — the
     * same 7-day window already published to customers on the product page
     * (see catalog/ProductDetail.tsx's "Return Policy" copy). Centralized
     * here so the enforced window can never drift from what's advertised.
     */
    public const ELIGIBILITY_WINDOW_DAYS = 7;

    public function eligibilityError(Order $order): ?string
    {
        if ($order->status !== OrderStatus::DELIVERED || $order->delivered_at === null) {
            return 'Only delivered orders are eligible for a return.';
        }

        if ($order->delivered_at->addDays(self::ELIGIBILITY_WINDOW_DAYS)->isPast()) {
            return 'The '.self::ELIGIBILITY_WINDOW_DAYS.'-day return window for this order has passed.';
        }

        return null;
    }

    /**
     * @param  array<int, array{order_item_id:int, quantity:int, reason?:string|null}>  $items
     *
     * @throws ReturnQuantityExceededException
     */
    public function submit(Order $order, User $user, string $reason, array $items): void
    {
        DB::transaction(function () use ($order, $user, $reason, $items): void {
            // Lock the order row so two concurrent return submissions
            // against it can never both read the same "remaining
            // quantity" for an item and both succeed in over-returning
            // it. Matches the same lock-the-order pattern already used
            // by InventoryService/CouponService for this exact reason.
            // Committed quantities are only ever computed after this.
            Order::whereKey($order->id)->lockForUpdate()->first();

            $itemsById = $order->items()->get()->keyBy('id');
            $committed = ReturnRequest::committedQuantitiesByOrderItem($order);

            // Aggregates requested quantity per item regardless of how many
            // times the same order_item_id appears, so the remaining-quantity
            // check can never be bypassed by duplicate lines.
            $requestedByItem = collect($items)
                ->groupBy('order_item_id')
                ->map(fn ($group) => collect($group)->sum('quantity'));

            foreach ($requestedByItem as $orderItemId => $requestedQuantity) {
                $orderItem = $itemsById->get($orderItemId);

                if ($orderItem === null) {
                    throw ReturnQuantityExceededException::forOrderItem('this item', 0);
                }

                $remaining = $orderItem->quantity - ($committed[$orderItemId] ?? 0);

                if ($requestedQuantity > $remaining) {
                    throw ReturnQuantityExceededException::forOrderItem($orderItem->product_name, $remaining);
                }
            }

            ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'status' => ReturnStatus::PENDING,
                'items' => array_map(fn (array $item): array => [
                    'order_item_id' => (int) $item['order_item_id'],
                    'product_id' => $itemsById->get($item['order_item_id'])->product_id,
                    'quantity' => (int) $item['quantity'],
                    'reason' => $item['reason'] ?? null,
                ], $items),
            ]);
        });
    }
}
