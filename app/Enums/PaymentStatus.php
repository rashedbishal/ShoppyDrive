<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case REFUNDED = 'refunded';
}
