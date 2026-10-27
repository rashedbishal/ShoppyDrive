<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesSearchSuggestions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use FlushesSearchSuggestions, HasFactory, SoftDeletes;

    /** Columns the search-bar suggestions depend on (see FlushesSearchSuggestions). */
    public const SEARCH_SUGGESTION_FIELDS = ['name', 'sku', 'slug', 'price', 'compare_price', 'is_featured', 'is_active', 'published_at'];

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'category_id',
        'sub_category_id',
        'brand_id',
        'price',
        'compare_price',
        'cost_price',
        'stock_quantity',
        'low_stock_threshold',
        'is_active',
        'is_featured',
        'is_digital',
        'free_delivery',
        'free_delivery_min_qty',
        'has_color_variant',
        'has_size_variant',
        'cry_baby_enabled',
        'weight',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_digital' => 'boolean',
            'free_delivery' => 'boolean',
            'free_delivery_min_qty' => 'integer',
            'has_color_variant' => 'boolean',
            'has_size_variant' => 'boolean',
            'cry_baby_enabled' => 'boolean',
            'price' => 'float',
            'compare_price' => 'float',
            'cost_price' => 'float',
            'weight' => 'float',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Product $product) {
            // Same rule as Category/SubCategory's own force-delete guard: once
            // the row is actually removed, order_items.product_id is now
            // nullOnDelete() (see the migration that changed it from
            // cascadeOnDelete()), so the FK itself won't destroy order
            // history — but refusing outright here is still the clearer
            // signal than silently nulling out a historical line item's
            // product reference.
            if ($product->isForceDeleting() && OrderItem::where('product_id', $product->id)->exists()) {
                throw new \RuntimeException(
                    "Cannot permanently delete product \"{$product->name}\": it has order history. Soft-delete it instead.",
                );
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->where('type', 'image');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->where('type', 'video');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function promotionalBanners(): BelongsToMany
    {
        return $this->belongsToMany(PromotionalBanner::class, 'promotional_banner_product');
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2);
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_price !== null && $this->compare_price > $this->price;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
