<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class NewsletterProductMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $emailSubject,
        public readonly Product $product,
        public readonly ?string $selectedImage = null,
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
        return new Content(
            view: 'emails.newsletter.product-promotion',
            with: [
                // Full addresses: an email has no site to resolve "/storage/…" against.
                'imageUrl' => $this->absoluteUrl($this->selectedImage ?? $this->product->primaryImagePath()),
                'productUrl' => route('product.show', $this->product),
            ],
        );
    }

    private function absoluteUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return preg_match('#^https?://#i', $path) === 1 ? $path : url($path);
    }

    /** @return array<int, never> */
    public function attachments(): array
    {
        return [];
    }
}
