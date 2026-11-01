<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingCheckout extends Model
{
    protected $fillable = [
        'token',
        'user_id',
        'cart_id',
        'landing_page_id',
        'guest_token',
        'checkout_data',
        'points_reserved',
        'order_number',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'checkout_data' => 'array',
            'points_reserved' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Pending checkouts still holding the given user's loyalty points. A
     * reservation ends by state alone, with nothing to release: converting
     * sets `order_number` (the debit happens in that same transaction),
     * cancelling deletes the row, and expiry is read from `expires_at`. The
     * comparison counts the exact expiry second as active, so it can only
     * err towards holding points for slightly too long.
     *
     * @param  Builder<PendingCheckout>  $query
     * @return Builder<PendingCheckout>
     */
    public function scopeHoldingPointsFor(Builder $query, int $userId): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->whereNull('order_number')
            ->where('points_reserved', '>', 0)
            ->where('expires_at', '>=', now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
