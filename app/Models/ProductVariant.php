<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    /**
     * FSA-04: see Product::$hidden.
     *
     * @var list<string>
     */
    protected $hidden = ['cost_price'];

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'compare_price',
        'cost_price',
        'stock_quantity',
        'low_stock_threshold',
        'attributes',
        'is_default',
        'color_value_id',
        'size_value_id',
    ];

    protected $appends = ['image'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price' => 'float',
            'compare_price' => 'float',
            'cost_price' => 'float',
            'is_default' => 'boolean',
        ];
    }

    public function getImageAttribute(): ?string
    {
        return $this->media->firstWhere('is_primary', true)?->path
            ?? $this->media->first()?->path;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function colorValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'color_value_id');
    }

    public function sizeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'size_value_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'variant_id');
    }
}
