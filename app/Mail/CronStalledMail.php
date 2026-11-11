<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * OPS-002: deliberately NOT ShouldQueue — it reports that the queue's own
 * scheduler stopped, so it must be sent immediately (see CronHealth).
 */
class CronStalledMail extends Mailable
{
    public function __construct(public readonly int $minutesSilent) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ShoppyDrive: background tasks have stopped');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.cron-stalled');
    }
}
