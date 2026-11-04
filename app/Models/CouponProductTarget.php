<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product a coupon is limited to: the whole product, or with a variant_id,
 * just that variant.
 */
class CouponProductTarget extends Model
{
    protected $fillable = [
        'coupon_id',
        'product_id',
        'variant_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
