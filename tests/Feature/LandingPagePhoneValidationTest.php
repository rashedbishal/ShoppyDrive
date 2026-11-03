<?php

declare(strict_types=1);

/*
 * FSA-21: landing-page checkout previously validated `phone` with only
 * `required|string|max:20` — far weaker than every other order-creation
 * path (PlaceOrderRequest, ConvertIncompleteOrderRequest,
 * TrackIncompleteOrderRequest), which all require the strict Bangladeshi
 * mobile shape `^01[3-9][0-9]{8}$`. Garbage like "N/A" or a 5-digit string
 * previously created a real Order whose stored phone later broke courier
 * booking (PathaoCourierService/SteadfastCourierService normalize but
 * never validate the shape).
 *
 * The fix normalizes common country-code/separator variants
 * (+880/880 prefix, spaces, hyphens) down to the plain local
 * 01XXXXXXXXX shape *before* validating against the existing strict
 * regex, and stores only that normalized value — never the raw input.
 */

use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    createMotherAdmin();

    Mail::fake();

    $this->product = Product::factory()->create([
        'is_active' => true,
        'published_at' => now()->subDay(),
        'price' => 500,
        'stock_quantity' => 50,
    ]);

    $this->page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo-'.uniqid(),
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => [],
    ]);
});

function fsa21OrderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Customer',
        'phone' => '01712345678',
        'email' => 'customer@example.com',
        'address_line1' => '123 Main St',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'quantity' => 1,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ], $overrides);
}

test('a valid local 11-digit phone number is accepted and stored unchanged', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '01712345678']));

    $response->assertRedirect();
    expect(Order::count())->toBe(1);
    expect(Order::firstOrFail()->shipping_address['phone'])->toBe('01712345678');
});

test('a +880-prefixed phone number is normalized to the local 11-digit form before storage', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '+8801700000000']));

    $response->assertRedirect();
    expect(Order::count())->toBe(1);
    expect(Order::firstOrFail()->shipping_address['phone'])->toBe('01700000000');
});

test('an 880-prefixed phone number without a plus sign is normalized to the local 11-digit form before storage', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '8801700000000']));

    $response->assertRedirect();
    expect(Order::count())->toBe(1);
    expect(Order::firstOrFail()->shipping_address['phone'])->toBe('01700000000');
});

test('a hyphen-separated local phone number is normalized to the local 11-digit form before storage', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '017-000-00000']));

    $response->assertRedirect();
    expect(Order::count())->toBe(1);
    expect(Order::firstOrFail()->shipping_address['phone'])->toBe('01700000000');
});

test('a space-separated local phone number is normalized to the local 11-digit form before storage', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '017 000 00000']));

    $response->assertRedirect();
    expect(Order::count())->toBe(1);
    expect(Order::firstOrFail()->shipping_address['phone'])->toBe('01700000000');
});

test('a hyphen-separated international phone number is normalized to the local 11-digit form before storage', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '+880-170-000-0000']));

    $response->assertRedirect();
    expect(Order::count())->toBe(1);
    expect(Order::firstOrFail()->shipping_address['phone'])->toBe('01700000000');
});

test('alphabetic phone input is rejected and creates no order', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => 'N/A']));

    $response->assertInvalid(['phone']);
    expect(Order::count())->toBe(0);
});

test('a too-short phone number is rejected and creates no order', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '12345']));

    $response->assertInvalid(['phone']);
    expect(Order::count())->toBe(0);
});

test('a too-long phone number is rejected and creates no order', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '017000000000']));

    $response->assertInvalid(['phone']);
    expect(Order::count())->toBe(0);
});

test('an invalid Bangladeshi operator prefix is rejected and creates no order', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '01212345678']));

    $response->assertInvalid(['phone']);
    expect(Order::count())->toBe(0);
});

test('a phone number containing unsupported symbols is rejected and creates no order', function () {
    $response = post(route('lp.order', $this->page->slug), fsa21OrderPayload(['phone' => '017####00000']));

    $response->assertInvalid(['phone']);
    expect(Order::count())->toBe(0);
});
