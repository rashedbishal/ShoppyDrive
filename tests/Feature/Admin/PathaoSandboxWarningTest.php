<?php

declare(strict_types=1);

/*
 * BIZ-028: Pathao defaults to its test (sandbox) system, where a booking
 * looks sent but no rider is dispatched. Owner chose warn-only: sending
 * still works, but in sandbox the admin is told the parcel won't be
 * collected instead of "sent for pickup".
 */

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use App\Services\PathaoCourierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function pathaoSettings(?string $environment): void
{
    $rows = [
        'pathao_client_id' => 'client-id', 'pathao_client_secret' => 'client-secret',
        'pathao_username' => 'merchant@example.com', 'pathao_password' => 'secret', 'pathao_store_id' => '555',
        'pathao_token' => ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()],
    ];
    if ($environment !== null) {
        $rows['pathao_environment'] = $environment;
    }
    foreach ($rows as $key => $value) {
        Setting::create(['key' => $key, 'value' => $value, 'group' => 'courier']);
    }
}

function sendSandboxTestOrder(): TestResponse
{
    $order = Order::create([
        'order_number' => 'ORD-SANDBOX-'.uniqid(), 'status' => OrderStatus::PROCESSING, 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => ['name' => 'Rafiq', 'phone' => '01712345678', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
        'billing_address' => [],
    ]);
    Http::fake(['*/aladdin/api/v1/orders' => Http::response(['type' => 'success', 'data' => ['consignment_id' => 'PA-1', 'order_status' => 'Pending', 'delivery_fee' => 60]])]);

    return actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.pathao.send', $order));
}

test('Pathao counts as sandbox unless the setting is exactly production', function (?string $environment, bool $sandbox) {
    pathaoSettings($environment);

    expect(PathaoCourierService::isSandbox())->toBe($sandbox);
})->with([
    'not set' => [null, true],
    'sandbox' => ['sandbox', true],
    'production' => ['production', false],
]);

test('sending in sandbox still books it but warns the parcel will not be collected', function () {
    pathaoSettings('sandbox');

    sendSandboxTestOrder()->assertSessionHas('error', fn (string $m) => str_contains($m, 'SANDBOX') && str_contains($m, 'NOT be collected'));

    expect(Order::sole()->pathao_consignment_id)->toBe('PA-1');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'courier-api-sandbox.pathao.com'));
});

test('sending in production says it was sent for pickup, to the live Pathao API', function () {
    pathaoSettings('production');

    sendSandboxTestOrder()->assertSessionHas('success', 'Order sent to Pathao for pickup.');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api-hermes.pathao.com'));
});
