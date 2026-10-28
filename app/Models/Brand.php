<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FlushesCatalogFilters;
use App\Concerns\FlushesSearchSuggestions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use Auditable, FlushesCatalogFilters, FlushesSearchSuggestions, HasFactory;

    /** Any change affects the product-listing filter options (see FlushesCatalogFilters). */
    public const CATALOG_FILTER_FIELDS = null;

    /** Columns the search-bar suggestions depend on (see FlushesSearchSuggestions). */
    public const SEARCH_SUGGESTION_FIELDS = ['name', 'slug', 'is_active'];

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
