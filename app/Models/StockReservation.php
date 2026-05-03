<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'quantity',
        'session_id',
        'user_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the product associated with the stock reservation.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product variant associated with the stock reservation.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Get the user that owns the stock reservation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
