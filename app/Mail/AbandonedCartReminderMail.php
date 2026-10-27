<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\IncompleteOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly IncompleteOrder $incompleteOrder,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You left something in your cart');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.cart.abandoned');
    }
}
