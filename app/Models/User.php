<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\VerifyEmailCodeMail;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'has_password',
        'phone',
        'avatar',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
    ];

    /**
     * Minimum seconds between two verification codes being issued. Also
     * drives the "resend available in Xs" countdown shown on the frontend.
     */
    public const VERIFICATION_CODE_RESEND_COOLDOWN_SECONDS = 60;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'email_verification_code_sent_at' => 'datetime',
            'password' => 'hashed',
            'has_password' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    /**
     * Send a 6-digit verification code by email instead of Laravel's
     * default signed-link notification.
     *
     * Idempotent within the cooldown window: whatever triggers this twice
     * in quick succession (a double form submit, a retried request, a
     * persistent-worker event-listener quirk) must not fire a second email
     * and silently invalidate the code the user already has in their
     * inbox — so a call within the cooldown is a no-op.
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->email_verification_code_sent_at
            && $this->email_verification_code_sent_at->addSeconds(self::VERIFICATION_CODE_RESEND_COOLDOWN_SECONDS)->isFuture()) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_code_expires_at' => now()->addMinutes(15),
            'email_verification_code_sent_at' => now(),
        ])->save();

        // A transient mail outage must not crash registration/login — log it
        // and let the user retry via "Resend" once mail is working again.
        try {
            Mail::to($this->email)->send(new VerifyEmailCodeMail($code));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * When the "Resend Code" button becomes usable again, or null if a
     * code was never sent / the cooldown has already elapsed.
     */
    public function verificationCodeResendAvailableAt(): ?CarbonInterface
    {
        if (! $this->email_verification_code_sent_at) {
            return null;
        }

        $availableAt = $this->email_verification_code_sent_at->addSeconds(self::VERIFICATION_CODE_RESEND_COOLDOWN_SECONDS);

        return $availableAt->isFuture() ? $availableAt : null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }
}
