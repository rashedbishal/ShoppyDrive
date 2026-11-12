<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\CleansUpReplacedFiles;
use App\Concerns\FlushesLayoutCache;
use App\Support\LayoutCache;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    use Auditable, CleansUpReplacedFiles, FlushesLayoutCache;

    /** Uploaded files removed from disk when replaced or permanently deleted (CleansUpReplacedFiles). */
    public const PUBLIC_FILE_COLUMNS = ['image'];

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::HERO_SLIDES];

    protected $fillable = [
        'eyebrow',
        'title',
        'description',
        'image',
        'primary_cta_label',
        'primary_cta_href',
        'stats',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'stats' => 'json',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
