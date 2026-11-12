<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * DISC-19: a customer paid by bKash/Nagad but an item sold out before the
 * order could be created. Plain values only, so the email still renders if
 * the pending checkout is gone by the time the queue sends it.
 */
class PaidOrderOutOfStockAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, quantity: int}>  $items
     */
    public function __construct(
        public readonly ?string $orderNumber,
        public readonly ?string $customerName,
        public readonly ?string $customerPhone,
        public readonly string $provider,
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly ?string $unavailableItem,
        public readonly array $items,
        public readonly bool $landingPage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Action needed — paid but not ordered: ৳'.number_format($this->amount, 2)." (TrxID {$this->transactionId})");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.paid-order-out-of-stock');
    }
}
