<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SEC-009: sent to the account's CURRENT address to confirm an email
 * change. Sent immediately, like VerifyEmailCodeMail, since the customer
 * is waiting on the page for it.
 */
class EmailChangeCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $maskedNewEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your ShoppyDrive email change');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth.email-change-code');
    }
}
