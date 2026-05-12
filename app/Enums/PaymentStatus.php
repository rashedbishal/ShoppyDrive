<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    // Used on orders.payment_status
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case REFUNDED = 'refunded';

    // Used on payments.status (gateway payment records)
    case PENDING = 'pending';
    case FAILED = 'failed';
}
