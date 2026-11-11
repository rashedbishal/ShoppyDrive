<?php

declare(strict_types=1);

use App\Jobs\SendMetaTrackingEventJob;
use App\Models\MetaTrackingEvent;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::create(['key' => 'facebook_pixel_id', 'value' => '123456789012345', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => 'test-token', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_enabled', 'value' => true, 'group' => 'marketing']);
});

function storedMetaEvent(array $attributes = []): MetaTrackingEvent
{
    $event = MetaTrackingEvent::create(array_merge([
        'event_name' => 'AddToCart',
        'event_id' => '6f1c1a52-6a43-4b8e-9f43-1f2e3d4c5b6a',
        'event_source_url' => 'https://shop.test/product/shirt',
        'custom_data' => ['currency' => 'BDT', 'value' => 1500, 'content_ids' => ['7']],
        'client_ip_address' => '203.0.113.5',
        'client_user_agent' => 'PestBrowser/1.0',
        'event_time' => now(),
    ], array_intersect_key($attributes, array_flip((new MetaTrackingEvent)->getFillable()))));

    $event->forceFill(array_diff_key($attributes, array_flip($event->getFillable())))->save();

    return $event->fresh();
}

function runMetaJob(MetaTrackingEvent $event, int $attempt = 1): SendMetaTrackingEventJob
{
    $job = (new SendMetaTrackingEventJob($event->id))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    app()->call([$job, 'handle']);

    return $job;
}

test('a delivered event is marked sent with the same event id it was stored with', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
    $event = storedMetaEvent();

    runMetaJob($event)->assertNotReleased()->assertNotFailed();

    expect($event->fresh())
        ->status->toBe(MetaTrackingEvent::STATUS_SENT)
        ->attempts->toBe(1)
        ->sent_at->not->toBeNull();
    Http::assertSent(fn ($request) => $request->data()['data'][0]['event_id'] === '6f1c1a52-6a43-4b8e-9f43-1f2e3d4c5b6a'
        && $request->data()['data'][0]['event_name'] === 'AddToCart'
        && $request->data()['data'][0]['user_data']['client_ip_address'] === '203.0.113.5');
});

test('a temporary failure is retried later with the same event id', function () {
    Http::fakeSequence('graph.facebook.com/*')
        ->pushFailedConnection()
        ->push(['events_received' => 1]);
    $event = storedMetaEvent();

    runMetaJob($event)->assertReleased(SendMetaTrackingEventJob::RETRY_DELAYS[0]);

    expect($event->fresh())
        ->status->toBe(MetaTrackingEvent::STATUS_PENDING)
        ->last_error->toContain('Could not reach');

    runMetaJob($event, attempt: 2);

    expect($event->fresh())->status->toBe(MetaTrackingEvent::STATUS_SENT)->attempts->toBe(2);
    $sentEventIds = Http::recorded()->map(fn (array $exchange) => $exchange[0]->data()['data'][0]['event_id'])->all();
    expect($sentEventIds)->toBe([$event->event_id, $event->event_id]);
});

test('a temporary failure on the last attempt gives up and lands in failed jobs', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    $event = storedMetaEvent();

    runMetaJob($event, attempt: 6)->assertNotReleased()->assertFailed();

    expect($event->fresh()->status)->toBe(MetaTrackingEvent::STATUS_FAILED);
});

test('a permanent rejection is not retried and is recorded without the access token', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter', 'code' => 100]], 400)]);
    $event = storedMetaEvent();

    runMetaJob($event)->assertNotReleased()->assertFailed();

    expect($event->fresh())
        ->status->toBe(MetaTrackingEvent::STATUS_FAILED)
        ->response_status->toBe(400)
        ->last_error->toBe('HTTP 400 code 100: Invalid parameter');
});

test('an event that is already sent or being sent is never sent again', function (string $status) {
    Http::fake();
    $event = storedMetaEvent(['status' => $status]);

    runMetaJob($event);

    Http::assertNothingSent();
    expect($event->fresh()->status)->toBe($status);
})->with([MetaTrackingEvent::STATUS_SENT, MetaTrackingEvent::STATUS_SENDING]);

test('an event due after the integration was switched off is closed without calling Meta or failing the job', function () {
    Http::fake();
    $event = storedMetaEvent();
    Setting::where('key', 'facebook_conversions_api_enabled')->update(['value' => false]);

    runMetaJob($event)->assertNotFailed()->assertNotReleased();

    Http::assertNothingSent();
    expect($event->fresh()->status)->toBe(MetaTrackingEvent::STATUS_FAILED);
});

test('the sweeper re-queues events whose job was lost, and leaves recent ones alone', function () {
    Queue::fake();
    $lostPending = storedMetaEvent(['event_id' => '11111111-1111-4111-8111-111111111111', 'updated_at' => now()->subHours(3)]);
    $stuckSending = storedMetaEvent(['event_id' => '22222222-2222-4222-8222-222222222222', 'status' => MetaTrackingEvent::STATUS_SENDING, 'updated_at' => now()->subHours(3)]);
    $recent = storedMetaEvent(['event_id' => '33333333-3333-4333-8333-333333333333']);
    $sent = storedMetaEvent(['event_id' => '44444444-4444-4444-8444-444444444444', 'status' => MetaTrackingEvent::STATUS_SENT, 'updated_at' => now()->subHours(3)]);

    artisan('meta:dispatch-pending')->assertSuccessful();

    $requeued = Queue::pushed(SendMetaTrackingEventJob::class)->pluck('metaTrackingEventId')->sort()->values()->all();
    expect($requeued)->toBe([$lostPending->id, $stuckSending->id])
        ->and($stuckSending->fresh()->status)->toBe(MetaTrackingEvent::STATUS_PENDING)
        ->and($recent->fresh()->status)->toBe(MetaTrackingEvent::STATUS_PENDING)
        ->and($sent->fresh()->status)->toBe(MetaTrackingEvent::STATUS_SENT);
});

test('the sweeper gives up on events too old for Meta to accept', function () {
    Queue::fake();
    $tooOld = storedMetaEvent(['event_time' => now()->subDays(7), 'updated_at' => now()->subDays(7)]);

    artisan('meta:dispatch-pending')->assertSuccessful();

    Queue::assertNotPushed(SendMetaTrackingEventJob::class);
    expect($tooOld->fresh()->status)->toBe(MetaTrackingEvent::STATUS_FAILED);
});

test('tracking rows are deleted after 30 days', function () {
    $old = storedMetaEvent(['event_id' => '55555555-5555-4555-8555-555555555555', 'created_at' => now()->subDays(31)]);
    $kept = storedMetaEvent(['event_id' => '66666666-6666-4666-8666-666666666666', 'created_at' => now()->subDays(29)]);

    artisan('data:prune')->assertSuccessful();

    expect(MetaTrackingEvent::find($old->id))->toBeNull()
        ->and(MetaTrackingEvent::find($kept->id))->not->toBeNull();
});
