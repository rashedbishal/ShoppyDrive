<?php

declare(strict_types=1);

/*
 * The real push sender (App\Support\WebPushSender) and the "Send test
 * notification" button: what is delivered, what is reported when it is not,
 * and that nothing ever throws into the SMS webhook.
 */

use App\Models\Admin;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Support\WebPushConfig;
use App\Support\WebPushLogger;
use App\Support\WebPushSender;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/** A subscription with real P-256 key material, like a browser's PushManager gives. */
function browserSubscriptionFor(Admin $admin, string $endpoint = 'https://push.example.test/send/abc'): PushSubscription
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ec = openssl_pkey_get_details($key)['ec'];
    $public = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    $b64 = fn (string $raw): string => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

    return $admin->pushSubscriptions()->create([
        'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint),
        'public_key' => $b64($public), 'auth_token' => $b64(random_bytes(16)),
    ]);
}

function mockedPushService(Response|ConnectException ...$responses): Client
{
    return new Client(['handler' => HandlerStack::create(new MockHandler($responses)), 'http_errors' => false]);
}

beforeEach(function () {
    WebPushConfig::generate();
    $this->admin = createMotherAdmin();
    browserSubscriptionFor($this->admin);
});

test('a push the service accepts is reported as delivered and remembered', function () {
    $result = WebPushSender::send($this->admin, ['title' => 'Hi'], 'test', mockedPushService(new Response(201)));

    expect($result)->toBe(['sent' => 1, 'expired' => 0, 'failed' => []]);
    expect(WebPushConfig::lastResult())->toMatchArray(['kind' => 'test', 'sent' => 1, 'failed' => []]);
});

test('a push the service rejects is reported with its status and reason, without throwing', function () {
    $result = WebPushSender::send($this->admin, ['title' => 'Hi'], 'sms', mockedPushService(new Response(403, [], 'invalid JWT provided')));

    expect($result['sent'])->toBe(0)
        ->and($result['failed'][0])->toContain('403');
    expect(WebPushConfig::lastResult()['failed'][0])->toContain('403')
        ->and(PushSubscription::count())->toBe(1);
});

test('a browser sign-up the service says is gone is removed', function () {
    $result = WebPushSender::send($this->admin, ['title' => 'Hi'], 'sms', mockedPushService(new Response(410)));

    expect($result['expired'])->toBe(1)->and($result['failed'])->toBe([])
        ->and(PushSubscription::count())->toBe(0);
});

test('an unreachable push service is reported, never thrown', function () {
    $result = WebPushSender::send($this->admin, ['title' => 'Hi'], 'sms', mockedPushService(
        new ConnectException('Connection refused', new Request('POST', 'https://push.example.test')),
    ));

    expect($result['sent'])->toBe(0)->and($result['failed'])->not->toBe([]);
});

test('nothing is sent, and it is said why, when the keys are not set up', function () {
    Setting::query()->whereIn('key', [WebPushConfig::PUBLIC_KEY_SETTING, WebPushConfig::PRIVATE_KEY_SETTING])->delete();

    $result = WebPushSender::send($this->admin, ['title' => 'Hi'], 'sms', mockedPushService());

    expect($result['sent'])->toBe(0)->and($result['failed'][0])->toContain('not set up');
});

test('an admin with no enabled browser has nothing to send to', function () {
    $other = createAdmin(['email' => 'nobrowser@example.com']);

    expect(WebPushSender::send($other, ['title' => 'Hi'], 'sms', mockedPushService()))->toBe(['sent' => 0, 'expired' => 0, 'failed' => []]);
});

// ── The test button ──────────────────────────────────────────────────────────

test('the test button explains itself when the keys are missing or no browser is enabled', function () {
    Setting::query()->whereIn('key', [WebPushConfig::PUBLIC_KEY_SETTING, WebPushConfig::PRIVATE_KEY_SETTING])->delete();

    actingAs($this->admin, 'admin')->post(route('admin.settings.sms-forwarder.push-test'))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'keys are not set up'));

    WebPushConfig::generate(); // also clears every browser sign-up

    actingAs($this->admin, 'admin')->post(route('admin.settings.sms-forwarder.push-test'))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'no browser with notifications enabled'));
});

test('the test button surfaces a real failure instead of staying silent', function () {
    // The stored endpoint is not reachable from the test run, so a genuine send attempt fails.
    PushSubscription::query()->delete();
    browserSubscriptionFor($this->admin, 'http://127.0.0.1:9/never-listening');

    actingAs($this->admin, 'admin')->post(route('admin.settings.sms-forwarder.push-test'))
        ->assertSessionHas('error', fn (string $m) => str_starts_with($m, 'Test notification failed:'));

    actingAs($this->admin, 'admin')->get(route('admin.settings.sms-forwarder.index'))
        ->assertInertia(fn ($page) => $page->where('pushStatus.last_result.kind', 'test')->where('pushStatus.last_result.sent', 0));
});

test('an admin who can only view the SMS forwarder cannot use the test button', function () {
    $viewer = Admin::create(['name' => 'Viewer', 'email' => 'viewer-push@example.com', 'password' => 'password', 'permissions' => ['sms_forwarder' => ['view']]]);

    actingAs($viewer, 'admin')->post(route('admin.settings.sms-forwarder.push-test'))->assertForbidden();
});

// ── Hosts without GMP/BCMath ─────────────────────────────────────────────────

test('the push library\'s "install GMP or BCMath" advisory is dropped, but real warnings are logged', function () {
    Log::spy();
    $logger = new WebPushLogger;

    $logger->notice('It is highly recommended to install the GMP or BCMath extension to speed up calculations.');
    $logger->warning('[WebPush] curl extension is not loaded but is required.');

    Log::shouldHaveReceived('log')->once()->with('warning', Mockery::on(fn (string $m) => str_contains($m, 'curl extension')), Mockery::any());
});
