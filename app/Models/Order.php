<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'user_id',
        'landing_page_id',
        'guest_token',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'shipping_cost',
        'discount',
        'tax',
        'total',
        'coupon_id',
        'coupon_code',
        'shipping_address',
        'billing_address',
        'notes',
        'tracking_number',
        'cancellation_reason',
        'shipped_at',
        'delivered_at',
        'pathao_consignment_id',
        'pathao_order_status',
        'pathao_delivery_fee',
        'pathao_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'subtotal' => 'float',
            'shipping_cost' => 'float',
            'discount' => 'float',
            'tax' => 'float',
            'pathao_delivery_fee' => 'float',
            'pathao_sent_at' => 'datetime',
            'total' => 'float',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}
