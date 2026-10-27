<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Review $review,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your review has been published');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reviews.approved');
    }
}
