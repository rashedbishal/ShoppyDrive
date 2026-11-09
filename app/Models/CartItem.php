<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        // BE-002: a display-only snapshot of the product's name at the time
        // this row was last written — product_id is nullOnDelete, so a
        // permanently deleted product still leaves this row nameable
        // instead of just vanishing or showing a blank message.
        'product_name',
        'variant_id',
        'bundle_id',
        'bundle_unit_quantity',
        'quantity',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }
}
