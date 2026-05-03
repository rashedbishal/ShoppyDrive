<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'countries',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class, 'zone_id');
    }
}
