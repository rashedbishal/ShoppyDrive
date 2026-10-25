<?php

declare(strict_types=1);

namespace App\Enums;

enum PointTransactionType: string
{
    case EARN = 'earn';
    case REDEEM = 'redeem';
    case REVOKE = 'revoke';
    case REFUND = 'refund';
}
