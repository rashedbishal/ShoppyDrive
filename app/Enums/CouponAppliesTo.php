<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponAppliesTo: string
{
    case ALL = 'all';
    case SELECTED = 'selected';
}
