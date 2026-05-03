<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'name',
        'type',
        'cost',
        'min_order_for_free',
        'estimated_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'float',
            'min_order_for_free' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'method_id');
    }
}
