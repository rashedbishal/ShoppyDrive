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
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_mother' => 'boolean',
        ];
    }
}
