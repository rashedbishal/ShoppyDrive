<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\VerifyEmailCodeMail;
use App\Notifications\QueuedResetPassword;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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
        'abandoned_cart_reminders_enabled',
    ];

    // FSA-17: both columns hold Laravel-encrypted ciphertext written by
    // Laravel\Fortify\TwoFactorAuthenticatable (the trait itself never adds
    // them to $hidden — that's the consuming app's responsibility). $hidden
    // only affects toArray()/toJson() serialization, never direct property
    // access, so this doesn't touch anything Fortify's own trait methods do.
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'anonymized_at' => 'datetime',
            'abandoned_cart_reminders_enabled' => 'boolean',
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
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        if ($this->email_verification_code_sent_at
            && $this->email_verification_code_sent_at->addSeconds(self::VERIFICATION_CODE_RESEND_COOLDOWN_SECONDS)->isFuture()) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'email_verification_code' => self::hashEmailVerificationCode($code, $this->email),
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
     * SEC-003: hashes a code together with the address it is mailed to, so
     * it can only ever prove that address. Every way an email can change
     * is covered without each one having to remember to clear the code.
     */
    public static function hashEmailVerificationCode(string $code, string $email): string
    {
        return Hash::make(self::emailVerificationCodeSecret($code, $email));
    }

    /**
     * Verifies this account's current address if the code matches. The row
     * is re-read under a lock so a concurrent email change can't slip
     * between the check and the write. Returns true only when this call
     * did the verifying.
     */
    public function consumeEmailVerificationCode(string $code): bool
    {
        $consumed = DB::transaction(function () use ($code): bool {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($locked->hasVerifiedEmail() || ! $locked->hasValidEmailVerificationCode($code)) {
                return false;
            }

            $locked->forceFill([
                'email_verified_at' => now(),
                'email_verification_code' => null,
                'email_verification_code_expires_at' => null,
                'email_verification_code_sent_at' => null,
            ])->save();

            return true;
        });

        $this->refresh();

        return $consumed;
    }

    /**
     * The only way a customer's email changes. The new address is unproven,
     * so verification and any outstanding code are cleared. Locks the row
     * so a verification landing mid-change can't carry over to the new
     * address, which an in-memory "already null" value would miss.
     */
    public function changeEmailTo(string $newEmail): void
    {
        DB::transaction(function () use ($newEmail): void {
            static::whereKey($this->id)->lockForUpdate()->firstOrFail()->forceFill([
                'email' => $newEmail,
                'email_verified_at' => null,
                'email_verification_code' => null,
                'email_verification_code_expires_at' => null,
                'email_verification_code_sent_at' => null,
            ])->save();
        });

        $this->refresh();
    }

    private function hasValidEmailVerificationCode(string $code): bool
    {
        if (! $this->email_verification_code
            || ! $this->email_verification_code_expires_at
            || $this->email_verification_code_expires_at->isPast()) {
            return false;
        }

        return Hash::check(self::emailVerificationCodeSecret($code, $this->email), $this->email_verification_code);
    }

    /**
     * Digested first because bcrypt ignores everything past 72 bytes, which
     * would otherwise drop the end of a long address.
     */
    private static function emailVerificationCodeSecret(string $code, string $email): string
    {
        return hash('sha256', $email.'|'.$code);
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

    /**
     * DB-001 (L4): whether deleting this account would destroy history the
     * business keeps — orders (trashed included), money/loyalty/coupon/return
     * records, reviews, or other customers' referral links to it. Such
     * accounts are anonymized, never hard-deleted; the RESTRICT foreign keys
     * make the database refuse it as well.
     */
    public function hasRetainedHistory(): bool
    {
        return Order::withTrashed()->where('user_id', $this->id)->exists()
            || ReferralPointTransaction::where('user_id', $this->id)->exists()
            || CouponUse::where('user_id', $this->id)->exists()
            || ReturnRequest::where('user_id', $this->id)->exists()
            || Review::withTrashed()->where('user_id', $this->id)->exists()
            // Customers they referred keep their referral link (and pending
            // referral discount) — deleting this account would clear it.
            || self::withTrashed()->where('referred_by_id', $this->id)->exists();
    }

    /**
     * Redact personal information in place and revoke account access, while
     * preserving the row itself and every order/payment/review/coupon/
     * loyalty record that references it — approved as the Option A
     * remediation for DB-001 (customer hard-delete destroying financial
     * history). The email is deliberately left untouched per that approval.
     * `$causer` is the admin who did it, or null when customers delete their
     * own account (DB-001, L4).
     *
     * Returns false (no-op) if this account was already anonymized, so a
     * repeated admin request can't double-log or re-randomize the already
     * anonymized fields.
     */
    public function anonymize(?Admin $causer = null): bool
    {
        return DB::transaction(function () use ($causer): bool {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if ($locked->anonymized_at !== null) {
                return false;
            }

            $locked->socialAccounts()->delete();

            $locked->addresses()->update([
                'name' => '[redacted]',
                'phone' => '[redacted]',
                'address_line1' => '[redacted]',
                'address_line2' => null,
                'city' => '[redacted]',
                'state' => '[redacted]',
                'postal_code' => null,
            ]);

            $locked->forceFill([
                'name' => "Deleted Customer #{$locked->id}",
                'phone' => null,
                'avatar' => null,
                'password' => Hash::make(Str::password(24)),
                'remember_token' => Str::random(60),
                'anonymized_at' => now(),
            ])->save();

            DB::table('sessions')->where('user_id', $locked->id)->delete();

            if ($causer !== null) {
                activity('admin')
                    ->causedBy($causer)
                    ->performedOn($locked)
                    ->event('customer.anonymized')
                    ->log("Admin {$causer->name} ({$causer->email}) anonymized customer #{$locked->id}.");
            } else {
                activity('customer')
                    ->causedBy($locked)
                    ->performedOn($locked)
                    ->event('customer.self_anonymized')
                    ->log("Customer #{$locked->id} deleted their account; it was anonymized because it has order or financial history.");
            }

            return true;
        });
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
