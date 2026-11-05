<?php

declare(strict_types=1);

use App\Jobs\AbandonedCartReminderJob;
use App\Mail\AbandonedCartReminderMail;
use App\Mail\NewsletterCustomMail;
use App\Mail\OrderPlacedMail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Support\FailedJobSummary;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $payload
 */
function failedJobRow(string $displayName = OrderPlacedMail::class, string $exception = "RuntimeException: Connection refused\n#0 /app/x.php(1)", array $payload = []): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(array_merge([
            'uuid' => $uuid,
            'displayName' => $displayName,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => 3,
            'data' => ['commandName' => 'Illuminate\\Mail\\SendQueuedMailable', 'command' => serialize(new AbandonedCartReminderJob(1))],
        ], $payload)),
        'exception' => $exception,
        'failed_at' => now(),
    ]);

    return $uuid;
}

function jobsAdmin(array $permissions): Admin
{
    return Admin::create(['name' => 'Jobs Admin', 'email' => 'jobs-'.uniqid().'@example.com', 'password' => 'password', 'permissions' => ['failed_jobs' => $permissions]]);
}

function queueConfirmedOrderMail(): Order
{
    config(['queue.default' => 'database']);
    $user = User::factory()->create();

    $order = Order::create([
        'order_number' => 'ORD-RETRY-'.uniqid(), 'user_id' => $user->id, 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => [], 'billing_address' => [],
    ]);

    DB::table('jobs')->delete();
    Mail::to($user->email)->queue(new OrderPlacedMail($order));

    return $order;
}

test('an empty list renders', function () {
    actingAs(jobsAdmin(['view']), 'admin');

    $this->get(route('admin.failed-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/failed-jobs/index')->has('failedJobs.data', 0));
});

