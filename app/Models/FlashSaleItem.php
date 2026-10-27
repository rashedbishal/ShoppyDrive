<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            ->sort(function (self $a, self $b): int {
                $aSpecific = $a->variant_id !== null ? 0 : 1;
                $bSpecific = $b->variant_id !== null ? 0 : 1;

                return [$aSpecific, (float) $a->sale_price, $a->flashSale->starts_at->timestamp]
                    <=> [$bSpecific, (float) $b->sale_price, $b->flashSale->starts_at->timestamp];
            })
            ->first();
    }
}
