<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'status',
        'items',
        'admin_note',
        'refund_amount',
        'refund_method',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'items' => 'array',
            'refund_amount' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
