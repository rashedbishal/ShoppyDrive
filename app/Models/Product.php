<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FlushesCatalogFilters;
use App\Concerns\FlushesSearchSuggestions;
use App\Services\ReviewImageStorage;
use App\Support\UnusedUploadRemover;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

class Product extends Model
{
    use Auditable, FlushesCatalogFilters, FlushesSearchSuggestions, HasFactory, SoftDeletes;

    /** Columns the product-listing filter options depend on (see FlushesCatalogFilters). */
    public const CATALOG_FILTER_FIELDS = ['is_active', 'published_at', 'category_id', 'sub_category_id', 'brand_id', 'price'];

    /** Columns the search-bar suggestions depend on (see FlushesSearchSuggestions). */
    public const SEARCH_SUGGESTION_FIELDS = ['name', 'sku', 'slug', 'price', 'compare_price', 'is_featured', 'is_active', 'published_at', 'meta_keywords'];

    /**
     * FSA-04: wholesale cost never leaves the server in a storefront
     * response. Admin screens that edit it call makeVisible('cost_price').
     *
     * @var list<string>
     */
    protected $hidden = ['cost_price'];

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'specifications',
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
        'variant_attribute_ids',
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
            'specifications' => 'json',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_digital' => 'boolean',
            'free_delivery' => 'boolean',
            'free_delivery_min_qty' => 'integer',
            'has_color_variant' => 'boolean',
            'has_size_variant' => 'boolean',
            'variant_attribute_ids' => 'array',
            'cry_baby_enabled' => 'boolean',
            'price' => 'float',
            'compare_price' => 'float',
            'cost_price' => 'float',
            'weight' => 'float',
            // withAvg()/loadAvg()'s 'reviews as average_rating' alias (used
            // across ProductController, HomeController, ProductListingController,
            // SearchController, BrandController, FlashSaleController) isn't a
            // real column, but casts apply to it the same way: on MySQL,
            // AVG() is a DECIMAL and PDO returns those as strings, not
            // floats, so `product.average_rating.toFixed(1)` crashed the
            // product page (and every ProductCard, via star-rating.tsx's
            // own `rating.toFixed(1)`) the moment any product had a real,
            // non-null average — invisible locally since SQLite's AVG()
            // returns an actual float.
            'average_rating' => 'float',
            'published_at' => 'datetime',
        ];
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Purifier::clean($value),
        );
    }

    /** @var list<string> Media file paths a permanent delete collects for removal once it's done. */
    private array $mediaPathsToRemove = [];

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

            // Permanently deleting a product also deletes its reviews at the
            // database level (no model events), so their photos are removed
            // from disk here, once the delete has committed.
            if ($product->isForceDeleting()) {
                $photos = Review::withTrashed()->where('product_id', $product->id)->pluck('images')->flatten()->filter()->values()->all();

                if ($photos !== []) {
                    DB::afterCommit(fn () => app(ReviewImageStorage::class)->delete($photos));
                }

                // Its media rows go with it at the database level (cascade),
                // so their paths are read now, while the rows still exist.
                $product->mediaPathsToRemove = $product->media()->pluck('path')->all();
            }
        });

        // Only after the row is gone can "is anything else still using this
        // file?" give the right answer; an ordered product never gets here.
        static::forceDeleted(function (Product $product): void {
            app(UnusedUploadRemover::class)->removeIfUnused(...$product->mediaPathsToRemove);
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

    /**
     * The image an admin chose as this product's default, falling back to its
     * first image for a product with no primary image (e.g. a video is primary).
     */
    public function primaryImagePath(): ?string
    {
        return ($this->images->firstWhere('is_primary', true) ?? $this->images->first())?->path;
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->where('type', 'video');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
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

    /**
     * Deactivating a brand/category/sub-category must hide every product
     * under it too — a product's own is_active/published_at alone isn't
     * enough. category_id is never null; sub_category_id and brand_id can
     * be, so those two are only checked when actually set.
     */
    public function scopeTaxonomyActive($query)
    {
        return $query
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q->whereNull('sub_category_id')->orWhereHas('subCategory', fn ($q) => $q->where('is_active', true)))
            ->where(fn ($q) => $q->whereNull('brand_id')->orWhereHas('brand', fn ($q) => $q->where('is_active', true)));
    }

    /**
     * The one scope storefront listings should use instead of
     * `active()->published()` alone — see `isPubliclyVisible()`, its
     * instance-form equivalent.
     */
    public function scopeVisible($query)
    {
        return $query->active()->published()->taxonomyActive();
    }

    /**
     * Item 28: sort by the price a customer actually sees on the card — the
     * active flash-sale price when there is one, the regular price
     * otherwise. Sorting by the `price` column alone put a ৳2,000 product
     * on flash sale at ৳500 last in "Low to High". Products with variants
     * already store their cheapest variant price in `price`, which is what
     * their card shows. `products.id` breaks ties so pagination is stable.
     */
    public function scopeOrderByDisplayedPrice($query, string $direction = 'asc')
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $flashPrice = FlashSaleItem::activePriceForProductSubquery();

        return $query
            ->orderByRaw("COALESCE(({$flashPrice->toSql()}), products.price) {$direction}", $flashPrice->getBindings())
            ->orderBy('products.id', $direction);
    }

    /**
     * FSA-05: the instance form of `visible()` — whether a customer may see
     * or buy this product right now. Also false once this product's own
     * brand, category or sub-category has been deactivated, even though
     * none of this product's own columns changed.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->is_active
            && $this->published_at !== null
            && ! $this->published_at->isFuture()
            && $this->category?->is_active === true
            && ($this->sub_category_id === null || $this->subCategory?->is_active === true)
            && ($this->brand_id === null || $this->brand?->is_active === true);
    }
}
