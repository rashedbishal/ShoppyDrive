<?php

declare(strict_types=1);

/*
 * DISC-37: Admin › System Health (spatie/laravel-health). Mother admin only;
 * the package's own result pages are never exposed.
 */

use App\Models\User;
use App\Support\AdminSections;
use App\Support\Health\MotherAdminHealthNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Health\Notifications\CheckFailedNotification;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('local');
    $this->mother = createMotherAdmin();
});

test('before any run the mother admin sees an empty page', function () {
    actingAs($this->mother, 'admin')->get(route('admin.system-health.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system-health/index')
            ->where('lastRanAt', null)
            ->where('checks', []));
});

test('running the checks stores every registered check and the page shows them', function () {
    Notification::fake();

    actingAs($this->mother, 'admin')->post(route('admin.system-health.run'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Health checks run.');

    Storage::disk('local')->assertExists('health.json');

    actingAs($this->mother, 'admin')->get(route('admin.system-health.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->whereNot('lastRanAt', null)
            ->has('checks', 9)
            ->where('checks', fn ($checks) => collect($checks)->pluck('name')->sort()->values()->all() === [
                'Cache', 'Database', 'DatabaseSize', 'DebugMode', 'DiskSpace',
                'Environment', 'OptimizedApp', 'Queue', 'Schedule',
            ])
            ->has('checks.0', fn (Assert $check) => $check->hasAll(['name', 'label', 'status', 'summary', 'message'])));

    // "Run checks now" never emails; the scheduled run does.
    Notification::assertNothingSent();
});

test('other admins, even with every section granted, are refused', function () {
    $admin = createAdmin(['permissions' => AdminSections::sanitize(collect(AdminSections::SECTIONS)->map(fn () => ['view', 'create', 'update', 'delete'])->all())]);

    actingAs($admin, 'admin')->get(route('admin.system-health.index'))->assertForbidden();
    actingAs($admin, 'admin')->post(route('admin.system-health.run'))->assertForbidden();
    Storage::disk('local')->assertMissing('health.json');
});

test('customers and guests cannot reach the page', function () {
    $this->get(route('admin.system-health.index'))->assertRedirect();
    $this->post(route('admin.system-health.run'))->assertRedirect();

    actingAs(User::factory()->create())->get(route('admin.system-health.index'))->assertRedirect();
    Storage::disk('local')->assertMissing('health.json');
});

test('the package exposes no route of its own', function () {
    $packageRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getActionName(), 'Spatie\\Health\\'));

    expect($packageRoutes)->toBeEmpty();
    $this->get('/health')->assertNotFound();
    $this->get('/oh-dear-health-check-results')->assertNotFound();
});

test('a failed scheduled check emails the mother admin', function () {
    Notification::fake();

    // The testing environment fails the production-only checks (Environment).
    $this->artisan('health:check')->assertSuccessful();

    Notification::assertSentTimes(CheckFailedNotification::class, 1);
    expect((new MotherAdminHealthNotifiable)->routeNotificationForMail())->toBe($this->mother->email);
});
