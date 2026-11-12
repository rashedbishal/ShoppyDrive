<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to one address when it subscribes to the newsletter (or subscribes
 * again after unsubscribing), so the footer's promise — unsubscribe any time
 * via the link in our emails — holds from the very first email. Unlike a
 * campaign email (one shared BCC body, FSA-13), it carries the subscriber's
 * own token link, so a single click reaches their unsubscribe confirmation.
 */
class NewsletterWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subscriberEmail,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Thanks for subscribing to our newsletter');
    }

    /**
     * Lets mail apps show their own "Unsubscribe" button for this email.
     */
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.welcome',
            with: ['unsubscribeUrl' => $this->unsubscribeUrl()],
        );
    }

    private function unsubscribeUrl(): string
    {
        return route('newsletter.unsubscribe.confirm', [
            'email' => $this->subscriberEmail,
            'token' => $this->token,
        ]);
    }
}
