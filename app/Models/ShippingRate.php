<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'method_id',
        'min_weight',
        'max_weight',
        'rate',
    ];

    protected function casts(): array
    {
        return [
            'min_weight' => 'float',
            'max_weight' => 'float',
            'rate' => 'float',
        ];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'method_id');
    }
}
