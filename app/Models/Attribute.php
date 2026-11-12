<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FlushesCatalogFilters;
use App\Enums\AttributeType;
use App\Support\VariantAttributeLabels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use Auditable, FlushesCatalogFilters, HasFactory;

    /** Any change affects the product-listing filter options (see FlushesCatalogFilters). */
    public const CATALOG_FILTER_FIELDS = null;

    protected $fillable = [
        'name',
        'type',
        'has_images',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'has_images' => 'boolean',
        ];
    }

    /** Variant labels are keyed by attribute name, so a rename rewrites them. */
    protected static function booted(): void
    {
        static::updated(function (Attribute $attribute): void {
            if ($attribute->wasChanged('name')) {
                VariantAttributeLabels::refreshForValues($attribute->values()->pluck('id')->all());
            }
        });
    }

    /**
     * Whether any product or variant uses this attribute (a product's variant
     * attributes, a variant's values, or the product attribute-value pivot),
     * so deleting it would break them.
     */
    public function isInUse(): bool
    {
        $valueIds = $this->values()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return Product::withTrashed()->whereNotNull('variant_attribute_ids')->get(['id', 'variant_attribute_ids'])
            ->contains(fn (Product $product): bool => in_array($this->id, array_map('intval', $product->variant_attribute_ids ?? []), true))
            || ($valueIds !== [] && AttributeValue::query()->whereKey($valueIds)->get()->contains(fn (AttributeValue $value): bool => $value->isInUse()));
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
