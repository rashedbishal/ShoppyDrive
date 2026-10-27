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
use App\Models\Product;
use App\Models\ProductVariant;
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
     */
    public function restoreForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->stock_restored_at !== null) {
                return;
            }

            foreach ($order->items as $item) {
                if ($item->variant_id !== null) {
                    ProductVariant::whereKey($item->variant_id)->lockForUpdate()->increment('stock_quantity', $item->quantity);
                } else {
                    Product::whereKey($item->product_id)->lockForUpdate()->increment('stock_quantity', $item->quantity);
                }

                InventoryLog::create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'type' => InventoryType::RELEASE,
                    'quantity' => $item->quantity,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'note' => "Order {$order->order_number} {$order->status->value} — stock restored.",
                ]);

                if ($item->flash_sale_item_id !== null) {
                    FlashSaleItem::whereKey($item->flash_sale_item_id)->lockForUpdate()->decrement('sold_count', $item->quantity);
                }
            }

            $locked->forceFill(['stock_restored_at' => now()])->saveQuietly();
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
