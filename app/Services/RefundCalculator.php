<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Payment;
use App\Models\ReturnRequest;

/**
 * PAY-RETURN-001: the single place refund amounts are calculated, in whole
 * poisha (integer) so cumulative refunds can never drift past what was paid.
 *
 *  - collected K: what the customer actually paid (0 when unpaid; COD uses
 *    the whole-taka amount the courier was asked to collect).
 *  - product net N: item subtotals minus the order discount, the discount
 *    shared across lines by the largest-remainder method.
 *  - cap C = max(0, min(K − shipping − tax, N)); shipping is non-refundable.
 *  - refunded R = sum of the order_refunds ledger; remaining = C − R.
 */
class RefundCalculator
{
    public static function toPaisa(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toTaka(int $paisa): float
    {
        return round($paisa / 100, 2);
    }

    /**
     * Amount collected from the customer, or null when it cannot be
     * established (COD order not delivered by the courier).
     */
    public function collectedPaisa(Order $order): ?int
    {
        if ($order->payment_status !== PaymentStatus::PAID) {
            return 0;
        }

        if ($order->payment_method === PaymentMethod::COD) {
            // The courier is asked for (int) round(total) — see the Pathao /
            // Steadfast payloads — and only a courier-reported delivery marks
            // a COD order paid.
            return $order->delivered_at === null ? null : ((int) round((float) $order->total)) * 100;
        }

        $paid = Payment::where('order_id', $order->id)->where('status', PaymentStatus::PAID->value);

        return $paid->exists() ? self::toPaisa($paid->sum('amount')) : self::toPaisa($order->total);
    }

    /**
     * Net product value per order item (discount allocated by largest remainder).
     * When a selected-items coupon marked the lines it covered, only those
     * lines carry the discount; any other order shares it across every line.
     *
     * @return array<int, int> order_item_id => net poisha
     */
    public function lineNetPaisa(Order $order): array
    {
        $items = OrderItem::where('order_id', $order->id)->orderBy('id')->get(['id', 'subtotal', 'coupon_applied']);
        $gross = $items->mapWithKeys(fn (OrderItem $item): array => [$item->id => max(0, self::toPaisa($item->subtotal))])->all();
        $totalGross = array_sum($gross);

        if ($totalGross === 0) {
            return array_map(fn (): int => 0, $gross);
        }

        $coveredIds = $items->where('coupon_applied', true)->pluck('id')->all();
        $discounted = $coveredIds === [] ? $gross : array_intersect_key($gross, array_flip($coveredIds));
        $discountedGross = array_sum($discounted);

        if ($discountedGross === 0) {
            $discounted = $gross;
            $discountedGross = $totalGross;
        }

        $discount = min(max(0, self::toPaisa($order->discount)), $discountedGross);
        $shares = array_map(fn (): int => 0, $gross);
        $remainders = [];

        foreach ($discounted as $id => $value) {
            $exact = $discount * $value;
            $shares[$id] = intdiv($exact, $discountedGross);
            $remainders[$id] = $exact % $discountedGross;
        }

        $leftover = $discount - array_sum($shares);
        uksort($remainders, fn (int $a, int $b): int => [$remainders[$b], $a] <=> [$remainders[$a], $b]);

        foreach (array_keys($remainders) as $id) {
            if ($leftover <= 0) {
                break;
            }
            $shares[$id]++;
            $leftover--;
        }

        $net = [];

        foreach ($gross as $id => $value) {
            $net[$id] = $value - $shares[$id];
        }

        return $net;
    }

    public function productNetPaisa(Order $order): int
    {
        $lines = $this->lineNetPaisa($order);

        return $lines === []
            ? max(0, self::toPaisa($order->subtotal) - self::toPaisa($order->discount))
            : array_sum($lines);
    }

    /**
     * Refundable cap, or null when the collected amount is unknown.
     */
    public function capPaisa(Order $order): ?int
    {
        $collected = $this->collectedPaisa($order);

        if ($collected === null) {
            return null;
        }

        return max(0, min($collected - self::toPaisa($order->shipping_cost) - self::toPaisa($order->tax), $this->productNetPaisa($order)));
    }

    public function refundedPaisa(Order $order, bool $lock = false): int
    {
        $query = OrderRefund::where('order_id', $order->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return self::toPaisa($query->sum('amount'));
    }

    public function remainingPaisa(Order $order, bool $lock = false): ?int
    {
        $cap = $this->capPaisa($order);

        return $cap === null ? null : max(0, $cap - $this->refundedPaisa($order, $lock));
    }

    /**
     * Net value of q of an item's units, cumulative so successive partial
     * returns sum exactly to the line's net value (never above it).
     */
    public function unitsValuePaisa(int $lineNet, int $quantity, int $units): int
    {
        if ($units <= 0 || $quantity <= 0) {
            return 0;
        }

        return $units >= $quantity ? $lineNet : intdiv($lineNet * $units, $quantity);
    }

    /**
     * Net value of the units on one return request, on top of units already
     * refunded by other return requests of the same order.
     */
    public function returnValuePaisa(Order $order, ReturnRequest $returnRequest, bool $lock = false): int
    {
        $lines = $this->lineNetPaisa($order);
        $items = OrderItem::where('order_id', $order->id)->get(['id', 'quantity'])->keyBy('id');
        $alreadyRefunded = ReturnRequest::refundedQuantitiesByOrderItem($order, lockForUpdate: $lock);

        return (int) collect($returnRequest->items)
            ->filter(fn (array $line): bool => isset($line['order_item_id'], $line['quantity']) && $items->has($line['order_item_id']))
            ->groupBy('order_item_id')
            ->map(fn ($group) => (int) $group->sum('quantity'))
            ->map(function (int $quantity, int $itemId) use ($lines, $items, $alreadyRefunded): int {
                $purchased = (int) $items->get($itemId)->quantity;
                $before = min($purchased, (int) ($alreadyRefunded[$itemId] ?? 0));
                $after = min($purchased, $before + $quantity);

                return $this->unitsValuePaisa($lines[$itemId] ?? 0, $purchased, $after)
                    - $this->unitsValuePaisa($lines[$itemId] ?? 0, $purchased, $before);
            })
            ->sum();
    }

    public function returnMaximumPaisa(Order $order, ReturnRequest $returnRequest, bool $lock = false): ?int
    {
        $remaining = $this->remainingPaisa($order, $lock);

        return $remaining === null ? null : min($this->returnValuePaisa($order, $returnRequest, $lock), $remaining);
    }

    /**
     * A refunded return request with no ledger row means a refund of unknown
     * amount was recorded before the ledger existed — never guess it.
     */
    public function hasUnrecordedRefunds(Order $order): bool
    {
        return ReturnRequest::where('order_id', $order->id)
            ->where('status', ReturnStatus::REFUNDED)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('order_refunds')->whereColumn('order_refunds.return_request_id', 'return_requests.id'))
            ->exists();
    }

    /**
     * @return array{collected: float|null, product_net: float, shipping: float, cap: float|null, refunded: float, remaining: float|null, product_refund_complete: bool, payment_fully_refunded: bool}
     */
    public function summary(Order $order): array
    {
        $collected = $this->collectedPaisa($order);
        $cap = $this->capPaisa($order);
        $refunded = $this->refundedPaisa($order);

        return [
            'collected' => $collected === null ? null : self::toTaka($collected),
            'product_net' => self::toTaka($this->productNetPaisa($order)),
            'shipping' => (float) $order->shipping_cost,
            'cap' => $cap === null ? null : self::toTaka($cap),
            'refunded' => self::toTaka($refunded),
            'remaining' => $cap === null ? null : self::toTaka(max(0, $cap - $refunded)),
            'product_refund_complete' => $cap !== null && $refunded >= $cap && $cap > 0,
            'payment_fully_refunded' => $order->payment_status === PaymentStatus::REFUNDED,
        ];
    }
}
