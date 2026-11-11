<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Shop-side alert that an order was cancelled (by an admin or by the
 * customer). Sent to the "order cancellation email" in Admin > Settings; the
 * customer's own notice is OrderCancelledMail.
 */
class CancelledOrderAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order Cancelled — {$this->order->order_number} (৳".number_format($this->order->total, 2).')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.cancelled-order');
    }
}
