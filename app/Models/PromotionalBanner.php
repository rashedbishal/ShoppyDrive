<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\CleansUpReplacedFiles;
use App\Concerns\FlushesLayoutCache;
use App\Support\ImageVariants;
use App\Support\LayoutCache;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromotionalBanner extends Model
{
    use Auditable, CleansUpReplacedFiles, FlushesLayoutCache;

    /** Uploaded files removed from disk when replaced or permanently deleted (CleansUpReplacedFiles). */
    public const PUBLIC_FILE_COLUMNS = ['image'];

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::PROMOTIONAL_BANNERS];

    protected $fillable = [
        'title',
        'description',
        'image',
        'href',
        'badge',
        'accent',
        'is_active',
        'sort_order',
    ];

    /** IMG-F5: see ImageVariants. */
    protected $appends = ['image_srcset'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotional_banner_product')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** IMG-F5: the smaller WebP copies of the banner image, if any. */
    protected function imageSrcset(): Attribute
    {
        return Attribute::get(fn (): ?string => app(ImageVariants::class)->srcset($this->attributes['image'] ?? null));
    }
}
