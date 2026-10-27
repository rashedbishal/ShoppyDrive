<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\NewOrderAlertMail;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Mail\ShippedWithTrackingMail;
use App\Services\CouponService;
use App\Services\InventoryService;
use App\Services\LoyaltyPointsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'user_id',
        'landing_page_id',
        'guest_token',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'shipping_cost',
        'discount',
        'tax',
        'total',
        'coupon_id',
        'coupon_code',
        'used_referral_discount',
        'points_redeemed',
        'points_refunded_at',
        'stock_restored_at',
        'coupon_released_at',
        'shipping_address',
        'billing_address',
        'notes',
        'tracking_number',
        'cancellation_reason',
        'shipped_at',
        'delivered_at',
        'pathao_consignment_id',
        'pathao_order_status',
        'pathao_delivery_fee',
        'pathao_sent_at',
        'steadfast_consignment_id',
        'steadfast_tracking_code',
        'steadfast_status',
        'steadfast_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'subtotal' => 'float',
            'shipping_cost' => 'float',
            'discount' => 'float',
            'tax' => 'float',
            'used_referral_discount' => 'boolean',
            'points_refunded_at' => 'datetime',
            'stock_restored_at' => 'datetime',
            'coupon_released_at' => 'datetime',
            'referral_reward_granted_at' => 'datetime',
            'loyalty_points_granted_at' => 'datetime',
            'pathao_delivery_fee' => 'float',
            'pathao_sent_at' => 'datetime',
            'steadfast_sent_at' => 'datetime',
            'total' => 'float',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function latestReturnRequest(): HasOne
    {
        return $this->hasOne(ReturnRequest::class)->latestOfMany();
    }

    protected static function booted(): void
    {
        // ip_address/user_agent are deliberately excluded from $fillable
        // (like Admin::is_mother) — captured here from the request that's
        // actually creating the order, not from any caller-supplied data,
        // so nothing passed to Order::create() can spoof them. Only set
        // when not already provided, so tests/backfills can still assign
        // explicit values via forceFill().
        static::creating(function (self $order): void {
            if ($order->ip_address === null && app()->bound('request')) {
                $order->ip_address = request()->ip();
                $order->user_agent = request()->userAgent();
            }

            // traffic_source/traffic_referrer are set from the sd_traffic_source/
            // sd_traffic_referrer cookies CaptureTrafficSource populates on the
            // customer's very first page view (or latest ad click) — not from
            // caller-supplied data, same spoofing rationale as ip_address above.
            if ($order->traffic_source === null && app()->bound('request')) {
                $order->traffic_source = request()->cookie('sd_traffic_source') ?? 'direct';
                $order->traffic_referrer = request()->cookie('sd_traffic_referrer') ?: null;
            }
        });

        // Fires exactly once per order row ever created — every order-
        // creation path (COD, ZiniPay, mobile banking, landing-page) has
        // its own idempotency guard that returns the already-existing
        // order instead of calling create() again on a retry/duplicate
        // webhook, so this can never double-send for the same order.
        static::created(function (self $order): void {
            static::dispatchMail(OrderPlacedMail::class, $order);
            static::dispatchAdminMail(NewOrderAlertMail::class, $order);
        });

        static::updated(function (self $order): void {
            if (! $order->wasChanged('status')) {
                return;
            }

            if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
                app(LoyaltyPointsService::class)->handleOrderCancelledOrRefunded($order);
                app(InventoryService::class)->restoreForOrder($order);
                app(CouponService::class)->release($order);
            }

            match ($order->status) {
                OrderStatus::SHIPPED => static::dispatchMail(ShippedWithTrackingMail::class, $order),
                OrderStatus::DELIVERED => static::dispatchMail(OrderDeliveredMail::class, $order),
                OrderStatus::CANCELLED => static::dispatchMail(OrderCancelledMail::class, $order),
                OrderStatus::PROCESSING, OrderStatus::REFUNDED => static::dispatchMail(OrderStatusUpdatedMail::class, $order),
                OrderStatus::PENDING => null,
            };
        });
    }

    /**
     * Resolves the customer's email strictly from the order's own data
     * (the account it belongs to, or the shipping address captured at
     * checkout) — never from the currently-authenticated session, which
     * for every status-change caller (admin actions, courier webhooks) is
     * an admin, not the customer. Queued (afterCommit()) so a transient
     * mail failure can never affect the order/payment/shipment action
     * that triggered it, and the job is only actually enqueued once the
     * triggering DB transaction (if any) has committed — discarded
     * entirely if it rolls back. Every one of these Mailables takes the
     * Order as its sole constructor argument.
     */
    private static function dispatchMail(string $mailableClass, self $order): void
    {
        $email = $order->user?->email ?: ($order->shipping_address['email'] ?? null);

        if (! $email) {
            return;
        }

        /** @var Mailable $mailable */
        $mailable = new $mailableClass($order);
        Mail::to($email)->send($mailable->afterCommit());
    }

    private static function dispatchAdminMail(string $mailableClass, self $order): void
    {
        $adminEmail = Admin::where('is_mother', true)->value('email');

        if (! $adminEmail) {
            Log::warning('No mother admin configured — skipping admin order alert.', ['order_number' => $order->order_number]);

            return;
        }

        /** @var Mailable $mailable */
        $mailable = new $mailableClass($order);
        Mail::to($adminEmail)->send($mailable->afterCommit());
    }
}
