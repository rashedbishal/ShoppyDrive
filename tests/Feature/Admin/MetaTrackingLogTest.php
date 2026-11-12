<?php

declare(strict_types=1);

use App\Jobs\SendMetaTrackingEventJob;
use App\Models\Admin;
use App\Models\MetaTrackingEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

function loggedMetaEvent(string $status = MetaTrackingEvent::STATUS_SENT, array $attributes = []): MetaTrackingEvent
{
    $event = MetaTrackingEvent::create(array_merge([
        'event_name' => 'AddToCart',
        'event_id' => (string) Str::uuid(),
        'event_source_url' => 'https://shop.test/product/shirt',
        'custom_data' => ['currency' => 'BDT', 'value' => 1500, 'content_ids' => ['7']],
        'client_ip_address' => '203.0.113.5',
        'client_user_agent' => 'PestBrowser/1.0',
        'event_time' => now(),
    ], $attributes));

    $event->forceFill(['status' => $status])->save();

    return $event;
}

/**
 * @param  list<string>  $abilities
 */
function pixelLogAdmin(array $abilities): Admin
{
    return Admin::create([
        'name' => 'Pixel Log Admin',
        'email' => 'pixel-'.uniqid().'@example.com',
        'password' => 'password',
        'permissions' => ['pixel_capi_log' => $abilities],
    ]);
}

test('an admin with view access sees the newest events first, paginated, with status totals', function () {
    $failed = loggedMetaEvent(MetaTrackingEvent::STATUS_FAILED);
    $failed->forceFill(['last_error' => 'Invalid parameter', 'response_status' => 400])->save();
    foreach (range(1, 25) as $i) {
        loggedMetaEvent();
    }
    $pending = loggedMetaEvent(MetaTrackingEvent::STATUS_PENDING);

    actingAs(pixelLogAdmin(['view']), 'admin');

    get(route('admin.pixel-capi-log.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/pixel-capi-log/index')
            ->where('events.total', 27)
            ->has('events.data', 25)
            ->where('events.data.0.id', $pending->id)
            ->where('events.data.0.status', 'pending')
            ->where('events.data.0.client_ip_address', '203.0.113.5')
            ->where('statusCounts', ['pending' => 1, 'sending' => 0, 'sent' => 25, 'failed' => 1])
            ->where('canDelete', false));

    get(route('admin.pixel-capi-log.index', ['page' => 2]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('events.data', 2)
            ->where('events.data.1.id', $failed->id)
            ->where('events.data.1.last_error', 'Invalid parameter')
            ->where('events.data.1.response_status', 400));
});

test('an admin without the section cannot see the log', function () {
    actingAs(Admin::create(['name' => 'No Access', 'email' => 'none@example.com', 'password' => 'password', 'permissions' => []]), 'admin');

    get(route('admin.pixel-capi-log.index'))->assertForbidden();
});

test('a guest is sent to the admin login', function () {
    get(route('admin.pixel-capi-log.index'))->assertRedirect(route('admin.login'));
});

test('deleting empties the whole log, undelivered events included, and records who did it', function () {
    loggedMetaEvent();
    loggedMetaEvent(MetaTrackingEvent::STATUS_FAILED);
    loggedMetaEvent(MetaTrackingEvent::STATUS_PENDING);
    loggedMetaEvent(MetaTrackingEvent::STATUS_SENDING);
    $admin = pixelLogAdmin(['view', 'delete']);

    actingAs($admin, 'admin');

    delete(route('admin.pixel-capi-log.destroy'), ['confirmed' => true])
        ->assertRedirect(route('admin.pixel-capi-log.index'))
        ->assertSessionHas('success', 'Deleted 4 logged events.');

    expect(MetaTrackingEvent::count())->toBe(0);
    expect(Activity::where('event', 'cleared')->latest('id')->first())
        ->causer_id->toBe($admin->id)
        ->description->toBe('Pixel & CAPI log cleared (4 events deleted).');
});

test('deleting requires the explicit confirmation', function () {
    loggedMetaEvent();
    actingAs(pixelLogAdmin(['view', 'delete']), 'admin');

    delete(route('admin.pixel-capi-log.destroy'))->assertSessionHasErrors('confirmed');

    expect(MetaTrackingEvent::count())->toBe(1);
});

test('an admin with only view access cannot delete the log', function () {
    loggedMetaEvent();
    actingAs(pixelLogAdmin(['view']), 'admin');

    delete(route('admin.pixel-capi-log.destroy'), ['confirmed' => true])->assertForbidden();

    expect(MetaTrackingEvent::count())->toBe(1);
});

test('the page offers the delete button to an admin who holds the delete ability', function () {
    actingAs(pixelLogAdmin(['view', 'delete']), 'admin');

    get(route('admin.pixel-capi-log.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('canDelete', true));
});

test('a delivery job for an event deleted from the log stops without sending or failing', function () {
    Http::fake();
    $event = loggedMetaEvent(MetaTrackingEvent::STATUS_PENDING);
    actingAs(pixelLogAdmin(['view', 'delete']), 'admin');
    delete(route('admin.pixel-capi-log.destroy'), ['confirmed' => true]);

    $job = (new SendMetaTrackingEventJob($event->id))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertNotFailed()->assertNotReleased();
    Http::assertNothingSent();
});
