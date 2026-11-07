<?php

declare(strict_types=1);

/*
 * SEC-023: laravel.log has no retention limit, so customer names, phones
 * and addresses must not reach it through webhook payloads, courier error
 * bodies, SMS senders or SQL error text. Each test records every log call
 * and checks the personal values never appear.
 */

use App\Models\Order;
use App\Models\Setting;
use App\Support\SafeLogContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

const LOG_PII = ['Rahima Akter', '01712345678', 'House 12, Road 7'];

/** Everything passed to the logger, as one string. */
function capturedLogs(): Closure
{
    $lines = new ArrayObject;

    Log::listen(function ($message) use ($lines): void {
        $lines->append($message->message.' '.json_encode($message->context, JSON_UNESCAPED_UNICODE));
    });

    return fn (): string => implode("\n", (array) $lines);
}

function expectNoCustomerDetails(string $logs): void
{
    foreach (LOG_PII as $value) {
        expect($logs)->not->toContain($value);
    }
}

test('a Steadfast webhook logs only the event, IDs and status', function () {
    $logs = capturedLogs();
    $secret = 'super-secret';
    Setting::updateOrCreate(['key' => 'steadfast_webhook_secret'], ['value' => $secret, 'group' => 'courier']);
    $body = json_encode([
        'notification_type' => 'tracking_update', 'consignment_id' => 555, 'invoice' => 'ORD-1', 'status' => 'pending',
        'recipient_name' => 'Rahima Akter', 'recipient_phone' => '01712345678', 'recipient_address' => 'House 12, Road 7',
    ]);

    $this->call('POST', '/api/steadfast/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X-Signature' => hash_hmac('sha256', $body, $secret),
    ], $body)->assertOk();

    $output = $logs();
    expect($output)->toContain('Webhook received')->toContain('555')->toContain('tracking_update');
    expectNoCustomerDetails($output);
});

test('a courier error logs the status, message and field names, never the values', function () {
    $context = SafeLogContext::courierResponse([
        'message' => 'Validation failed',
        'errors' => ['recipient_phone' => ['01712345678 is not valid'], 'recipient_address' => ['House 12, Road 7 too short']],
    ], 422);

    expect($context)->toBe(['http_status' => 422, 'message' => 'Validation failed', 'error_fields' => ['recipient_phone', 'recipient_address']]);
});

test('an odd courier reply cannot break logging', function (mixed $json) {
    expect(SafeLogContext::courierResponse($json, 500)['message'])->toBeNull();
})->with([null, 'plain text', 7]);

test('a failed Pathao booking keeps customer details out of the log', function () {
    $logs = capturedLogs();
    foreach ([
        'pathao_environment' => 'production', 'pathao_client_id' => 'id', 'pathao_client_secret' => 'secret',
        'pathao_username' => 'm@example.com', 'pathao_password' => 'pw', 'pathao_store_id' => '555',
        'pathao_token' => ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()],
    ] as $key => $value) {
        Setting::create(['key' => $key, 'value' => $value, 'group' => 'courier']);
    }
    Http::fake(['*/aladdin/api/v1/orders' => Http::response(['message' => 'Rejected', 'errors' => ['recipient_address' => ['House 12, Road 7']], 'echo' => '01712345678'], 422)]);
    $order = Order::create([
        'order_number' => 'ORD-LOG-1', 'status' => 'processing', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => ['name' => 'Rahima Akter', 'phone' => '01712345678', 'address_line1' => 'House 12, Road 7', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
        'billing_address' => [],
    ]);

    $this->actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.pathao.send', $order))->assertSessionHas('error');

    $output = $logs();
    expect($output)->toContain('Create order failed');
    expectNoCustomerDetails($output);
});

test('a database error logs its type and code, never the SQL values', function () {
    $e = new QueryException('mysql', 'insert into orders (address) values (?)', ['House 12, Road 7'], new PDOException('SQLSTATE[23000]: duplicate'));

    expectNoCustomerDetails(json_encode(SafeLogContext::exception($e)));
    expect(SafeLogContext::exception($e)['exception'])->toBe(QueryException::class);
});

test('an SMS log line has no sender', function () {
    $logs = capturedLogs();
    setSmsForwarderToken('token-123');

    $this->postJson('/api/sms/receive', ['from' => '01712345678', 'message' => 'Hello'], ['Authorization' => 'Bearer token-123']);

    expect($logs())->toContain('SMS received')->not->toContain('01712345678');
});
