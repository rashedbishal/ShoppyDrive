<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryType;
use App\Exceptions\InsufficientStockException;
use App\Mail\LowStockAlertMail;
use App\Models\Admin;
use App\Models\FlashSaleItem;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Enforces ordinary stock and flash-sale stock caps at order-creation time.
 *
 * Every public method here must run inside the caller's own order-creation
 * DB::transaction() — the row locks taken here only serialize concurrent
 * checkouts correctly as part of that outer transaction, and a thrown
 * InsufficientStockException is relied upon to roll back the whole order.
 */
class InventoryService
{
    /**
     * Locks and decrements stock for one order line (ordinary stock, plus
     * any currently active flash-sale cap for the same product/variant).
     * Returns the matched FlashSaleItem id, if any, so the caller can
     * persist it on the OrderItem for later restoration.
     *
     * $unitPrice is the price actually being charged for this line — for
     * COD/synchronous paths this was resolved moments ago and will always
     * match; for mobile-banking/ZiniPay it can be minutes-to-hours old
     * (frozen at payment initiation). Flash-cap consumption is only
     * applied when a currently-active flash item's sale_price matches this
     * price, not merely because one currently exists: a flash sale that
     * activates (or changes price) after a price was already frozen and
     * paid must never retroactively get attributed to that paid order —
     * it would consume the campaign's cap for a sale that was never
     * actually charged at the discounted price.
     *
     * @throws InsufficientStockException
     */
    public function decrementForOrderItem(Order $order, int $productId, ?int $variantId, int $quantity, float $unitPrice): ?int
    {
        if ($variantId !== null) {
            $variant = ProductVariant::whereKey($variantId)->lockForUpdate()->first();

            if ($variant === null || $variant->stock_quantity < $quantity) {
                throw InsufficientStockException::forProduct(
                    $variant?->product?->name ?? 'this item',
                    $variant?->stock_quantity ?? 0,
                );
            }

            $before = $variant->stock_quantity;
            $variant->decrement('stock_quantity', $quantity);
            $this->maybeAlertLowStock($variant->product?->name ?? 'Unknown product', $variant->sku, $before, $variant->stock_quantity, $variant->low_stock_threshold);
        } else {
            $product = Product::whereKey($productId)->lockForUpdate()->first();

            if ($product === null) {
                throw InsufficientStockException::forProduct('this item', 0);
            }

            // `Product.stock_quantity` is only a real stock count for
            // variant-less products — for a product that has variants it's
            // a denormalized sum the admin form maintains (see
            // Admin\ProductController::syncVariants()), never itself
            // decremented. An order line with no variant_id for such a
            // product is invalid (the client should have supplied one);
            // falling through to decrement the sum would silently bypass
            // per-variant enforcement instead of actually constraining it.
            if ($product->variants()->exists()) {
                throw InsufficientStockException::forProduct($product->name, 0);
            }

            if ($product->stock_quantity < $quantity) {
                throw InsufficientStockException::forProduct($product->name, $product->stock_quantity);
            }

            $before = $product->stock_quantity;
            $product->decrement('stock_quantity', $quantity);
            $this->maybeAlertLowStock($product->name, $product->sku, $before, $product->stock_quantity, $product->low_stock_threshold);
        }

        InventoryLog::create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'type' => InventoryType::OUT,
            'quantity' => $quantity,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'note' => "Order {$order->order_number} placed.",
        ]);

        return $this->decrementFlashSaleStock($productId, $variantId, $quantity, $unitPrice);
    }

    /**
     * Restores stock (and any flash-sale sold_count) for every item on a
     * cancelled/refunded order. Idempotent and concurrency-safe — callers
     * (the Order status-change model event) run outside any transaction of
     * their own, so this wraps itself in one: a `SELECT ... FOR UPDATE`
     * only holds its lock for the life of a transaction, and without one
     * the stock_restored_at check-then-set below would not be atomic
     * across two concurrent cancel/refund requests for the same order.
     * Nests safely (via a savepoint) if a caller ever does wrap it too.
     *
     * PAY-RETURN-001: restocks only each item's *remaining* quantity
     * (`quantity - restocked_quantity`), not its full original quantity —
     * some or all of it may have already been restored by an earlier
     * return-request approval via `restoreForReturnRequest()`. Each
     * `OrderItem` is freshly re-fetched and locked here (never trusted
     * from the `$order` parameter's possibly-stale, pre-loaded `items`
     * relation), and both this method and `restoreForReturnRequest()`
     * process items in the same ascending-id order, so the two can never
     * deadlock against each other when they race for the same order.
     */
    public function restoreForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->stock_restored_at !== null) {
                return;
            }

            $orderItemIds = $order->items()->orderBy('id')->pluck('id');

            foreach ($orderItemIds as $orderItemId) {
                $item = OrderItem::whereKey($orderItemId)->lockForUpdate()->first();

                if ($item === null) {
                    continue;
                }

                $remaining = max(0, $item->quantity - $item->restocked_quantity);

                if ($remaining <= 0) {
                    continue;
                }

                if ($item->variant_id !== null) {
                    ProductVariant::whereKey($item->variant_id)->lockForUpdate()->increment('stock_quantity', $remaining);
                } else {
                    Product::whereKey($item->product_id)->lockForUpdate()->increment('stock_quantity', $remaining);
                }

                InventoryLog::create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'type' => InventoryType::RELEASE,
                    'quantity' => $remaining,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'note' => "Order {$order->order_number} {$order->status->value} — stock restored.",
                ]);

                if ($item->flash_sale_item_id !== null) {
                    FlashSaleItem::whereKey($item->flash_sale_item_id)->lockForUpdate()->decrement('sold_count', $remaining);
                }

                $item->forceFill(['restocked_quantity' => $item->restocked_quantity + $remaining])->saveQuietly();
            }

            $locked->forceFill(['stock_restored_at' => now()])->saveQuietly();
        });
    }

    /**
     * Restores stock (and any flash-sale sold_count) for exactly the
     * quantities listed on one approved return request — never the
     * item's full order quantity. Called from
     * `Admin\ReturnController::approve()`, inside that action's own
     * transaction (this method's own `DB::transaction()` therefore always
     * runs as a nested savepoint in practice, matching
     * `restoreForOrder()`'s identical self-wrapping convention).
     *
     * PAY-RETURN-001: `$returnRequest->items` is deliberately aggregated
     * by `order_item_id` first — the same defensive concern as
     * `Admin\ReturnController::eligibleRefundAmount()` — so a malformed or
     * duplicated line can never cause the same `OrderItem` to be locked
     * and restocked twice within this one transaction. Each `OrderItem`
     * is freshly re-fetched and locked (never trusted from any pre-loaded
     * relation), processed in ascending `order_item_id` order to match
     * `restoreForOrder()`'s own ordering, and the actual restock amount is
     * capped at `quantity - restocked_quantity` so this can never restock
     * more than genuinely remains outstanding for that item — safe
     * regardless of whether a whole-order cancellation already restocked
     * some or all of it first.
     */
    public function restoreForReturnRequest(ReturnRequest $returnRequest): void
    {
        DB::transaction(function () use ($returnRequest) {
            $quantityByOrderItemId = collect($returnRequest->items)
                ->filter(fn (array $line) => isset($line['order_item_id'], $line['quantity']))
                ->groupBy('order_item_id')
                ->map(fn ($group) => (int) $group->sum('quantity'))
                ->sortKeys();

            foreach ($quantityByOrderItemId as $orderItemId => $requestedQuantity) {
                $item = OrderItem::whereKey($orderItemId)->lockForUpdate()->first();

                if ($item === null || $item->order_id !== $returnRequest->order_id) {
                    continue;
                }

                $remaining = max(0, $item->quantity - $item->restocked_quantity);
                $restockNow = min($requestedQuantity, $remaining);

                if ($restockNow <= 0) {
                    continue;
                }

                if ($item->variant_id !== null) {
                    ProductVariant::whereKey($item->variant_id)->lockForUpdate()->increment('stock_quantity', $restockNow);
                } else {
                    Product::whereKey($item->product_id)->lockForUpdate()->increment('stock_quantity', $restockNow);
                }

                InventoryLog::create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'type' => InventoryType::RELEASE,
                    'quantity' => $restockNow,
                    'reference_type' => ReturnRequest::class,
                    'reference_id' => $returnRequest->id,
                    'note' => "Return request #{$returnRequest->id} (order #{$returnRequest->order_id}) approved — stock restored.",
                ]);

                if ($item->flash_sale_item_id !== null) {
                    FlashSaleItem::whereKey($item->flash_sale_item_id)->lockForUpdate()->decrement('sold_count', $restockNow);
                }

                $item->forceFill(['restocked_quantity' => $item->restocked_quantity + $restockNow])->saveQuietly();
            }
        });
    }

    /**
     * Fires only on the crossing event (stock was above the threshold,
     * now at or below it) — never re-fires on every subsequent sale while
     * already low, and never fires at all if there's no threshold set.
     * Queued + afterCommit() for the same reasons as every other mail
     * dispatched from order creation: never let a mail failure affect
     * the order, and never alert for a decrement that gets rolled back.
     */
    private function maybeAlertLowStock(string $productName, ?string $sku, int $before, int $after, ?int $threshold): void
    {
        if ($threshold === null || $before <= $threshold || $after > $threshold) {
            return;
        }

        $adminEmail = Admin::where('is_mother', true)->value('email');

        if (! $adminEmail) {
            Log::warning('No mother admin configured — skipping low-stock alert.', ['product' => $productName]);

            return;
        }

        Mail::to($adminEmail)->send((new LowStockAlertMail($productName, $sku, $after, $threshold))->afterCommit());
    }

    /**
     * @throws InsufficientStockException
     */
    private function decrementFlashSaleStock(int $productId, ?int $variantId, int $quantity, float $unitPrice): ?int
    {
        // Same lookup CartManager::resolvePrice() uses for pricing — a
        // single source of truth for "which flash sale applies here" so
        // the two can never disagree about it.
        $flashItem = FlashSaleItem::findActiveFor($productId, $variantId);

        if ($flashItem === null || abs((float) $flashItem->sale_price - $unitPrice) > 0.01) {
            return null;
        }

        $locked = FlashSaleItem::whereKey($flashItem->id)->lockForUpdate()->first();

        if ($locked->stock_limit !== null && $locked->sold_count + $quantity > $locked->stock_limit) {
            throw InsufficientStockException::forFlashSaleLimit($locked->product?->name ?? 'this item');
        }

        $locked->increment('sold_count', $quantity);

        return $locked->id;
    }
}
