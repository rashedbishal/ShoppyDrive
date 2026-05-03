<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryType: string
{
    case IN = 'in';
    case OUT = 'out';
    case ADJUSTMENT = 'adjustment';
    case RESERVATION = 'reservation';
    case RELEASE = 'release';
}
