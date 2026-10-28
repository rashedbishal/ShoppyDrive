<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    // is_mother is deliberately excluded — it must only ever be set via
    // forceFill() (see CreateMotherAdminCommand, the only legitimate place
    // it's assigned), never through mass assignment, so no request path
    // (now or in the future) can self-promote an admin to super-admin.
    //
    // permissions IS mass-assignable: AdminController is the only place
    // that sets it, always through AdminSections::sanitize() and always
    // gated to the mother admin acting on a non-mother target (see
    // AdminController::store()/update()) — so this doesn't open a
    // self-escalation path the way is_mother would.
    protected $fillable = ['name', 'email', 'password', 'permissions'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_mother' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /**
     * The mother admin bypasses this entirely — every other admin starts
     * with no section access (`permissions` is null) until the mother
     * grants some via Admin > Admins. See AdminSections for the section
     * catalog and which abilities (beyond the always-implied `view`) each
     * one supports.
     */
    public function canAccess(string $section, string $ability = 'view'): bool
    {
        if ($this->is_mother) {
            return true;
        }

        return in_array($ability, $this->permissions[$section] ?? [], true);
    }
}
