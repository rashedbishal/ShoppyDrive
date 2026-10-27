<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIp extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_by',
    ];

    public function blockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'blocked_by');
    }

    public static function isBlocked(string $ip): bool
    {
        return static::where('ip_address', $ip)->exists();
    }
}
