<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

function auditedMother(): Admin
{
    $mother = new Admin(['name' => 'Mother', 'email' => 'mother@example.com', 'password' => 'password']);
    $mother->forceFill(['is_mother' => true])->saveQuietly();

    return $mother;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function auditedStaff(array $attributes = []): Admin
{
    return Admin::withoutEvents(fn () => Admin::create(array_merge([
        'name' => 'Staff',
        'email' => 'staff@example.com',
        'password' => 'password',
    ], $attributes)));
}

function adminActivity(string $event): ?Activity
{
    return Activity::query()->where('subject_type', Admin::class)->where('event', $event)->latest('id')->first();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function staffPayload(Admin $admin, array $overrides = []): array
{
    return array_merge([
        'name' => $admin->name,
        'email' => $admin->email,
        'permissions' => $admin->permissions ?? [],
    ], $overrides);
}

test('creating an admin records the acting admin, the target and the granted permissions', function () {
    $mother = auditedMother();

    actingAs($mother, 'admin')->post(route('admin.admins.store'), [
        'name' => 'New Staff',
        'email' => 'new@example.com',
        'password' => 'secret-password-1',
        'password_confirmation' => 'secret-password-1',
        'permissions' => ['orders' => ['update']],
    ])->assertRedirect(route('admin.admins.index'));

    $created = Admin::where('email', 'new@example.com')->firstOrFail();
    $activity = adminActivity('created');

    expect($activity->causer->is($mother))->toBeTrue();
    expect($activity->subject->is($created))->toBeTrue();
    expect($activity->log_name)->toBe('admin');
    expect($activity->description)->toContain('new@example.com');
    expect($activity->properties['attributes']['permissions'])->toBe(['orders' => ['view', 'update']]);
});

test('changing a name and email records the before and after values', function () {
    $mother = auditedMother();
    $staff = auditedStaff();

    actingAs($mother, 'admin')->put(route('admin.admins.update', $staff), staffPayload($staff, [
        'name' => 'Renamed',
        'email' => 'renamed@example.com',
    ]))->assertRedirect();

    $activity = adminActivity('updated');

    expect($activity->causer->is($mother))->toBeTrue();
    expect($activity->subject->is($staff))->toBeTrue();
    expect($activity->description)->toContain('changed name, email');
    expect($activity->properties['old'])->toMatchArray(['name' => 'Staff', 'email' => 'staff@example.com']);
    expect($activity->properties['attributes'])->toMatchArray(['name' => 'Renamed', 'email' => 'renamed@example.com']);
});

test('changing permissions records what was granted and revoked', function () {
    $mother = auditedMother();
    $staff = auditedStaff(['permissions' => ['orders' => ['view', 'update'], 'products' => ['view']]]);

    actingAs($mother, 'admin')->put(route('admin.admins.update', $staff), staffPayload($staff, [
        'permissions' => ['orders' => ['view'], 'coupons' => ['view', 'create']],
    ]))->assertRedirect();

    $activity = adminActivity('updated');

    expect($activity->description)->toContain('changed permissions');
    expect($activity->properties['old']['permissions'])->toBe(['orders' => ['view', 'update'], 'products' => ['view']]);
    expect($activity->properties['permission_changes'])->toBe([
        'granted' => ['coupons' => ['view', 'create']],
        'revoked' => ['orders' => ['update'], 'products' => ['view']],
    ]);
});

test('changing only a password records that it changed and nothing about the value', function () {
    $mother = auditedMother();
    $staff = auditedStaff();
    $oldHash = $staff->password;

    actingAs($mother, 'admin')->put(route('admin.admins.update', $staff), staffPayload($staff, [
        'password' => 'secret-password-2',
        'password_confirmation' => 'secret-password-2',
    ]))->assertRedirect();

    $staff->refresh();
    $activity = adminActivity('password_changed');
    $logged = Activity::query()->get()->toJson();

    expect($activity->causer->is($mother))->toBeTrue();
    expect($activity->subject->is($staff))->toBeTrue();
    expect(adminActivity('updated'))->toBeNull();
    expect($logged)->not->toContain('secret-password-2');
    expect($logged)->not->toContain($staff->password);
    expect($logged)->not->toContain($oldHash);
});

test('an admin updating their own account is recorded as both actor and target', function () {
    $staff = auditedStaff();

    actingAs($staff, 'admin')->put(route('admin.admins.update', $staff), staffPayload($staff, [
        'name' => 'Self Renamed',
    ]))->assertRedirect();

    $activity = adminActivity('updated');

    expect($activity->causer->is($staff))->toBeTrue();
    expect($activity->subject->is($staff))->toBeTrue();
});

test('deleting an admin keeps who was deleted, and by whom, after the row is gone', function () {
    $mother = auditedMother();
    $staff = auditedStaff(['permissions' => ['orders' => ['view']]]);

    actingAs($mother, 'admin')->delete(route('admin.admins.destroy', $staff))->assertRedirect(route('admin.admins.index'));

    $activity = adminActivity('deleted');

    expect(Admin::find($staff->id))->toBeNull();
    expect($activity->causer->is($mother))->toBeTrue();
    expect((int) $activity->subject_id)->toBe($staff->id);
    expect($activity->description)->toContain('staff@example.com');
    expect($activity->properties['old']['permissions'])->toBe(['orders' => ['view']]);
});

test('saving an unchanged admin writes no audit entry', function () {
    $mother = auditedMother();
    $staff = auditedStaff();

    actingAs($mother, 'admin')->put(route('admin.admins.update', $staff), staffPayload($staff))->assertRedirect();

    expect(Activity::query()->where('subject_type', Admin::class)->count())->toBe(0);
});

test('operations that fail write no audit entry', function (Closure $attempt) {
    $mother = auditedMother();
    $staff = auditedStaff();
    $otherStaff = auditedStaff(['email' => 'other@example.com']);

    $attempt($mother, $staff, $otherStaff);

    expect(Activity::query()->where('subject_type', Admin::class)->count())->toBe(0);
})->with([
    'creating without permission' => [function (Admin $mother, Admin $staff) {
        actingAs($staff, 'admin')->post(route('admin.admins.store'), [
            'name' => 'Nope', 'email' => 'nope@example.com', 'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1',
        ])->assertForbidden();
    }],
    'creating with invalid data' => [function (Admin $mother) {
        actingAs($mother, 'admin')->post(route('admin.admins.store'), ['name' => 'Nope'])->assertSessionHasErrors(['email', 'password']);
    }],
    'updating someone else without permission' => [function (Admin $mother, Admin $staff, Admin $otherStaff) {
        actingAs($staff, 'admin')->put(route('admin.admins.update', $otherStaff), staffPayload($otherStaff, ['name' => 'Nope']))->assertForbidden();
    }],
    'updating to a taken email' => [function (Admin $mother, Admin $staff, Admin $otherStaff) {
        actingAs($mother, 'admin')->put(route('admin.admins.update', $staff), staffPayload($staff, ['email' => $otherStaff->email]))->assertSessionHasErrors('email');
    }],
    'changing a password without confirming it' => [function (Admin $mother, Admin $staff) {
        actingAs($mother, 'admin')->put(route('admin.admins.update', $staff), staffPayload($staff, ['password' => 'secret-password-1']))->assertSessionHasErrors('password');
    }],
    'deleting without permission' => [function (Admin $mother, Admin $staff, Admin $otherStaff) {
        actingAs($staff, 'admin')->delete(route('admin.admins.destroy', $otherStaff));
    }],
    'deleting yourself' => [function (Admin $mother) {
        actingAs($mother, 'admin')->delete(route('admin.admins.destroy', $mother));
    }],
]);

test('a non-mother admin cannot change their own permissions through the audited update', function () {
    $staff = auditedStaff(['permissions' => ['orders' => ['view']]]);

    actingAs($staff, 'admin')->put(route('admin.admins.update', $staff), staffPayload($staff, [
        'permissions' => ['orders' => ['view'], 'products' => ['view', 'delete']],
    ]))->assertRedirect();

    expect($staff->refresh()->permissions)->toBe(['orders' => ['view']]);
    expect(Activity::query()->where('subject_type', Admin::class)->count())->toBe(0);
});

test('replacing the mother admin from the console is recorded without any credential', function () {
    $mother = auditedMother();

    artisan('admin:create-mother', [
        '--force' => true,
        '--name' => 'New Mother',
        '--email' => 'new-mother@example.com',
        '--password' => 'secret-password-3',
    ])->assertSuccessful();

    expect(adminActivity('updated')->subject->is($mother))->toBeTrue();
    expect(adminActivity('updated')->causer)->toBeNull();
    expect(adminActivity('password_changed'))->not->toBeNull();
    expect(Activity::query()->get()->toJson())->not->toContain('secret-password-3');
    expect(Hash::check('secret-password-3', $mother->refresh()->password))->toBeTrue();
});
