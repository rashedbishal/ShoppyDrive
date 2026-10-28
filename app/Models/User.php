<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\VerifyEmailCodeMail;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    // role is deliberately excluded — no legitimate runtime path needs to
    // mass-assign it (new users get the column's own 'customer' default; see
    // database/migrations/2026_04_28_063542_update_users_table_for_ecommerce_features.php),
    // so keeping it out of $fillable closes the door on a future form/
    // controller change ever accidentally forwarding a client-supplied
    // 'role' value through to a User::create()/update() call.
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'has_password',
        'phone',
        'avatar',
        'referred_by_id',
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
            'login_streak_date' => 'date',
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

    /**
     * The existing customer whose referral link this user signed up through, if any.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    /**
     * Customers who signed up through this user's referral link.
     */
    public function referredUsers(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_id');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(ReferralPointTransaction::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->referral_code ??= self::generateUniqueReferralCode();
        });
    }

    private static function generateUniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(12));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }
}
