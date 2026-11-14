<?php

declare(strict_types=1);

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Support\CronHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Owner request 2026-11-14: System Monitor showed "7 jobs waiting" next to
 * "Nothing is waiting" because the 7 jobs set aside on the `held-backlog`
 * queue were counted with the real queue. Parked jobs are now shown apart,
 * and the mother admin can delete them with her password.
 */

function parkedJob(string $queue = 'held-backlog', string $class = 'App\\Mail\\OrderPlacedMail', ?int $reservedAt = null): int
{
    return DB::table('jobs')->insertGetId([
        'queue' => $queue,
        'payload' => json_encode(['displayName' => $class, 'data' => ['command' => 'secret-customer@example.com']]),
        'attempts' => 0,
        'reserved_at' => $reservedAt,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->subDays(3)->getTimestamp(),
    ]);
}

test('parked jobs are counted apart from the jobs the worker will run', function () {
    parkedJob();
    parkedJob();
    parkedJob('default');

    expect(CronHealth::snapshot())->toMatchArray(['pending_jobs' => 1, 'parked_jobs' => 2]);
});

test('the monitor lists each parked job by class name only, never its data', function () {
    $id = parkedJob();
    parkedJob('default');
    actingAs(createAdmin(), 'admin');

    $this->get(route('admin.monitor.index'))
        ->assertOk()
        ->assertDontSee('secret-customer@example.com')
        ->assertInertia(fn (Assert $page) => $page
            ->where('monitor.parked_jobs', 1)
            ->has('parkedJobs', 1)
            ->where('parkedJobs.0.id', $id)
            ->where('parkedJobs.0.name', 'OrderPlacedMail')
            ->where('parkedJobs.0.queue', 'held-backlog')
            ->where('canDeleteParkedJobs', false));
});

test('the mother admin deletes exactly the parked jobs she was shown, and it is audited', function () {
    $shown = [parkedJob(), parkedJob()];
    $parkedLater = parkedJob();
    $active = parkedJob('default');
    $beingRun = parkedJob(reservedAt: now()->getTimestamp());
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');

    $this->delete(route('admin.monitor.parked-jobs.destroy'), [
        'password' => 'password',
        // A default-queue job and one being run are passed too: neither may go.
        'ids' => [...$shown, $active, $beingRun],
    ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Deleted 2 parked jobs.');

    expect(DB::table('jobs')->whereIn('id', $shown)->exists())->toBeFalse()
        ->and(DB::table('jobs')->whereIn('id', [$parkedLater, $active, $beingRun])->count())->toBe(3);

    $entry = Activity::where('description', 'like', '%deleted 2 parked background jobs%')->sole();
    expect($entry->causer_id)->toBe($mother->id)
        ->and(collect($entry->properties['parked_jobs'])->pluck('id')->all())->toBe($shown)
        ->and(json_encode($entry->properties))->not->toContain('secret-customer@example.com');
});

test('a wrong password deletes nothing', function () {
    $id = parkedJob();
    actingAs(createMotherAdmin(), 'admin');

    $this->delete(route('admin.monitor.parked-jobs.destroy'), ['password' => 'wrong', 'ids' => [$id]])
        ->assertSessionHasErrors('password');

    expect(DB::table('jobs')->where('id', $id)->exists())->toBeTrue();
});

test('only the mother admin can delete parked jobs, whatever her permissions', function () {
    $id = parkedJob();

    actingAs(createAdmin(), 'admin');
    $this->delete(route('admin.monitor.parked-jobs.destroy'), ['password' => 'password', 'ids' => [$id]])->assertForbidden();

    auth('admin')->logout();
    $this->delete(route('admin.monitor.parked-jobs.destroy'), ['password' => 'password', 'ids' => [$id]])->assertRedirect();

    expect(DB::table('jobs')->where('id', $id)->exists())->toBeTrue();
});

test('the mother admin sees which order and who each parked email was for, masked', function () {
    config(['queue.default' => 'database']);
    $order = Order::create([
        'order_number' => 'ORD-PARKED-1', 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => ['name' => 'Rahim'], 'billing_address' => [],
    ]);
    DB::table('jobs')->delete();
    Mail::to('rahim.uddin@gmail.com')->queue(new OrderPlacedMail($order));
    DB::table('jobs')->update(['queue' => 'held-backlog']);

    actingAs(createMotherAdmin(), 'admin');
    $this->get(route('admin.monitor.index'))
        ->assertOk()
        ->assertDontSee('rahim.uddin@gmail.com')
        ->assertInertia(fn (Assert $page) => $page
            ->where('parkedJobs.0.name', 'OrderPlacedMail')
            ->where('parkedJobs.0.summary', 'Order ORD-PARKED-1 · to r***@gmail.com'));

    // Other admins who can open the monitor see the job type only.
    actingAs(createAdmin(), 'admin');
    $this->get(route('admin.monitor.index'))
        ->assertInertia(fn (Assert $page) => $page->where('parkedJobs.0.summary', null));
});
