<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IncompleteOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncompleteOrder extends Model
{
    /** @use HasFactory<IncompleteOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'attempt_token',
        'user_id',
        'name',
        'phone',
        'items',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'subtotal' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
