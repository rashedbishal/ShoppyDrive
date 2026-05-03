<?php

declare(strict_types=1);

namespace App\Enums;

enum AttributeType: string
{
    case SELECT = 'select';
    case COLOR = 'color';
    case TEXT = 'text';
}
