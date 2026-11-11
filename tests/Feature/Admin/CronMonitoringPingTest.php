<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\HeartbeatPing;
use App\Support\LayoutCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

const PING_URL = 'https://hc-ping.com/11111111-2222-3333-4444-555555555555';

// ── Saving the URL (mother-only) ─────────────────────────────────────────────

test('the mother admin can save, and then clear, the monitoring ping url', function () {
    actingAs(createMotherAdmin(), 'admin')
        ->put(route('admin.settings.security.heartbeat.update'), ['heartbeat_ping_url' => PING_URL])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.settings.security.edit'));

    expect(HeartbeatPing::url())->toBe(PING_URL);

    actingAs(createMotherAdmin(), 'admin')
        ->put(route('admin.settings.security.heartbeat.update'), ['heartbeat_ping_url' => ''])
        ->assertSessionHasNoErrors();

    expect(HeartbeatPing::url())->toBeNull()
        ->and(Setting::where('key', HeartbeatPing::SETTING_KEY)->exists())->toBeFalse();
});

test('an ordinary admin cannot change the monitoring ping url', function () {
    actingAs(createAdmin(['email' => 'staff-ping@example.com']), 'admin')
        ->put(route('admin.settings.security.heartbeat.update'), ['heartbeat_ping_url' => PING_URL])
        ->assertSessionHas('error');

    expect(HeartbeatPing::url())->toBeNull();
});

test('a value that is not an http(s) url is rejected', function (string $value) {
    actingAs(createMotherAdmin(), 'admin')
        ->put(route('admin.settings.security.heartbeat.update'), ['heartbeat_ping_url' => $value])
        ->assertSessionHasErrors('heartbeat_ping_url');

    expect(HeartbeatPing::url())->toBeNull();
})->with(['plain text' => 'not a url', 'ftp' => 'ftp://example.com/ping', 'javascript' => 'javascript:alert(1)']);

test('only the mother admin is ever sent the url itself; others only learn whether it is set', function () {
    HeartbeatPing::save(PING_URL);

    actingAs(createMotherAdmin(), 'admin')
        ->get(route('admin.settings.security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('heartbeatPingUrl', PING_URL)->where('heartbeatConfigured', true));

    actingAs(createAdmin(['email' => 'staff-ping2@example.com']), 'admin')
        ->get(route('admin.settings.security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('heartbeatPingUrl', null)->where('heartbeatConfigured', true));
});

test('the ping url is never shared with the storefront', function () {
    HeartbeatPing::save(PING_URL);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->missing('settings.'.HeartbeatPing::SETTING_KEY));
    expect(json_encode(app(LayoutCache::class)::settings()))->not->toContain('hc-ping.com');
});

// ── The ping itself ──────────────────────────────────────────────────────────

test('app:drain-queue pings the url after a clean run, even with nothing to drain', function () {
    config(['queue.default' => 'database']);
    Process::fake();
    Http::fake();
    HeartbeatPing::save(PING_URL);

    $this->artisan('app:drain-queue')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === PING_URL);
});

test('nothing is pinged when no url is configured', function () {
    config(['queue.default' => 'database']);
    Process::fake();
    Http::fake();

    $this->artisan('app:drain-queue')->assertSuccessful();

    Http::assertNothingSent();
});

test('a run that fails does not ping, so the monitor notices a queue that cannot be worked', function () {
    config(['queue.default' => 'database']);
    Http::fake();
    HeartbeatPing::save(PING_URL);

    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
        'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp(),
    ]);
    Process::fake(fn (PendingProcess $process) => Process::result(exitCode: 1, errorOutput: 'boom'));

    $this->artisan('app:drain-queue')->assertFailed();

    Http::assertNothingSent();
});

test('an unreachable monitor never breaks the drain and never logs the url', function () {
    config(['queue.default' => 'database']);
    Process::fake();
    Http::fake(fn () => throw new ConnectionException('down '.PING_URL));
    Log::spy();
    HeartbeatPing::save(PING_URL);

    $this->artisan('app:drain-queue')->assertSuccessful();

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context = []) => ! str_contains($message.json_encode($context), 'hc-ping.com'),
    );
});
