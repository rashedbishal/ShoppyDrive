<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Setting extends Model
{
    use HasFactory;
    use LogsActivity;

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

        return (float) (static::find($key)?->value ?? $default);
    }
}
