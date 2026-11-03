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
 * FSA-14: the generic link in newsletter campaign emails can't carry a
 * per-recipient token (FSA-13's shared BCC body is identical for every
 * recipient in a chunk), so this is sent separately, to one address at a
 * time, only after that address is confirmed to be an active subscriber.
 */
class NewsletterUnsubscribeLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subscriberEmail,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Manage your newsletter subscription');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.unsubscribe-link',
            with: [
                'unsubscribeUrl' => route('newsletter.unsubscribe.confirm', [
                    'email' => $this->subscriberEmail,
                    'token' => $this->token,
                ]),
            ],
        );
    }
}