test('a failed job appears with useful metadata', function () {
    actingAs(jobsAdmin(['view']), 'admin');
    $uuid = failedJobRow();

    $this->get(route('admin.failed-jobs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('failedJobs.data', 1)
            ->where('failedJobs.data.0.uuid', $uuid)
            ->where('failedJobs.data.0.queue', 'default')
            ->where('failedJobs.data.0.connection', 'database')
            ->where('failedJobs.data.0.job', 'OrderPlacedMail')
            ->where('failedJobs.data.0.max_tries', 3)
            ->where('failedJobs.data.0.error_type', 'RuntimeException')
            ->where('failedJobs.data.0.error', 'Connection refused')
            ->where('failedJobs.data.0.can_retry', true));
});

test('payloads and sensitive exception text never reach the page', function () {
    actingAs(jobsAdmin(['view']), 'admin');
    failedJobRow(exception: 'Illuminate\\Database\\QueryException: Failed for customer@example.com phone 01712345678 password=hunter2 token: abc123 at https://user:pw@smtp.example.com:587 from 203.0.113.9 key AbCdEfGhIjKlMnOpQrStUvWxYz012345 card 4111111111 (Connection: mysql, SQL: insert into users (email) values (leaked@example.com))'."\n#0 /home/site/app/Secret.php(10): stack", payload: ['data' => ['command' => 'customer@example.com secret-recipient-data']]);

    $response = $this->get(route('admin.failed-jobs.index'));
    $page = json_encode($response->viewData('page')['props']['failedJobs']);

    foreach (['customer@example.com', '01712345678', 'hunter2', 'abc123', 'user:pw', 'smtp.example.com', '203.0.113.9', 'AbCdEfGhIjKlMnOpQrStUvWxYz012345', '4111111111', 'leaked', 'insert into', 'Secret.php', 'secret-recipient-data', 'payload', 'CallQueuedHandler'] as $secret) {
        expect($page)->not->toContain($secret);
    }

    expect($page)->toContain('[email]')->toContain('[phone]')->toContain('password [hidden]');
});

test('a very long exception is cut short', function () {
    $summary = FailedJobSummary::from((object) [
        'uuid' => 'u', 'queue' => 'default', 'connection' => 'database', 'failed_at' => 'now',
        'payload' => '{}', 'exception' => 'RuntimeException: '.str_repeat('word ', 200),
    ]);

    expect(mb_strlen($summary['error']))->toBeLessThanOrEqual(240);
});

test('a malformed payload is shown as unknown and cannot be retried', function () {
    expect(FailedJobSummary::canRetry((object) ['payload' => 'not json']))->toBeFalse();
    expect(FailedJobSummary::from((object) ['uuid' => 'u', 'queue' => 'q', 'connection' => 'c', 'failed_at' => 'now', 'payload' => 'not json', 'exception' => ''])['job'])->toBe('Unknown job');
});

test('staff without the section cannot view, retry or delete', function () {
    $uuid = failedJobRow();
    actingAs(Admin::create(['name' => 'No Access', 'email' => 'none@example.com', 'password' => 'password', 'permissions' => ['orders' => ['view']]]), 'admin');

    $this->get(route('admin.failed-jobs.index'))->assertForbidden();
    $this->post(route('admin.failed-jobs.retry', $uuid), ['confirmed' => true])->assertForbidden();
    $this->delete(route('admin.failed-jobs.destroy', $uuid), ['confirmed' => true])->assertForbidden();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

test('view-only staff cannot retry or delete, and each ability is separate', function () {
    $uuid = failedJobRow();

    actingAs(jobsAdmin(['view']), 'admin');
    $this->post(route('admin.failed-jobs.retry', $uuid), ['confirmed' => true])->assertForbidden();
    $this->delete(route('admin.failed-jobs.destroy', $uuid), ['confirmed' => true])->assertForbidden();

    actingAs(jobsAdmin(['view', 'update']), 'admin');
    $this->delete(route('admin.failed-jobs.destroy', $uuid), ['confirmed' => true])->assertForbidden();

    actingAs(jobsAdmin(['view', 'delete']), 'admin');
    $this->post(route('admin.failed-jobs.retry', $uuid), ['confirmed' => true])->assertForbidden();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

test('guests are kept out', function () {
    $this->get(route('admin.failed-jobs.index'))->assertRedirect();
});

test('retry and delete need explicit confirmation', function () {
    actingAs(jobsAdmin(['view', 'update', 'delete']), 'admin');
    $uuid = failedJobRow();

    $this->post(route('admin.failed-jobs.retry', $uuid))->assertSessionHasErrors('confirmed');
    $this->delete(route('admin.failed-jobs.destroy', $uuid))->assertSessionHasErrors('confirmed');

    expect(DB::table('failed_jobs')->count())->toBe(1);
    expect(DB::table('jobs')->count())->toBe(0);
});

test('a job not on the allow-list cannot be retried', function (string $class) {
    actingAs(jobsAdmin(['view', 'update']), 'admin');
    $uuid = failedJobRow($class);

    $this->post(route('admin.failed-jobs.retry', $uuid), ['confirmed' => true])->assertSessionHas('error');

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeTrue();
    expect(DB::table('jobs')->count())->toBe(0);
    expect(Activity::query()->where('event', 'failed_job_retried')->count())->toBe(0);
})->with([AbandonedCartReminderMail::class, NewsletterCustomMail::class, 'App\\Jobs\\ChargeCustomerJob', 'Unknown']);

test('retrying twice queues the job once', function () {
    actingAs(jobsAdmin(['view', 'update']), 'admin');
    $uuid = failedJobRow(AbandonedCartReminderJob::class);

    $this->post(route('admin.failed-jobs.retry', $uuid), ['confirmed' => true])->assertSessionHas('success');
    $this->post(route('admin.failed-jobs.retry', $uuid), ['confirmed' => true])->assertSessionHas('error');

    expect(DB::table('jobs')->count())->toBe(1);
    expect(DB::table('failed_jobs')->count())->toBe(0);
    expect(Activity::query()->where('event', 'failed_job_retried')->count())->toBe(1);
});

// ── OPS-003: retrying a whole backlog shouldn't take one click per row ──────

test('retry all retries every retryable job in one request', function () {
    actingAs(jobsAdmin(['view', 'update']), 'admin');
    failedJobRow(AbandonedCartReminderJob::class);
    failedJobRow(AbandonedCartReminderJob::class);
    failedJobRow('App\\Jobs\\SomeUnknownJob'); // not on the retryable allow-list

    $this->post(route('admin.failed-jobs.retryAll'), ['confirmed' => true])
        ->assertSessionHas('success', '2 job(s) queued to run again.');

    expect(DB::table('jobs')->count())->toBe(2);
    expect(DB::table('failed_jobs')->count())->toBe(1);
    expect(Activity::query()->where('event', 'failed_job_retried')->count())->toBe(2);
});

test('retry all reports nothing retryable when the backlog has no eligible jobs', function () {
    actingAs(jobsAdmin(['view', 'update']), 'admin');
    failedJobRow('App\\Jobs\\SomeUnknownJob');

    $this->post(route('admin.failed-jobs.retryAll'), ['confirmed' => true])
        ->assertSessionHas('error', 'No retryable failed jobs were found.');

    expect(DB::table('jobs')->count())->toBe(0);
    expect(DB::table('failed_jobs')->count())->toBe(1);
});

test('view-only staff cannot retry all', function () {
    actingAs(jobsAdmin(['view']), 'admin');
    failedJobRow(AbandonedCartReminderJob::class);

    $this->post(route('admin.failed-jobs.retryAll'), ['confirmed' => true])->assertForbidden();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

test('a real failed email fails visibly, then sends once when retried', function () {
    $admin = jobsAdmin(['view', 'update']);
    queueConfirmedOrderMail();
    config(['mail.mailers.array.transport' => 'not-configured']);
    app('mail.manager')->purge('array');

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);

    expect(DB::table('jobs')->count())->toBe(0);
    $failed = DB::table('failed_jobs')->sole();

    actingAs($admin, 'admin');
    $this->post(route('admin.failed-jobs.retry', $failed->uuid), ['confirmed' => true])->assertSessionHas('success');

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);

    expect(DB::table('failed_jobs')->count())->toBe(1);

    config(['mail.mailers.array.transport' => 'array']);
    app('mail.manager')->purge('array');
    $this->post(route('admin.failed-jobs.retry', DB::table('failed_jobs')->value('uuid')), ['confirmed' => true])->assertSessionHas('success');

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);

    expect(DB::table('failed_jobs')->count())->toBe(0);
    expect(DB::table('jobs')->count())->toBe(0);
    expect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1);
});

test('retrying an order email changes nothing on the order', function () {
    $order = queueConfirmedOrderMail();
    $before = $order->refresh()->only(['status', 'payment_status', 'total', 'stock_restored_at']);
    $orders = Order::query()->count();

    DB::table('jobs')->delete();
    actingAs(jobsAdmin(['view', 'update']), 'admin');
    $this->post(route('admin.failed-jobs.retry', failedJobRow()), ['confirmed' => true]);

    expect(Order::query()->count())->toBe($orders);
    expect($order->refresh()->only(['status', 'payment_status', 'total', 'stock_restored_at']))->toEqual($before);
});

test('deleting a record is audited with no job data beyond its name', function () {
    $admin = jobsAdmin(['view', 'delete']);
    actingAs($admin, 'admin');
    $uuid = failedJobRow();

    $this->delete(route('admin.failed-jobs.destroy', $uuid), ['confirmed' => true])->assertSessionHas('success');

    $activity = Activity::query()->where('event', 'failed_job_deleted')->sole();

    expect(DB::table('failed_jobs')->count())->toBe(0);
    expect($activity->causer->is($admin))->toBeTrue();
    expect($activity->properties->toArray())->toEqual(['uuid' => $uuid, 'job' => 'OrderPlacedMail', 'queue' => 'default']);
    expect($activity->toJson())->not->toContain('customer@example.com');
});

test('a retried job is audited against the acting admin', function () {
    $admin = jobsAdmin(['view', 'update']);
    actingAs($admin, 'admin');

    $this->post(route('admin.failed-jobs.retry', failedJobRow()), ['confirmed' => true]);

    expect(Activity::query()->where('event', 'failed_job_retried')->sole()->causer->is($admin))->toBeTrue();
});

test('the dashboard count shows only to staff who can open the page', function () {
    failedJobRow();

    actingAs(jobsAdmin(['view']), 'admin');
    $this->get(route('admin.dashboard'))->assertInertia(fn (AssertableInertia $page) => $page->where('stats.failed_jobs', 1));

    actingAs(Admin::create(['name' => 'No Access', 'email' => 'none@example.com', 'password' => 'password', 'permissions' => []]), 'admin');
    $this->get(route('admin.dashboard'))->assertInertia(fn (AssertableInertia $page) => $page->where('stats.failed_jobs', null));
});
