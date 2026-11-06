<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class NewsletterCustomMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $emailSubject,
        public readonly string $body,
        public readonly array $attachmentPaths = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    /**
     * OPS-004: lets mail apps offer "unsubscribe". One shared BCC message
     * can't carry a per-person link, so this is the generic manage page.
     */
    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.route('newsletter.manage').'>']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter.custom');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (string $path) => Attachment::fromStorageDisk('local', $path),
            $this->attachmentPaths,
        );
    }
}
