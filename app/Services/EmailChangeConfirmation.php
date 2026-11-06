<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EmailChangeCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * SEC-009: a verified customer's email changes only after a 6-digit code
 * sent to the CURRENT address is entered, so someone using a logged-in
 * session can't move the account to their own email and then reset the
 * password. The pending change lives in the session (plain values only):
 * it is tied to the browser that asked and ends with logout.
 */
class EmailChangeConfirmation
{
    public const SESSION_KEY = 'pending_email_change';

    public const CODE_TTL_MINUTES = 15;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    /**
     * Whether this change needs a code: only when the current address was
     * proven. An unverified address (e.g. a registration typo) can't
     * receive the code, protects nothing, and the new one must be verified.
     */
    public function requiresCode(User $user, string $newEmail): bool
    {
        return $user->hasVerifiedEmail() && $newEmail !== $user->email;
    }

    /**
     * Stores the pending change and mails a code to the current address,
     * unless one was sent for this same new address within the cooldown.
     */
    public function start(Request $request, User $user, string $newEmail): void
    {
        $pending = $this->pending($request);

        if ($pending !== null
            && $pending['email'] === $newEmail
            && now()->getTimestamp() - $pending['sent_at'] < self::RESEND_COOLDOWN_SECONDS) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'email' => $newEmail,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES)->getTimestamp(),
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ]);

        // A mail outage must not crash the profile page; the customer can
        // press Save again to resend once mail works.
        try {
            Mail::to($user->email)->send(new EmailChangeCodeMail($code, self::mask($newEmail)));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{user_id: int, email: string, code_hash: string, expires_at: int, sent_at: int, attempts: int}|null
     */
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ($pending['user_id'] ?? null) !== $request->user()?->id) {
            return null;
        }

        return $pending;
    }

    /**
     * Checks the code. Returns the confirmed new email, or an error message.
     *
     * @return array{email: string}|array{error: string}
     */
    public function verify(Request $request, string $code): array
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return ['error' => 'There is no email change waiting to be confirmed. Please enter the new email again.'];
        }

        if ($pending['expires_at'] < now()->getTimestamp()) {
            $this->cancel($request);

            return ['error' => 'This code has expired. Please enter the new email again to get a new code.'];
        }

        if (! Hash::check($code, $pending['code_hash'])) {
            $pending['attempts']++;

            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                $this->cancel($request);

                return ['error' => 'Too many wrong codes. Please enter the new email again to get a new code.'];
            }

            $request->session()->put(self::SESSION_KEY, $pending);

            return ['error' => 'That code is not correct.'];
        }

        $this->cancel($request);

        return ['email' => $pending['email']];
    }

    public function cancel(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /** a***@mail.com */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 1).'***@'.$domain;
    }
}
