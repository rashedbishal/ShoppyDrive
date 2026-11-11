<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Meta Conversions API event waiting to be, or already, delivered — see
 * the migration's docblock and App\Services\MetaEventRecorder, the only
 * place rows are created.
 *
 * @property array<string, mixed> $custom_data
 */
class MetaTrackingEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * The six events the storefront reports itself; Purchase is deliberately
     * absent — it is only ever created from a real order (MetaEventRecorder::recordPurchase()).
     */
    public const BROWSER_EVENTS = ['ViewContent', 'AddToCart', 'RemoveFromCart', 'AddToWishlist', 'InitiateCheckout', 'Search'];

    /**
     * Meta's standard events; anything else (RemoveFromCart) is a custom
     * event and must be fired with fbq('trackCustom') in the browser.
     */
    public const STANDARD_EVENTS = ['ViewContent', 'AddToCart', 'AddToWishlist', 'InitiateCheckout', 'Search', 'Purchase'];

    protected $fillable = [
        'event_name',
        'event_id',
        'order_id',
        'user_id',
        'action_source',
        'event_source_url',
        'custom_data',
        'client_ip_address',
        'client_user_agent',
        'fbp',
        'fbc',
        'event_time',
    ];

    protected function casts(): array
    {
        return [
            'custom_data' => 'array',
            'event_time' => 'datetime',
            'sent_at' => 'datetime',
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
