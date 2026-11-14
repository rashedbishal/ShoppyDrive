<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Models\Admin;
use Spatie\Health\Notifications\Notifiable;

/**
 * DISC-37: System Health failure emails go to the mother admin's login
 * email (the same address new-order alerts fall back to), so no separate
 * recipient has to be configured in .env.
 */
class MotherAdminHealthNotifiable extends Notifiable
{
    public function routeNotificationForMail(): string|array
    {
        return Admin::where('is_mother', true)->value('email') ?: (string) config('health.notifications.mail.to');
    }
}
