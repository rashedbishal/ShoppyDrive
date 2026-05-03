<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rate_percent',
        'country',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'float',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
