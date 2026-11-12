<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'flash_sale_item_id',
        'bundle_id',
        'coupon_applied',
        'product_name',
        'sku',
        'price',
        'quantity',
        'restocked_quantity',
        'subtotal',
        'product_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'coupon_applied' => 'boolean',
            'price' => 'float',
            'subtotal' => 'float',
            'product_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
     * The options bought, e.g. "Color: Red · Set: 12pcs": the copy taken
     * when the order was placed (`product_snapshot.variant_attributes`), so
     * renaming or removing a variant later never changes it; the live
     * variant only for older lines without that copy. Null for a plain item.
     */
    public function variantLabel(): ?string
    {
        // getAttribute(), not ->attributes: inside a model, PHP would read the
        // other model's protected $attributes array instead of the column.
        $attributes = $this->product_snapshot['variant_attributes'] ?? $this->variant?->getAttribute('attributes');

        if (! is_array($attributes) || $attributes === []) {
            return null;
        }

        return collect($attributes)->map(fn ($value, $name): string => "{$name}: {$value}")->implode(' · ');
    }

    public function flashSaleItem(): BelongsTo
    {
        return $this->belongsTo(FlashSaleItem::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }
}
