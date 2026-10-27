<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $productName,
        public readonly ?string $sku,
        public readonly int $currentStock,
        public readonly int $threshold,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Low stock: {$this->productName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.low-stock');
    }
}
