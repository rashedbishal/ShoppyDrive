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
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'subtotal' => 'float',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Clean up whatever tracked checkout attempt(s) a real order just
     * resolved. Always matched by phone (a guest never has a user_id to
     * match by at all); additionally matched by user_id when the order
     * belongs to a logged-in customer, since they may have entered a
     * different phone number between abandoning and actually completing
     * checkout — phone alone would miss that row forever. Never matches
     * by user_id alone, and never for a guest order (`$userId === null`),
     * so this can never delete a row belonging to a different customer.
     */
    public static function deleteCompletedFor(string $phone, ?int $userId): void
    {
        static::where(function ($query) use ($phone, $userId) {
            $query->where('phone', $phone);

            if ($userId !== null) {
                $query->orWhere('user_id', $userId);
            }
        })->delete();
    }
}
