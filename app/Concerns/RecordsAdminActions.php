<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * For records customers create, where model-level auditing would log their
 * activity and personal data. Only the admin's own action goes to the Audit
 * Trail, identified by ID. Call it in the action's transaction.
 */
trait RecordsAdminActions
{
    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordAdminAction(string $event, string $description, ?Model $subject = null, array $properties = []): void
    {
        $activity = activity('admin')->causedBy(Auth::guard('admin')->user())->event($event)->withProperties($properties);

        if ($subject !== null) {
            $activity->performedOn($subject);
        }

        $activity->log($description);
    }

    /** Enough to recognise a deleted address later, without keeping it. */
    private function maskedEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        return Str::mask($email, '*', 1, strpos($email, '@') - 1);
    }
}
