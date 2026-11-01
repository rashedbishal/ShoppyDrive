<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class BlockedIp extends Model
{
    use Auditable, HasFactory;

    private const CACHE_KEY = 'security.blocked_ips';

    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_by',
    ];

    protected static function booted(): void
    {
        static::created(fn (): bool => self::flushCache());
        static::deleted(fn (): bool => self::flushCache());
    }

    /**
     * The list is cached forever, so a caller writing inside a transaction
     * must call this again after commit, or a reader could store the old list.
     */
    public static function flushCache(): bool
    {
        return Cache::forget(self::CACHE_KEY);
    }

    protected function auditLabel(): string
    {
        return $this->ip_address;
    }

    public function blockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'blocked_by');
    }

    /**
     * Checked on every storefront request (see BlockBlockedIp), so the
     * block list is cached forever as a plain array of IPs and flushed on
     * create/delete rather than hitting the database per request.
     */
    public static function isBlocked(string $ip): bool
    {
        $blockedIps = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => static::query()->pluck('ip_address')->all(),
        );

        return in_array($ip, $blockedIps, true);
    }
}
