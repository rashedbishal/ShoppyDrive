<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'token',
        'refresh_token',
        'token_expires_at',
    ];

    /**
     * SEC-004: nothing in the app ever reads these back (Socialite tokens
     * are used once, at login, and never stored for reuse) — but with no
     * $hidden, they appeared in the admin customer page
     * (Admin\CustomerController::show()'s raw ->load('socialAccounts'))
     * and, more subtly, in the shared `auth.user` prop on every page: that
     * share reads the same User instance Auth::user() returns, and
     * Settings\ConnectionsController::edit() lazy-loads this exact
     * relation onto it — its own `connections` prop already maps to a
     * safe shape, but the shared prop would still have carried the loaded,
     * unhidden models.
     *
     * @var list<string>
     */
    protected $hidden = ['token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
