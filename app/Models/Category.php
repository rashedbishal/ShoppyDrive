<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesLayoutCache;
use App\Support\LayoutCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use FlushesLayoutCache, HasFactory, SoftDeletes;

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::NAV_CATEGORIES];

    protected $fillable = [
        'name',
        'slug',
        'image',
        'icon',
        'color',
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

    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategory::class);
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
        static::deleting(function (Category $category) {
            if ($category->isForceDeleting()) {
                // `products.category_id` and `sub_categories.category_id`
                // both cascadeOnDelete at the DB level: once this
                // category's row is actually deleted, every product below
                // it (directly or via a sub-category) is hard-deleted by
                // the database regardless of what Eloquent does first —
                // there is no selectively-soft-delete-some-of-them escape
                // hatch, and that in turn cascades to hard-delete their
                // historical OrderItem rows. The only safe option is to
                // refuse the whole force-delete outright when any of
                // those products has order history.
                $productIds = $category->products()->pluck('id')
                    ->merge(Product::whereIn('sub_category_id', $category->subCategories()->pluck('id'))->pluck('id'));

                if (OrderItem::whereIn('product_id', $productIds)->exists()) {
                    throw new \RuntimeException(
                        "Cannot permanently delete category \"{$category->name}\": one or more of its products have order history. Soft-delete it instead.",
                    );
                }

                $category->subCategories()->forceDelete();
                $category->products()->forceDelete();
            } else {
                $category->subCategories()->each(fn ($subCategory) => $subCategory->delete());
                $category->products()->each(fn ($product) => $product->delete());
            }
        });
    }
}
