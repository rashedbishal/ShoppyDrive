<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FlashSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'flash_sale_id',
        'product_id',
        'variant_id',
        'sale_price',
        'stock_limit',
        'sold_count',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'float',
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * The single source of truth for "is this product/variant currently on
     * flash sale, with stock remaining" — used identically for pricing
     * (CartManager::resolvePrice) and stock-cap enforcement
     * (InventoryService), so the two can never disagree about which sale
     * applies. The admin UI only ever creates product-wide items
     * (`variant_id` is always null today — see Admin\FlashSaleController),
     * but this still prefers an exact variant-level match if one exists.
     *
     * Overlapping campaigns for the same product are not prevented by the
     * admin UI and there is no defined campaign-priority rule for that
     * case. Rather than silently inventing one, this picks the most
     * specific match (variant over product-wide), then the lowest sale
     * price (safest for the customer — never risks overcharging), then the
     * earliest-starting campaign as a final deterministic tie-break. This
     * is a flagged assumption, not a confirmed business rule.
     */
    public static function findActiveFor(int $productId, ?int $variantId): ?self
    {
        $candidates = static::query()
            ->where('product_id', $productId)
            ->where(function ($query) use ($variantId) {
                $query->whereNull('variant_id');

                if ($variantId !== null) {
                    $query->orWhere('variant_id', $variantId);
                }
            })
            ->whereHas('flashSale', function ($query) {
                $query->where('is_active', true)
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now());
            })
            ->with('flashSale')
            ->get()
            ->filter(fn (self $item): bool => $item->stock_limit === null || $item->sold_count < $item->stock_limit);

        return $candidates
            ->sort(self::compareCandidates(...))
            ->first();
    }

    /**
     * PERF-001: batched findActiveFor() for one product and many of its
     * variants — one query for the whole product page instead of one per
     * variant (a 30-variant product on sale cost 60 extra queries).
     *
     * Loads the union of every candidate findActiveFor() would consider for
     * any of these variants (product-wide items plus items for exactly these
     * variants), then picks per variant from the same subset, with the same
     * stock-exhaustion filter and the same compareCandidates() ordering — so
     * each result is the item findActiveFor($productId, $variantId) returns.
     * `product` is the findActiveFor($productId, null) answer. Display-only:
     * pricing and stock enforcement (CartManager, InventoryService) keep
     * calling findActiveFor() itself.
     *
     * @param  list<int>  $variantIds
     * @return array{product: ?self, variants: array<int, ?self>}
     */
    public static function findActiveForVariants(int $productId, array $variantIds): array
    {
        $candidates = static::query()
            ->where('product_id', $productId)
            ->where(function ($query) use ($variantIds) {
                $query->whereNull('variant_id');

                if ($variantIds !== []) {
                    $query->orWhereIn('variant_id', $variantIds);
                }
            })
            ->whereHas('flashSale', function ($query) {
                $query->where('is_active', true)
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now());
            })
            ->with('flashSale')
            ->get()
            ->filter(fn (self $item): bool => $item->stock_limit === null || $item->sold_count < $item->stock_limit);

        $productWide = $candidates->filter(fn (self $item): bool => $item->variant_id === null);

        $variants = [];
        foreach ($variantIds as $variantId) {
            $variants[$variantId] = $candidates
                ->filter(fn (self $item): bool => $item->variant_id === null || (int) $item->variant_id === $variantId)
                ->sort(self::compareCandidates(...))
                ->first();
        }

        return [
            'product' => $productWide->sort(self::compareCandidates(...))->first(),
            'variants' => $variants,
        ];
    }

    /**
     * The tie-break shared by findActiveFor() and findActiveForVariants():
     * variant-specific over product-wide, then lowest sale price, then the
     * earliest-starting campaign.
     */
    private static function compareCandidates(self $a, self $b): int
    {
        $aSpecific = $a->variant_id !== null ? 0 : 1;
        $bSpecific = $b->variant_id !== null ? 0 : 1;

        return [$aSpecific, (float) $a->sale_price, $a->flashSale->starts_at->timestamp]
            <=> [$bSpecific, (float) $b->sale_price, $b->flashSale->starts_at->timestamp];
    }

    /**
     * Item 28: the SQL form of activePricesForProducts(), as a correlated
     * subquery on `products.id` — the cheapest product-wide sale price with
     * stock left in a campaign running right now, or NULL. Same scope as
     * that method (variant_id IS NULL, active window, stock limit not
     * reached), so a listing sorted by it orders products by exactly the
     * flash price their cards show. Used by Product::scopeOrderByDisplayedPrice().
     *
     * @return Builder<self>
     */
    public static function activePriceForProductSubquery(): Builder
    {
        return static::query()
            ->selectRaw('MIN(flash_sale_items.sale_price)')
            ->whereColumn('flash_sale_items.product_id', 'products.id')
            ->whereNull('flash_sale_items.variant_id')
            ->where(fn ($query) => $query
                ->whereNull('flash_sale_items.stock_limit')
                ->orWhereColumn('flash_sale_items.sold_count', '<', 'flash_sale_items.stock_limit'))
            ->whereHas('flashSale', function ($query) {
                $query->where('is_active', true)
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now());
            });
    }

    /**
     * Batched version of findActiveFor($productId, null) — one query for
     * many products instead of one query per product, for a listing/search
     * results page showing several at once. Mirrors that exact call's
     * product-wide-only scope (variant_id IS NULL): a product with only a
     * variant-specific item and no product-wide one is correctly reported
     * as not on sale here, same as the product detail page's own
     * card-level fields (ProductController) — this exists so every place
     * that shows one price per product agrees with the one place
     * (ProductController, for in_flash_sale/flash_sale_price at the
     * product level) that already got it right. Same stock-exhaustion
     * exclusion and lowest-price tie-break as findActiveFor().
     *
     * @param  list<int>  $productIds
     * @return array<int, array{price: float, ends_at: Carbon}>
     */
    public static function activePricesForProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return static::query()
            ->whereIn('product_id', $productIds)
            ->whereNull('variant_id')
            ->whereHas('flashSale', function ($query) {
                $query->where('is_active', true)
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now());
            })
            ->with('flashSale')
            ->get()
            ->filter(fn (self $item): bool => $item->stock_limit === null || $item->sold_count < $item->stock_limit)
            ->groupBy('product_id')
            ->map(function ($items): array {
                $cheapest = $items->sortBy('sale_price')->first();

                return [
                    'price' => (float) $cheapest->sale_price,
                    'ends_at' => $cheapest->flashSale->ends_at,
                ];
            })
            ->all();
    }
}
