<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    use Auditable;

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
