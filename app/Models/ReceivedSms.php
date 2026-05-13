<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivedSms extends Model
{
    protected $fillable = [
        'sender',
        'provider',
        'content',
        'received_at',
        'parsed_transaction_id',
        'parsed_amount',
        'is_matched',
        'matched_order_id',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'parsed_amount' => 'float',
            'is_matched' => 'boolean',
        ];
    }

    public function matchedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'matched_order_id');
    }
}
