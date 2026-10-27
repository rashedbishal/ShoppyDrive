<?php

declare(strict_types=1);

namespace App\Enums;

enum BundleDiscountType: string
{
    case FixedPrice = 'fixed_price';
    case FreeDelivery = 'free_delivery';
}
