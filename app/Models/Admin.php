<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Admin extends Authenticatable
{
    use LogsActivity;
    use Notifiable;

    // is_mother and can_view_audit_trail are deliberately excluded — both
    // must only ever be set via forceFill() (is_mother: see
    // CreateMotherAdminCommand; can_view_audit_trail: see AdminController,
    // only when the mother admin is the one filling the request), never
    // through mass assignment, so no request path (now or in the future)
    // can self-promote an admin to super-admin or grant itself audit access.
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_mother' => 'boolean',
            'can_view_audit_trail' => 'boolean',
        ];
    }

    /**
     * Logged explicitly rather than via the shared `Auditable::logFillable()`
     * default — `can_view_audit_trail` (like `is_mother`) is deliberately
     * not in $fillable, but a permission grant/revoke is exactly the kind
     * of admin-account change this audit trail exists to catch, so it's
     * named here instead of relying on fillable attributes alone.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logOnly(['name', 'email', 'can_view_audit_trail'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => sprintf(
                'Admin account %s: %s (%s)',
                $eventName,
                $this->name,
                $this->email,
            ));
    }
}
