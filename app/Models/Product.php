<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FlushesCatalogFilters;
use App\Concerns\FlushesSearchSuggestions;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mews\Purifier\Facades\Purifier;

class Product extends Model
{
    use Auditable, FlushesCatalogFilters, FlushesSearchSuggestions, HasFactory, SoftDeletes;

    /** Columns the product-listing filter options depend on (see FlushesCatalogFilters). */
    public const CATALOG_FILTER_FIELDS = ['is_active', 'published_at', 'category_id', 'sub_category_id', 'brand_id', 'price'];

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

    protected $appends = ['available_stock'];

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

    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Purifier::clean($value),
        );
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

    /**
     * What a customer can actually buy. `stock_quantity` is only a real count
     * for a variant-less product: for one with variants it's a sum the admin
     * form writes on save, and orders decrement the variants instead (see
     * InventoryService). Reads only an already-loaded relation, so serializing
     * a list never queries per product. Every storefront query loads
     * `variants`; without them this falls back to `stock_quantity`.
     */
    public function getAvailableStockAttribute(): int
    {
        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            return (int) $this->variants->sum('stock_quantity');
        }

        return (int) ($this->attributes['stock_quantity'] ?? 0);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2);
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_price !== null && $this->compare_price > $this->price;
    }

    /** The query form of `available_stock`, for the storefront stock filters. */
    public function scopeInStock($query)
    {
        return $query->where(fn ($inStock) => $inStock
            ->whereHas('variants', fn ($variants) => $variants->where('stock_quantity', '>', 0))
            ->orWhere(fn ($variantLess) => $variantLess->whereDoesntHave('variants')->where('products.stock_quantity', '>', 0)));
    }

    /**
     * LOW-03: a summed `stock_quantity` compared against one threshold can
     * hide a single critically-low variant behind others that are fine, and
     * `products.low_stock_threshold` only ever holds one variant's value
     * (whichever was saved there), not each variant's own. This instead
     * matches InventoryService's real-time alert: each variant is checked
     * against its own threshold. A NULL variant threshold never matches
     * (opted out of alerting, same as InventoryService::maybeAlertLowStock),
     * not treated as "use a default."
     */
    public function scopeLowStock($query)
    {
        return $query->where(fn ($lowStock) => $lowStock
            ->whereHas('variants', fn ($variants) => $variants->whereColumn('stock_quantity', '<=', 'low_stock_threshold'))
            ->orWhere(fn ($variantLess) => $variantLess->whereDoesntHave('variants')->whereColumn('products.stock_quantity', '<=', 'products.low_stock_threshold')));
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
