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
use Illuminate\Support\Facades\URL;

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
        // OPS-006: signed, not a plain preference route, so the link in
        // this specific email works for the customer who received it
        // without needing to log in, and can't be reused to unsubscribe
        // anyone else.
        return new Content(view: 'emails.cart.abandoned', with: [
            'unsubscribeUrl' => URL::signedRoute('cart.reminders.unsubscribe', ['user' => $this->incompleteOrder->user_id]),
        ]);
    }
}
