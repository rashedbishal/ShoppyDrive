<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FlushesLayoutCache;
use App\Support\LayoutCache;
use Illuminate\Database\Eloquent\Model;

class HomepageSetting extends Model
{
    use Auditable, FlushesLayoutCache;

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::HOMEPAGE_SETTINGS];

    protected $fillable = [
        'section_key',
        'section_name',
        'title',
        'description',
        'eyebrow',
        'action_label',
        'action_href',
        'is_visible',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'json',
    ];

    protected function auditLabel(): string
    {
        return $this->section_name ?: $this->section_key;
    }
}
