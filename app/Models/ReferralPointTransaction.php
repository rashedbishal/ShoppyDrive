<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralPointTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'referred_user_id',
        'order_id',
        'type',
        'points',
        'currency_amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => PointTransactionType::class,
            'points' => 'integer',
            'currency_amount' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
