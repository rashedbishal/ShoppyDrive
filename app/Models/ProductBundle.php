<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\CleansUpReplacedFiles;
use App\Enums\BundleDiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBundle extends Model
{
    use Auditable, CleansUpReplacedFiles, HasFactory;

    /** Uploaded files removed from disk when replaced or permanently deleted (CleansUpReplacedFiles). */
    public const PUBLIC_FILE_COLUMNS = ['image'];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'discount_type',
        'bundle_price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'discount_type' => BundleDiscountType::class,
        'bundle_price' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
