<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\CleansUpReplacedFiles;
use App\Concerns\FlushesCatalogFilters;
use App\Concerns\FlushesSearchSuggestions;
use App\Concerns\RecordsSlugRedirects;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use Auditable, CleansUpReplacedFiles, FlushesCatalogFilters, FlushesSearchSuggestions, HasFactory, RecordsSlugRedirects;

    /** DISC-92: old slugs redirect under /brand/{slug} (see RecordsSlugRedirects). */
    public const SLUG_REDIRECT_NAMESPACE = 'brand';

    /** Uploaded files removed from disk when replaced or permanently deleted (CleansUpReplacedFiles). */
    public const PUBLIC_FILE_COLUMNS = ['logo'];

    /** Any change affects the product-listing filter options (see FlushesCatalogFilters). */
    public const CATALOG_FILTER_FIELDS = null;

    /** Columns the search-bar suggestions depend on (see FlushesSearchSuggestions). */
    public const SEARCH_SUGGESTION_FIELDS = ['name', 'slug', 'is_active'];

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'meta_title',
        'meta_description',
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

    /** DISC-92: where an old slug of this brand sends visitors, while it's active (same rule as BrandController::show). */
    public function currentPublicUrl(): ?string
    {
        return $this->is_active ? route('brand.show', $this->slug) : null;
    }
}
