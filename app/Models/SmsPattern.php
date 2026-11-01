<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SmsPattern extends Model
{
    use Auditable;

    protected $fillable = [
        'provider',
        'name',
        'identifier',
        'txn_id_regex',
        'amount_regex',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $clear = fn () => Cache::forget('sms_patterns_active');
        static::saved($clear);
        static::deleted($clear);
    }
}
