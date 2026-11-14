<?php

declare(strict_types=1);

namespace App\Mail;

use App\Concerns\AttachesOrderInvoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderDeliveredMail extends Mailable implements ShouldQueue
{
    use AttachesOrderInvoice, Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your order {$this->order->order_number} has been delivered");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.delivered');
    }

    /**
     * DISC-38: the PDF invoice (none for a cancelled/refunded order).
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->orderInvoiceAttachments($this->order);
    }
}
