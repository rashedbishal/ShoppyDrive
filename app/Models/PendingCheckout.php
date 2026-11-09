<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingCheckout extends Model
{
    /**
     * BIZ-010: how much longer, past its own 2-hour window, a checkout
     * keeps holding its redeemed points once there's real evidence (an
     * unconsumed SMS matching its exact total, received on time) that
     * MobilePaymentController's late-submission grace (FSA-07) might still
     * complete it. Bounded, not indefinite, so a checkout that never
     * actually gets paid still releases its points eventually — the whole
     * reason the 2-hour expiry exists at all. See hasUnclaimedProofOfPayment().
     */
    private const LATE_PAYMENT_GRACE_HOURS = 24;

    protected $fillable = [
        'token',
        'user_id',
        'cart_id',
        'landing_page_id',
        'guest_token',
        'checkout_data',
        'points_reserved',
        'order_number',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'checkout_data' => 'array',
            'points_reserved' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * BIZ-010: whether this expired checkout still has real evidence a
     * late payment might complete it — an unconsumed SMS matching its
     * exact total, received within its own original window (the same
     * window MobilePaymentController::hasProofOfPaymentWithinWindow()
     * checks). The provider isn't known until the customer actually
     * submits (see MobilePaymentController::submit()), so this matches on
     * amount alone, within the same ±1 taka tolerance `submit()` uses for
     * a real payment — worst case it holds a different customer's
     * unrelated, coincidentally same-amount payment's points a little
     * longer, never indefinitely (bounded by scopeHoldingPointsFor()'s
     * own window, not by this check).
     */
    public function hasUnclaimedProofOfPayment(): bool
    {
        $total = (float) ($this->checkout_data['total'] ?? 0);

        if ($total <= 0) {
            return false;
        }

        return ReceivedSms::where('is_matched', false)
            ->whereNotNull('parsed_amount')
            ->whereBetween('received_at', [$this->created_at, $this->expires_at])
            ->whereRaw('ABS(parsed_amount - ?) <= 1', [$total])
            ->exists();
    }

    /**
     * Pending checkouts still holding the given user's loyalty points. A
     * reservation ends by state alone, with nothing to release: converting
     * sets `order_number` (the debit happens in that same transaction),
     * cancelling deletes the row, and expiry is read from `expires_at`. The
     * comparison counts the exact expiry second as active, so it can only
     * err towards holding points for slightly too long.
     *
     * Widened past plain non-expiry (BIZ-010 retest, 2026-11-09): a
     * checkout past its own window but within LATE_PAYMENT_GRACE_HOURS of
     * it is also a candidate — the caller must still check
     * hasUnclaimedProofOfPayment() per row, since this only bounds *how
     * far back* to look, not whether a specific row is actually still
     * holding (that needs the SMS table, which a single SQL aggregate
     * can't cheaply join on amount alone across JSON columns).
     *
     * @param  Builder<PendingCheckout>  $query
     * @return Builder<PendingCheckout>
     */
    public function scopeHoldingPointsFor(Builder $query, int $userId): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->whereNull('order_number')
            ->where('points_reserved', '>', 0)
            ->where('expires_at', '>=', now()->subHours(self::LATE_PAYMENT_GRACE_HOURS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
