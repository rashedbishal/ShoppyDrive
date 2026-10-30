<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * The one order-number generator (DB-001). `orders.order_number` is unique
 * across every row, soft-deleted ones included, so the collision check must
 * include trashed orders too — otherwise a reused number would only surface
 * as a database error.
 */
class OrderNumberGenerator
{
    public static function generate(): string
    {
        do {
            $number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }
}
