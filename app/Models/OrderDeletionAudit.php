<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DB-001 (L3): append-only record of a permanent-order-deletion attempt.
 * Written only by App\Services\OrderPermanentDeletionService; never updated
 * or deleted by the application. Holds no password data and no foreign keys.
 */
class OrderDeletionAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'order_number',
        'customer_id',
        'order_status',
        'payment_status',
        'payment_method',
        'subtotal',
        'discount',
        'shipping_cost',
        'total',
        'item_count',
        'inventory_movements',
        'order_created_at',
        'order_deleted_at',
        'admin_id',
        'admin_name',
        'admin_email',
        'step',
        'outcome',
        'refusal_reasons',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'refusal_reasons' => 'array',
            'inventory_movements' => 'array',
            'order_created_at' => 'datetime',
            'order_deleted_at' => 'datetime',
        ];
    }
}
