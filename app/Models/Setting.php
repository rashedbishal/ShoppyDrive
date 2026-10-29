<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesLayoutCache;
use App\Support\LayoutCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Setting extends Model
{
    use FlushesLayoutCache, HasFactory, LogsActivity;

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::SETTINGS];

    /**
     * Keys in this generic key-value table whose `value` is rich-text HTML
     * and must be purified before saving. Deliberately an explicit
     * allow-list, not a content-shape heuristic — this same table holds
     * courier/gateway/webhook secrets in the same `value` column, and a
     * heuristic could wrongly touch one of those.
     */
    private const RICH_TEXT_KEYS = ['about_mission_body'];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Setting $setting): void {
            if (in_array($setting->key, self::RICH_TEXT_KEYS, true) && is_string($setting->value)) {
                $setting->value = Purifier::clean($setting->value);
            }
        });
    }

    /**
     * `value` is deliberately never logged — it's where API keys, webhook
     * secrets, and other credentials live (courier/gateway/SMS-forwarder
     * config), and any admin with Audit Trail access would otherwise be
     * able to read them off the log. `key`/`group` alone aren't dirty on
     * most saves (only `value` changes), so this skips `logOnlyDirty()`
     * entirely — every real update produces one entry regardless, just
     * without the actual value in it.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logOnly(['key', 'group'])
            ->setDescriptionForEvent(fn (string $eventName): string => "Setting {$eventName}: {$this->key}");
    }

    /**
     * The flat delivery charge for a shipping address, admin-configurable
     * from Admin > Courier Config. Falls back to the original hardcoded
     * rates when not yet set, so existing orders/estimates don't change
     * until an admin actually saves a rate.
     */
    public static function shippingRateFor(?string $district, ?string $city): float
    {
        $isInsideDhaka = $district === 'Dhaka' && $city === 'Inside Dhaka City Corporation';
        $key = $isInsideDhaka ? 'shipping_rate_inside_dhaka' : 'shipping_rate_outside_dhaka';
        $default = $isInsideDhaka ? 60.0 : 120.0;

        return (float) (LayoutCache::settings()[$key] ?? $default);
    }
}
