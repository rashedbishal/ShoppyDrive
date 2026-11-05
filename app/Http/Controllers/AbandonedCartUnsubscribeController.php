<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * OPS-006: a signed link in AbandonedCartReminderMail, requiring no login
 * (the customer may not be signed in when they open the email) and no
 * extra confirmation step — this only ever turns future reminders off,
 * never anything destructive, so a scanner auto-opening it is low-stakes.
 */
class AbandonedCartUnsubscribeController extends Controller
{
    public function __invoke(User $user): RedirectResponse
    {
        $user->update(['abandoned_cart_reminders_enabled' => false]);

        return redirect()->route('home')->with('success', 'You will no longer receive cart reminder emails.');
    }
}
