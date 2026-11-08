<?php

declare(strict_types=1);

use App\Models\BlockedIp;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function assertSecurityHeaders(TestResponse $response): TestResponse
{
    expect($response->headers->all('x-content-type-options'))->toBe(['nosniff']);
    expect($response->headers->all('x-frame-options'))->toBe(['SAMEORIGIN']);
    expect($response->headers->all('referrer-policy'))->toBe(['strict-origin-when-cross-origin']);

    return $response;
}

test('public pages carry the headers', function (string $routeName) {
    assertSecurityHeaders(get(route($routeName)))->assertOk();
})->with(['home', 'products.index', 'search', 'login', 'register', 'wishlist.index']);

test('checkout carries the headers', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 5]);

    assertSecurityHeaders(actingAs($user)->get(route('checkout.index', ['buy_now_product' => $product->id])))->assertOk();
});

test('account pages carry the headers, signed in or redirected', function () {
    assertSecurityHeaders(get(route('account.wishlist')))->assertRedirect(route('login'));
    assertSecurityHeaders(actingAs(User::factory()->create())->get(route('account.wishlist')))->assertOk();
});

test('admin pages carry the headers, signed in or redirected', function () {
    assertSecurityHeaders(get(route('admin.dashboard')))->assertRedirect();

    actingAs(createAdmin(['name' => 'Header Admin', 'email' => 'headers@example.com', 'password' => bcrypt('password')]), 'admin');

    assertSecurityHeaders(get(route('admin.dashboard')))->assertOk();
    assertSecurityHeaders(get(route('admin.login')));
});

test('error responses carry the headers', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_headers-boom', fn () => throw new RuntimeException('Boom'));

    assertSecurityHeaders(get('/no-such-page'))->assertNotFound();
    assertSecurityHeaders(get('/_headers-boom'))->assertServerError();
});

test('a blocked visitor gets the headers on the 403', function () {
    BlockedIp::create(['ip_address' => '127.0.0.1']);

    assertSecurityHeaders(get(route('home')))->assertForbidden();
});

test('the health check carries the headers', function () {
    assertSecurityHeaders(get('/up'))->assertOk();
});

test('api and JSON responses still work and keep their content type', function () {
    assertSecurityHeaders(getJson('/api/search/suggestions?q=shirt'))->assertOk()->assertHeader('Content-Type', 'application/json');
});

test('a webhook still gets its normal response', function () {
    Setting::updateOrCreate(['key' => 'steadfast_webhook_secret'], ['value' => 'secret', 'group' => 'courier']);

    assertSecurityHeaders(postJson(route('steadfast.webhook'), ['notification_type' => 'delivery_status']))
        ->assertUnauthorized()
        ->assertJson(['message' => 'Invalid signature']);
});

test('an invoice download keeps its type and attachment', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::create([
        'order_number' => 'ORD-HEADERS-1', 'user_id' => $user->id, 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => ['name' => 'Buyer'], 'billing_address' => [],
    ]);
    OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Item', 'sku' => 'SKU', 'price' => 100, 'quantity' => 1, 'subtotal' => 100, 'product_snapshot' => []]);

    $response = assertSecurityHeaders(actingAs($user)->get(route('account.orders.invoice', $order)))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('invoice-ORD-HEADERS-1.pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

test('file and streamed responses are unchanged apart from the headers', function () {
    Route::get('/_headers-image', fn () => response()->file(public_path('favicon.ico')));
    Route::get('/_headers-stream', fn () => response()->streamDownload(fn () => print ('data'), 'export.csv', ['Content-Type' => 'text/csv']));

    $image = assertSecurityHeaders(get('/_headers-image'))->assertOk();
    $stream = assertSecurityHeaders(get('/_headers-stream'))->assertOk();

    expect($image->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
    expect($stream->streamedContent())->toBe('data');
    expect($stream->headers->get('Content-Disposition'))->toContain('export.csv');
});

test('the app embeds nothing that a frame policy would break', function () {
    $sources = collect(File::allFiles(resource_path()))
        ->filter(fn (SplFileInfo $file) => in_array($file->getExtension(), ['tsx', 'ts', 'php'], true))
        ->filter(fn (SplFileInfo $file) => str_contains(strtolower($file->getContents()), '<iframe'));

    expect($sources)->toBeEmpty();
});

test('outside production no HSTS or Content Security Policy is sent', function () {
    // SEC-013: production adds HSTS and an enforced CSP; see
    // ProductionTransportSecurityTest.
    $response = get(route('home'));

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
});
