<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Admin extends Authenticatable
{
    use LogsActivity, Notifiable;

    /** The only attributes ever written to the Audit Trail. */
    private const AUDITED_ATTRIBUTES = ['name', 'email', 'is_mother', 'permissions'];

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
     * An explicit allow-list rather than `Auditable`'s `logFillable()`, so a
     * password hash can never reach the log. A password change is recorded
     * on its own by `logPasswordChange()`, without any value.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logOnly(self::AUDITED_ATTRIBUTES)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Admin {$eventName}: {$this->auditLabel()}".$this->changedAttributesSuffix($eventName));
    }

    /**
     * Adds what was actually granted and revoked to an update that changed
     * permissions, alongside the full before/after in `old`/`attributes`.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $attributes = $activity->properties->get('attributes', []);

        if ($eventName !== 'updated' || ! array_key_exists('permissions', $attributes)) {
            return;
        }

        $before = $activity->properties->get('old', [])['permissions'] ?? [];
        $after = $attributes['permissions'] ?? [];

        $activity->properties = $activity->properties->put('permission_changes', [
            'granted' => self::missingAbilities($after, $before),
            'revoked' => self::missingAbilities($before, $after),
        ]);
    }

    /**
     * Records that this admin's password changed, never the value. Call it
     * where a person changes a password, not from a model event: the guard
     * also rewrites the hash at login when the hashing cost changes.
     */
    public function logPasswordChange(): void
    {
        activity('admin')
            ->performedOn($this)
            ->event('password_changed')
            ->log("Admin password changed: {$this->auditLabel()}");
    }

    private function auditLabel(): string
    {
        return "{$this->name} ({$this->email})";
    }

    private function changedAttributesSuffix(string $eventName): string
    {
        if ($eventName !== 'updated') {
            return '';
        }

        $changed = array_intersect(self::AUDITED_ATTRIBUTES, array_keys($this->getChanges()));

        return $changed === [] ? '' : ' — changed '.implode(', ', $changed);
    }

    /**
     * @param  array<string, array<int, string>>  $from
     * @param  array<string, array<int, string>>  $other
     * @return array<string, array<int, string>>
     */
    private static function missingAbilities(array $from, array $other): array
    {
        $missing = [];

        foreach ($from as $section => $abilities) {
            $difference = array_values(array_diff($abilities, $other[$section] ?? []));

            if ($difference !== []) {
                $missing[$section] = $difference;
            }
        }

        return $missing;
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
