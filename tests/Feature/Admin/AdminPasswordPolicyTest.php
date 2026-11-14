<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Support\PasswordPolicy;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Pest\Laravel\actingAs;

test('SEC-010: admin account creation accepts the non-production 8-character floor', function () {
    // PasswordPolicy::adminRule() falls back to Laravel's own
    // Password::min(8) outside production, exactly like Password::default(),
    // so an 8-character password is accepted here. The production-only
    // 12-character admin minimum (DISC-33) is covered by the tests below.
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');

    $this->post(route('admin.admins.store'), [
        'name' => 'New Staff',
        'email' => 'ok-pw@example.com',
        'password' => 'eight888',
        'password_confirmation' => 'eight888',
    ])->assertSessionDoesntHaveErrors('password');

    expect(Admin::where('email', 'ok-pw@example.com')->exists())->toBeTrue();
});

test('SEC-010: an admin cannot change their own password without confirming the current one', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $this->put(route('admin.admins.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});

test('SEC-010: the wrong current password refuses a self password change', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $this->put(route('admin.admins.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'current_password' => 'totally-wrong',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});

test('SEC-010: the correct current password allows a self password change', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $this->put(route('admin.admins.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionDoesntHaveErrors();

    expect(Hash::check('a-brand-new-password', $admin->refresh()->password))->toBeTrue();
});

test('SEC-010: the mother resetting a different admin\'s password never needs the mother\'s own current password', function () {
    $mother = createMotherAdmin();
    $staff = createAdmin();
    actingAs($mother, 'admin');

    $this->put(route('admin.admins.update', $staff), [
        'name' => $staff->name,
        'email' => $staff->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'permissions' => $staff->permissions ?? [],
    ])->assertSessionDoesntHaveErrors();

    expect(Hash::check('a-brand-new-password', $staff->refresh()->password))->toBeTrue();
});

// ── DISC-33: customers 8+ characters, admins 12+ (production) ───────────────

/**
 * Runs $callback in production mode with the breach check faked as "not
 * leaked", so no request leaves the test.
 */
function inProductionWithoutBreachCheck(Closure $callback): void
{
    app()->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
    {
        public function verify($data): bool
        {
            return true;
        }
    });
    app()->detectEnvironment(fn () => 'production');

    try {
        $callback();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
}

function passesPasswordRule(string $password, Password $rule): bool
{
    return Validator::make(['password' => $password], ['password' => [$rule]])->passes();
}

test('DISC-33: in production a customer password needs 8+ characters, an admin password 12+', function () {
    inProductionWithoutBreachCheck(function (): void {
        // 10 characters, meets every other rule.
        expect(passesPasswordRule('Abcdefg1!x', Password::default()))->toBeTrue()
            ->and(passesPasswordRule('Abcdefg1!x', PasswordPolicy::adminRule()))->toBeFalse()
            // 12 characters passes both.
            ->and(passesPasswordRule('Abcdefghi1!x', PasswordPolicy::adminRule()))->toBeTrue()
            // 7 characters fails both.
            ->and(passesPasswordRule('Abcde1!', Password::default()))->toBeFalse();
    });
});

test('DISC-33: in production the other rules still apply to customers', function (string $password) {
    inProductionWithoutBreachCheck(function () use ($password): void {
        expect(passesPasswordRule($password, Password::default()))->toBeFalse();
    });
})->with([
    'no upper case' => 'abcdefg1!',
    'no lower case' => 'ABCDEFG1!',
    'no number' => 'Abcdefgh!',
    'no symbol' => 'Abcdefgh1',
]);

test('DISC-33: in production the admin requirement list says 12 characters, the customer list 8', function () {
    inProductionWithoutBreachCheck(function (): void {
        expect(PasswordPolicy::adminRequirements()[0])->toBe('At least 12 characters')
            ->and(PasswordPolicy::requirements()[0])->toBe('At least 8 characters')
            ->and(array_slice(PasswordPolicy::adminRequirements(), 1))->toBe(array_slice(PasswordPolicy::requirements(), 1));
    });
});

test('DISC-33: the admin create and edit pages show the admin requirement list', function () {
    $mother = createMotherAdmin();
    $staff = createAdmin();
    actingAs($mother, 'admin');

    $this->get(route('admin.admins.create'))
        ->assertInertia(fn ($page) => $page->where('password_requirements', PasswordPolicy::adminRequirements()));
    $this->get(route('admin.admins.edit', $staff))
        ->assertInertia(fn ($page) => $page->where('password_requirements', PasswordPolicy::adminRequirements()));
});

test('DISC-33: admin passwords are validated with the admin rule everywhere they are set', function () {
    $controller = (string) file_get_contents(app_path('Http/Controllers/Admin/AdminController.php'));
    $command = (string) file_get_contents(app_path('Console/Commands/CreateMotherAdminCommand.php'));

    expect(substr_count($controller, 'PasswordPolicy::adminRule()'))->toBe(2)
        ->and($controller)->not->toContain('Password::default()')
        ->and($command)->toContain('PasswordPolicy::adminRule()')
        ->and($command)->not->toContain('Password::default()');
});
