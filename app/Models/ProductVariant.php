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
    protected $hidden = ['cost_price', 'media'];

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
        'attribute_value_ids',
    ];

    protected $appends = ['image'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'attribute_value_ids' => 'array',
            'price' => 'float',
            'compare_price' => 'float',
            'cost_price' => 'float',
            'is_default' => 'boolean',
        ];
    }

    /**
     * This variant's photo: an image chosen for this exact variant (an older
     * per-variant pick), otherwise the image chosen for one of its values —
     * one Red photo serves Red/S, Red/M and Red/L. With several attributes
     * that allow images, the first in the product's attribute order wins.
     * A video can't be a thumbnail, so value images are images only.
     */
    public function getImageAttribute(): ?string
    {
        $candidateSets = [$this->media->where('variant_id', $this->id)];

        foreach ($this->valueIds() as $valueId) {
            $candidateSets[] = $this->media->where('attribute_value_id', $valueId)->where('type', 'image');
        }

        foreach ($candidateSets as $candidates) {
            $media = $candidates->firstWhere('is_primary', true) ?? $candidates->first();

            if ($media !== null) {
                return $media->path;
            }
        }

        return null;
    }

    /**
     * This variant's attribute value ids in the product's attribute order. A
     * row that predates custom attributes (or was created without them) falls
     * back to its Color/Size columns.
     *
     * @return array<int, int>
     */
    public function valueIds(): array
    {
        return array_map('intval', $this->attribute_value_ids
            ?? array_values(array_filter([$this->color_value_id, $this->size_value_id])));
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

    /**
     * Every media row of this variant's product, not only rows pointing at
     * this variant: a colour image has no `variant_id`, and `image` picks from
     * these. Kept under this name so the existing `variants.media` eager loads
     * (PERF-001) fetch it in one query. Hidden from JSON (`$hidden`): the
     * gallery is `Product::media`, and repeating it on every variant would
     * bloat every product card.
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'product_id', 'product_id')->orderBy('sort_order')->orderBy('id');
    }
}
