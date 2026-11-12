<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FlushesCatalogFilters;
use App\Support\VariantAttributeLabels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AttributeValue extends Model
{
    use Auditable, FlushesCatalogFilters, HasFactory;

    /** Any change affects the product-listing filter options (see FlushesCatalogFilters). */
    public const CATALOG_FILTER_FIELDS = null;

    protected $fillable = [
        'attribute_id',
        'value',
        'color_hex',
        'sort_order',
    ];

    /** Variant labels show value names, so a rename rewrites them. */
    protected static function booted(): void
    {
        static::updated(function (AttributeValue $value): void {
            if ($value->wasChanged('value')) {
                VariantAttributeLabels::refreshForValues([$value->id]);
            }
        });
    }

    /**
     * Variants (current or retired, kept for carts and orders) that use this
     * value. `attribute_value_ids` is JSON, so they're matched in PHP.
     *
     * @return Collection<int, ProductVariant>
     */
    public function variantsUsingIt(): Collection
    {
        return ProductVariant::query()
            ->whereNotNull('attribute_value_ids')
            ->get(['id', 'product_id', 'attribute_value_ids'])
            ->filter(fn (ProductVariant $variant): bool => in_array($this->id, $variant->valueIds(), true))
            ->values();
    }

    public function isInUse(): bool
    {
        return $this->products()->exists()
            || $this->colorVariants()->exists()
            || $this->sizeVariants()->exists()
            || $this->variantsUsingIt()->isNotEmpty();
    }

    protected function auditLabel(): string
    {
        return "{$this->attribute?->name}: {$this->value}";
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_values');
    }

    public function colorVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'color_value_id');
    }

    public function sizeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'size_value_id');
    }
}
