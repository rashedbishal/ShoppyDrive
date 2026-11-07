<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * BE-010: sent from the queue like every other email, so a mail outage can't
 * crash or hang the forgot-password page, and the answer takes the same time
 * whether or not the email has an account (SEC-015).
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
