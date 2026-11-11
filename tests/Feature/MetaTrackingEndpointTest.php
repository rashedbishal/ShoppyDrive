<?php

declare(strict_types=1);

use App\Jobs\SendMetaTrackingEventJob;
use App\Models\Coupon;
use App\Models\MetaTrackingEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withUnencryptedCookies;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::create(['key' => 'facebook_pixel_id', 'value' => '123456789012345', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => 'test-token', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_enabled', 'value' => true, 'group' => 'marketing']);
    Queue::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function metaEventPayload(string $eventName, array $overrides = []): array
{
    return array_merge([
        'event_name' => $eventName,
        'event_id' => (string) Str::uuid(),
        'event_source_url' => url('/product/test-product'),
    ], $overrides);
}

test('a product page view is recorded with the name and price from the database, not the browser', function () {
    $product = Product::factory()->create(['name' => 'Cotton Shirt', 'price' => 1500]);
    $payload = metaEventPayload('ViewContent', [
        'items' => [['product_id' => $product->id, 'price' => 1, 'item_price' => 1]],
        'value' => 1,
    ]);

    postJson(route('tracking.meta'), $payload)->assertStatus(202)->assertJson(['recorded' => true]);

    $event = MetaTrackingEvent::sole();
    expect($event->event_name)->toBe('ViewContent')
        ->and($event->event_id)->toBe($payload['event_id'])
        ->and($event->event_source_url)->toBe(url('/product/test-product'))
        ->and($event->custom_data)->toEqual([
            'currency' => 'BDT',
            'value' => 1500,
            'content_type' => 'product',
            'content_ids' => [(string) $product->id],
            'contents' => [['id' => (string) $product->id, 'quantity' => 1, 'item_price' => 1500]],
            'content_name' => 'Cotton Shirt',
        ]);
    Queue::assertPushed(SendMetaTrackingEventJob::class, fn ($job) => $job->metaTrackingEventId === $event->id);
});

test('add to cart values the added quantity at the variant\'s own price', function () {
    $product = Product::factory()->create(['price' => 900]);
    $variant = ProductVariant::factory()->for($product)->create(['price' => 1200]);

    postJson(route('tracking.meta'), metaEventPayload('AddToCart', [
        'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 3]],
    ]))->assertStatus(202);

    $customData = MetaTrackingEvent::sole()->custom_data;
    expect($customData['value'])->toEqual(3600)
        ->and($customData['contents'])->toEqual([['id' => (string) $product->id, 'quantity' => 3, 'item_price' => 1200]]);
});

test('a variant that belongs to another product is never priced or recorded', function () {
    $product = Product::factory()->create();
    $otherVariant = ProductVariant::factory()->for(Product::factory())->create();

    postJson(route('tracking.meta'), metaEventPayload('AddToCart', [
        'items' => [['product_id' => $product->id, 'variant_id' => $otherVariant->id, 'quantity' => 1]],
    ]))->assertStatus(202)->assertJson(['recorded' => false]);

    expect(MetaTrackingEvent::count())->toBe(0);
});

test('remove from cart and add to wishlist are recorded under their own event names', function (string $eventName) {
    $product = Product::factory()->create(['price' => 700]);

    postJson(route('tracking.meta'), metaEventPayload($eventName, [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ]))->assertStatus(202);

    $event = MetaTrackingEvent::sole();
    expect($event->event_name)->toBe($eventName)
        ->and($event->custom_data['value'])->toEqual(1400);
})->with(['RemoveFromCart', 'AddToWishlist']);

test('initiate checkout totals several products and keeps a real coupon code only', function () {
    $shirt = Product::factory()->create(['price' => 1000]);
    $cap = Product::factory()->create(['price' => 250]);
    Coupon::factory()->create(['code' => 'EID10']);
    $items = [['product_id' => $shirt->id, 'quantity' => 2], ['product_id' => $cap->id, 'quantity' => 1]];

    postJson(route('tracking.meta'), metaEventPayload('InitiateCheckout', ['items' => $items, 'coupon' => 'EID10']))->assertStatus(202);
    postJson(route('tracking.meta'), metaEventPayload('InitiateCheckout', ['items' => $items, 'coupon' => 'MADEUP']))->assertStatus(202);

    [$withCoupon, $withoutCoupon] = MetaTrackingEvent::orderBy('id')->get()->all();
    expect($withCoupon->custom_data['value'])->toEqual(2250)
        ->and($withCoupon->custom_data['num_items'])->toBe(3)
        ->and($withCoupon->custom_data['coupon'])->toBe('EID10')
        ->and($withCoupon->custom_data['content_ids'])->toBe([(string) $shirt->id, (string) $cap->id])
        ->and($withoutCoupon->custom_data)->not->toHaveKey('coupon');
});

test('a search is recorded with the search text and only products the shop really shows', function () {
    $visible = Product::factory()->create();
    $hidden = Product::factory()->create(['is_active' => false]);

    postJson(route('tracking.meta'), metaEventPayload('Search', [
        'event_source_url' => url('/search?q=shirt'),
        'search_string' => '  shirt ',
        'items' => [['product_id' => $visible->id], ['product_id' => $hidden->id]],
    ]))->assertStatus(202);

    expect(MetaTrackingEvent::sole()->custom_data)->toBe([
        'search_string' => 'shirt',
        'content_type' => 'product',
        'content_ids' => [(string) $visible->id],
    ]);
});

test('a search with no results is still recorded', function () {
    postJson(route('tracking.meta'), metaEventPayload('Search', ['search_string' => 'nothing matches']))->assertStatus(202);

    expect(MetaTrackingEvent::sole()->custom_data)->toBe(['search_string' => 'nothing matches']);
});

test('the browser can never report a Purchase', function () {
    $product = Product::factory()->create();

    postJson(route('tracking.meta'), metaEventPayload('Purchase', ['items' => [['product_id' => $product->id]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event_name');

    expect(MetaTrackingEvent::count())->toBe(0);
});

test('a repeated request with the same event id is one event', function () {
    $product = Product::factory()->create();
    $payload = metaEventPayload('AddToCart', ['items' => [['product_id' => $product->id, 'quantity' => 1]]]);

    postJson(route('tracking.meta'), $payload)->assertStatus(202)->assertJson(['recorded' => true]);
    postJson(route('tracking.meta'), $payload)->assertStatus(202)->assertJson(['recorded' => false]);

    expect(MetaTrackingEvent::count())->toBe(1);
    Queue::assertPushed(SendMetaTrackingEventJob::class, 1);
});

test('requests that are malformed or claim another site are refused', function (array $overrides, string $field) {
    $product = Product::factory()->create();

    postJson(route('tracking.meta'), metaEventPayload('AddToCart', ['items' => [['product_id' => $product->id]], ...$overrides]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'not a uuid' => [['event_id' => 'abc-123'], 'event_id'],
    'another site' => [['event_source_url' => 'https://evil.example/product/x'], 'event_source_url'],
    'no products' => [['items' => []], 'items'],
    'search text on a product event' => [['search_string' => 'x'], 'search_string'],
    'absurd quantity' => [['items' => [['product_id' => 1, 'quantity' => 100000]]], 'items.0.quantity'],
]);

test('a logged-in customer is linked to the event, and the Pixel cookies are kept unhashed', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    withUnencryptedCookies(['_fbp' => 'fb.1.1596403881668.1116446470', '_fbc' => 'fb.1.1596403881668.IwAR2xyz']);
    actingAs($user)
        ->withCredentials()
        ->withHeader('User-Agent', 'PestBrowser/1.0')
        ->postJson(route('tracking.meta'), metaEventPayload('AddToWishlist', ['items' => [['product_id' => $product->id]]]))
        ->assertStatus(202);

    expect(MetaTrackingEvent::sole())
        ->user_id->toBe($user->id)
        ->client_user_agent->toBe('PestBrowser/1.0')
        ->fbp->toBe('fb.1.1596403881668.1116446470')
        ->fbc->toBe('fb.1.1596403881668.IwAR2xyz');
});

test('a guest who just arrived from a Facebook ad gets an fbc built from the fbclid; malformed cookies are dropped', function () {
    $product = Product::factory()->create();

    withUnencryptedCookies(['_fbp' => '<script>', '_fbc' => 'nonsense'])
        ->withCredentials()
        ->postJson(route('tracking.meta'), metaEventPayload('ViewContent', [
            'event_source_url' => url('/product/test-product?fbclid=IwAR0abc_DEF-123'),
            'items' => [['product_id' => $product->id]],
        ]))->assertStatus(202);

    $event = MetaTrackingEvent::sole();
    expect($event->user_id)->toBeNull()
        ->and($event->fbp)->toBeNull()
        ->and($event->fbc)->toMatch('/^fb\.1\.\d{13}\.IwAR0abc_DEF-123$/');
});

test('nothing is recorded while the integration is switched off', function () {
    Setting::where('key', 'facebook_conversions_api_enabled')->update(['value' => false]);
    $product = Product::factory()->create();

    postJson(route('tracking.meta'), metaEventPayload('ViewContent', ['items' => [['product_id' => $product->id]]]))
        ->assertStatus(202)
        ->assertJson(['recorded' => false]);

    expect(MetaTrackingEvent::count())->toBe(0);
});

test('the endpoint is rate limited per visitor', function () {
    $product = Product::factory()->create();

    foreach (range(1, 60) as $attempt) {
        postJson(route('tracking.meta'), metaEventPayload('ViewContent', ['items' => [['product_id' => $product->id]]]))->assertStatus(202);
    }

    postJson(route('tracking.meta'), metaEventPayload('ViewContent', ['items' => [['product_id' => $product->id]]]))->assertTooManyRequests();
});
