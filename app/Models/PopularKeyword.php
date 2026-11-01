<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\FlushesLayoutCache;
use App\Support\LayoutCache;
use Illuminate\Database\Eloquent\Model;

class PopularKeyword extends Model
{
    use Auditable, FlushesLayoutCache;

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::POPULAR_KEYWORDS];

    protected $fillable = ['keyword', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function auditLabel(): string
    {
        return $this->keyword;
    }
}
