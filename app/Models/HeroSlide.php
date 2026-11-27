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

class HeroSlide extends Model
{
    use Auditable, CleansUpReplacedFiles, FlushesLayoutCache;

    /** Uploaded files removed from disk when replaced or permanently deleted (CleansUpReplacedFiles). */
    public const PUBLIC_FILE_COLUMNS = ['image', 'poster'];

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::HERO_SLIDES];

    protected $fillable = [
        'eyebrow',
        'title',
        'description',
        'image',
        // IMG-F3: still shown while a video slide loads.
        'poster',
        'primary_cta_label',
        'primary_cta_href',
        'stats',
        'is_active',
        'sort_order',
    ];

    /** IMG-F5: see ImageVariants. */
    protected $appends = ['image_srcset', 'poster_srcset'];

    protected $casts = [
        'stats' => 'json',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** IMG-F5: the smaller WebP copies of the slide image (null for a video). */
    protected function imageSrcset(): Attribute
    {
        return Attribute::get(fn (): ?string => app(ImageVariants::class)->srcset($this->attributes['image'] ?? null));
    }

    /** IMG-F5: the smaller WebP copies of the video's poster, if any. */
    protected function posterSrcset(): Attribute
    {
        return Attribute::get(fn (): ?string => app(ImageVariants::class)->srcset($this->attributes['poster'] ?? null));
    }
}
