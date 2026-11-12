<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\InsufficientStockException;
use App\Mail\PaidOrderOutOfStockAlertMail;
use App\Models\PendingCheckout;
use App\Models\ReceivedSms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * DISC-19: a bKash/Nagad payment SMS passed every check (provider, unused,
 * after checkout, amount), but an item sold out before the order could be
 * created. The order transaction has already rolled back, so no order exists
 * and the SMS stays unmatched — the customer may still resubmit it if stock
 * returns. Shared by cart checkout (MobilePaymentController) and landing-page
 * checkout (LandingPageController).
 *
 * The authoritative record is an entry in the PendingCheckout's own
 * `checkout_data['paid_stock_failures']` list, one per SMS. It survives
 * deploys and cache clears, and the Received SMS admin page shows it on
 * that SMS for as long as the SMS stays unmatched. The alert email is a
 * supplementary notification sent only by the request that recorded a new
 * entry; if it can't be queued the entry remains and the failure is logged.
 */
class PaidOrderStockFailure
{
    public const DATA_KEY = 'paid_stock_failures';

    /**
     * Record the failure (once per SMS), alert the shop for a new one, and
     * return the message shown to the customer.
     */
    public static function handle(PendingCheckout $pending, ReceivedSms $sms, string $provider, string $transactionId, InsufficientStockException $exception): string
    {
        $amount = (float) $sms->parsed_amount;
        $entry = [
            'sms_id' => $sms->id,
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'unavailable_item' => $exception->productName,
            'recorded_at' => now()->toIso8601String(),
        ];

        $context = [
            'pending_checkout_id' => $pending->id,
            'order_number' => $pending->checkout_data['order_number'] ?? null,
            'sms_id' => $sms->id,
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'amount' => $amount,
        ];

        try {
            $recordedNow = self::record($pending, $sms, $entry);
        } catch (Throwable $e) {
            // The marker could not be written: alert anyway so the payment
            // is not lost silently (a duplicate alert beats none).
            Log::error('[PaidStockFailure] Could not record the failure on the pending checkout', $context + SafeLogContext::exception($e));
            $recordedNow = true;
        }

        if ($recordedNow) {
            Log::warning('[PaidStockFailure] Paid mobile-banking checkout could not become an order: an item is out of stock', $context);
            self::alert($pending, $entry, $context);
        }

        return self::customerMessage($amount, $transactionId, $exception->productName);
    }

    /**
     * Unmatched SMS ids => their failure entry (plus the intended order and
     * customer), for the Received SMS admin page.
     *
     * @param  Collection<int, int>  $unmatchedSmsIds
     * @return array<int, array<string, mixed>>
     */
    public static function forUnmatchedSms(Collection $unmatchedSmsIds): array
    {
        if ($unmatchedSmsIds->isEmpty()) {
            return [];
        }

        $wanted = $unmatchedSmsIds->flip();
        $found = [];

        foreach (self::pendingCheckoutsWithFailures() as $pending) {
            foreach ($pending->checkout_data[self::DATA_KEY] ?? [] as $entry) {
                $smsId = (int) ($entry['sms_id'] ?? 0);

                if (! $wanted->has($smsId) || isset($found[$smsId])) {
                    continue;
                }

                $found[$smsId] = [
                    'unavailable_item' => $entry['unavailable_item'] ?? null,
                    'recorded_at' => $entry['recorded_at'] ?? null,
                    'order_number' => $pending->checkout_data['order_number'] ?? null,
                    'customer_name' => $pending->checkout_data['shipping_address']['name'] ?? null,
                    'customer_phone' => $pending->checkout_data['shipping_address']['phone'] ?? null,
                    'landing_page' => $pending->landing_page_id !== null,
                ];
            }
        }

        return $found;
    }

    public static function customerMessage(float $amount, string $transactionId, ?string $productName): string
    {
        $item = $productName ?? 'an item in your order';

        return 'We received your payment of ৳'.number_format($amount, 2)." (TrxID: {$transactionId}), but unfortunately, {$item} became unavailable before your order could be confirmed. Our team has been notified and will contact you shortly to arrange the next steps. We apologize for the inconvenience.";
    }

    /**
     * Same lock order as the payment submission (pending checkout, then
     * SMS). The SMS row lock serializes every attempt with this SMS, even
     * across two different pending checkouts, and because both locks come
     * before the first plain read, that read sees any entry committed by
     * the request that held the lock before us.
     *
     * @param  array<string, mixed>  $entry
     */
    private static function record(PendingCheckout $pending, ReceivedSms $sms, array $entry): bool
    {
        return DB::transaction(function () use ($pending, $sms, $entry): bool {
            $lockedPending = PendingCheckout::whereKey($pending->id)->lockForUpdate()->first();
            ReceivedSms::whereKey($sms->id)->lockForUpdate()->first();

            if ($lockedPending === null) {
                throw new \RuntimeException('Pending checkout no longer exists.');
            }

            $alreadyRecorded = self::pendingCheckoutsWithFailures()->contains(
                fn (PendingCheckout $other): bool => collect($other->checkout_data[self::DATA_KEY] ?? [])
                    ->contains(fn (array $existing): bool => (int) ($existing['sms_id'] ?? 0) === $sms->id),
            );

            if ($alreadyRecorded) {
                return false;
            }

            $data = $lockedPending->checkout_data;
            $data[self::DATA_KEY] = [...($data[self::DATA_KEY] ?? []), $entry];
            $lockedPending->update(['checkout_data' => $data]);

            return true;
        }, 3);
    }

    /**
     * Only rows that hold at least one failure entry — a handful at most.
     *
     * @return Collection<int, PendingCheckout>
     */
    private static function pendingCheckoutsWithFailures(): Collection
    {
        return PendingCheckout::query()
            ->where('checkout_data', 'like', '%"'.self::DATA_KEY.'"%')
            ->get(['id', 'landing_page_id', 'checkout_data']);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $context
     */
    private static function alert(PendingCheckout $pending, array $entry, array $context): void
    {
        $recipient = OrderNotificationRecipients::newOrder();

        if ($recipient === null) {
            Log::error('[PaidStockFailure] No alert recipient configured; the failure is only visible on Admin › Received SMS', $context);

            return;
        }

        try {
            Mail::to($recipient)->queue(new PaidOrderOutOfStockAlertMail(
                orderNumber: $pending->checkout_data['order_number'] ?? null,
                customerName: $pending->checkout_data['shipping_address']['name'] ?? null,
                customerPhone: $pending->checkout_data['shipping_address']['phone'] ?? null,
                provider: $entry['provider'],
                transactionId: $entry['transaction_id'],
                amount: $entry['amount'],
                unavailableItem: $entry['unavailable_item'],
                items: array_map(fn (array $item): array => [
                    'name' => (string) ($item['product_name'] ?? ''),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ], $pending->checkout_data['items'] ?? []),
                landingPage: $pending->landing_page_id !== null,
            ));
        } catch (Throwable $e) {
            Log::error('[PaidStockFailure] Alert email could not be queued; the failure is still shown on Admin › Received SMS', $context + SafeLogContext::exception($e));
        }
    }
}
