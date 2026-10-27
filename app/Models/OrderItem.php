<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'flash_sale_item_id',
        'product_name',
        'sku',
        'price',
        'quantity',
        'subtotal',
        'product_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'subtotal' => 'float',
            'product_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function flashSaleItem(): BelongsTo
    {
        return $this->belongsTo(FlashSaleItem::class);
    }
}
