<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttributeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
