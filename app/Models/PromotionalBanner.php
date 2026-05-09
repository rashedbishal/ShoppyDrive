<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromotionalBanner extends Model
{
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

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotional_banner_product')->withPivot('sort_order')->orderByPivot('sort_order');
    }
}
