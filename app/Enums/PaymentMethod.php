<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case COD = 'cod';
    case ZINIPAY = 'zinipay';
    case MobileBanking = 'mobile_banking';
}
