<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesLayoutCache;
use App\Support\LayoutCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use FlushesLayoutCache, HasFactory;

    /** LayoutCache entries built from this table (see FlushesLayoutCache). */
    public const LAYOUT_CACHE_KEYS = [LayoutCache::SETTINGS];

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
