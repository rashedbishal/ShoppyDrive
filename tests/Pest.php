<?php

use App\Models\Admin;
use App\Support\AdminSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * is_mother is deliberately not mass-assignable (see Admin::$fillable) —
 * tests that need a mother admin must set it via forceFill(), exactly
 * like the one legitimate place in the app that does (CreateMotherAdminCommand).
 *
 * @param  array<string, mixed>  $attributes
 */
function createMotherAdmin(array $attributes = []): Admin
{
    $admin = Admin::create(array_merge([
        'name' => 'Mother Admin',
        'email' => 'mother-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ], $attributes));

    $admin->forceFill(['is_mother' => true])->save();

    return $admin;
}

/**
 * A regular (non-mother) admin with every section fully granted. New admins
 * default to no section access at all (see AdminSections) once the mother
 * can hand-pick permissions, but almost every existing feature test here
 * predates that and just needs "a logged-in admin who can do anything a
 * non-mother admin is allowed to do" — tests that specifically exercise
 * restricted access build their own Admin with a narrower `permissions`
 * array instead of using this helper.
 *
 * @param  array<string, mixed>  $attributes
 */
function createAdmin(array $attributes = []): Admin
{
    $permissions = collect(AdminSections::SECTIONS)
        ->keys()
        ->mapWithKeys(fn (string $section): array => [$section => AdminSections::abilitiesFor($section)])
        ->all();

    return Admin::create(array_merge([
        'name' => 'Admin User',
        'email' => 'admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'permissions' => $permissions,
    ], $attributes));
}
