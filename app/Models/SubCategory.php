<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesLayoutCache;
use App\Support\LayoutCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubCategory extends Model
{
    use FlushesLayoutCache, HasFactory, SoftDeletes;

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::NAV_CATEGORIES];

    protected $fillable = [
        'name',
        'slug',
        'category_id',
        'image',
        'description',
        'meta_title',
        'meta_description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::deleting(function (SubCategory $subCategory) {
            if ($subCategory->isForceDeleting()) {
                // `products.sub_category_id` cascadeOnDeletes at the DB
                // level: once this row is actually deleted, every product
                // under it is hard-deleted by the database regardless of
                // what Eloquent does first, which in turn hard-deletes
                // their historical OrderItem rows. Refuse the whole
                // force-delete outright when any of those products has
                // order history — there is no safe partial option.
                $productIds = $subCategory->products()->pluck('id');

                if (OrderItem::whereIn('product_id', $productIds)->exists()) {
                    throw new \RuntimeException(
                        "Cannot permanently delete sub-category \"{$subCategory->name}\": one or more of its products have order history. Soft-delete it instead.",
                    );
                }

                $subCategory->products()->forceDelete();
            } else {
                $subCategory->products()->each(fn ($product) => $product->delete());
            }
        });
    }
}
