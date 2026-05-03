<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'per_user_limit',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'float',
            'min_order_amount' => 'float',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
