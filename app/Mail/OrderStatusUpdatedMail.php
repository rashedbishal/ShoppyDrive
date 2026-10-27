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
 * Generic status-change notice for transitions that don't have their own
 * dedicated Mailable (shipped/delivered/cancelled do) — currently that
 * means "processing" and "refunded".
 */
class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your order {$this->order->order_number} status: ".ucfirst($this->order->status->value));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.status-updated');
    }
}
