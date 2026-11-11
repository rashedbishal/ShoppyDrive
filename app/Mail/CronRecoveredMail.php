<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * OPS-002: sent right away, like CronStalledMail.
 */
class CronRecoveredMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ShoppyDrive: background tasks are running again');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.cron-recovered');
    }
}
