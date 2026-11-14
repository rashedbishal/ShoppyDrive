<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryType;
use App\Exceptions\StockAdjustmentException;
use App\Models\Admin;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Manual stock management (owner decisions 2026-11-14), shared by Admin ›
 * Stock Management and the product edit form, so a manual change is recorded
 * the same way wherever it's made: an `InventoryLog` ADJUSTMENT row, one Audit
 * Trail entry ("stock" log), and the low-stock alert when a change crosses the
 * threshold. Automatic changes from orders, cancellations and returns stay in
 * InventoryService and are not written here.
 */
final class StockAdjustmentService
{
    public const SOURCE_STOCK_PAGE = 'stock_management';

    public const SOURCE_PRODUCT_EDIT = 'product_edit';

    public const SOURCE_PRODUCT_CREATE = 'product_create';

    /** Matches ProductRequest's own stock maximum. */
    public const MAX_STOCK = 9999999;

    private const SOURCE_LABELS = [
        self::SOURCE_STOCK_PAGE => 'Stock Management',
        self::SOURCE_PRODUCT_EDIT => 'product edit form',
        self::SOURCE_PRODUCT_CREATE => 'new product form',
    ];

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Adds (positive $delta) or removes (negative) stock relative to the
     * current value, under row locks, so it can't overwrite a concurrent order
     * or cancellation. Locks the product, then the variant: the same order the
     * product edit form and order creation use.
     *
     * @return array{before: int, after: int}
     *
     * @throws StockAdjustmentException
     */
    public function adjust(Admin $admin, int $productId, ?int $variantId, int $delta, ?string $reason): array
    {
        return DB::transaction(function () use ($admin, $productId, $variantId, $delta, $reason): array {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            $variant = $variantId === null ? null : ProductVariant::query()
                ->whereKey($variantId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($variant === null && $product->variants()->exists()) {
                throw StockAdjustmentException::variantRequired();
            }

            $before = (int) ($variant ?? $product)->stock_quantity;
            $after = $before + $delta;

            if ($after < 0) {
                throw StockAdjustmentException::belowZero($before);
            }

            if ($after > self::MAX_STOCK) {
                throw StockAdjustmentException::aboveMaximum(self::MAX_STOCK);
            }

            // Query-builder writes, like InventoryService: stock changes fire
            // no model events, and the Audit Trail entry is written explicitly
            // in record() instead.
            if ($variant !== null) {
                ProductVariant::query()->whereKey($variant->id)->update(['stock_quantity' => $after]);
                $this->resumProductStock($product->id);
            } else {
                Product::query()->whereKey($product->id)->update(['stock_quantity' => $after]);
            }

            $this->record($admin, $product, $variant, $before, $after, $reason, self::SOURCE_STOCK_PAGE);

            return ['before' => $before, 'after' => $after];
        });
    }

    /**
     * Records one manual stock change. Call inside the transaction that made it.
     */
    public function record(Admin $admin, Product $product, ?ProductVariant $variant, int $before, int $after, ?string $reason, string $source): void
    {
        $delta = $after - $before;

        if ($delta === 0) {
            return;
        }

        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
        // A variant removed by the edit form no longer exists; the stock log's
        // variant_id must point at a real row, so it records the product and
        // names the variant in its note instead.
        $variantExists = $variant !== null && $variant->exists;
        $item = $this->itemLabel($product, $variant).($variant !== null && ! $variantExists ? ' [variant removed]' : '');
        $change = ($delta > 0 ? '+' : '').$delta;
        $where = self::SOURCE_LABELS[$source] ?? $source;
        $summary = "Stock for {$item} changed by {$change} ({$before} → {$after}) by admin {$admin->name} via {$where}".($reason !== null ? ". Reason: {$reason}" : '.');

        InventoryLog::create([
            'product_id' => $product->id,
            'variant_id' => $variantExists ? $variant->id : null,
            'type' => InventoryType::ADJUSTMENT,
            'quantity' => $delta,
            'reference_type' => Admin::class,
            'reference_id' => $admin->id,
            'note' => $summary,
        ]);

        activity('stock')
            ->causedBy($admin)
            ->performedOn($variant ?? $product)
            ->event('stock_adjusted')
            ->withProperties([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'item' => $item,
                'before' => $before,
                'after' => $after,
                'change' => $delta,
                'reason' => $reason,
                'source' => $source,
            ])
            ->log($summary);

        if ($variant !== null && ! $variantExists) {
            return;
        }

        $this->inventory->maybeAlertLowStock(
            $product->name,
            $variant?->sku ?? $product->sku,
            $before,
            $after,
            $variant !== null ? $variant->low_stock_threshold : $product->low_stock_threshold,
        );
    }

    /** Keeps a variant product's own stock column equal to its variants' total. */
    public function resumProductStock(int $productId): void
    {
        Product::query()->whereKey($productId)->update([
            'stock_quantity' => (int) ProductVariant::query()->where('product_id', $productId)->sum('stock_quantity'),
        ]);
    }

    private function itemLabel(Product $product, ?ProductVariant $variant): string
    {
        $options = $variant !== null && is_array($variant->attributes) && $variant->attributes !== []
            ? ' ('.implode(' / ', array_map('strval', $variant->attributes)).')'
            : '';

        return $product->name.$options;
    }
}
