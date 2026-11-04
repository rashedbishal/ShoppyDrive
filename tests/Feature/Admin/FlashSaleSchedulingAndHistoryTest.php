<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAs(createAdmin(), 'admin');
    $this->product = Product::factory()->create(['stock_quantity' => 50]);
});

/**
 * @param  array<int, array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function scheduledSale(array $items, string $startsAt = '2027-01-10T10:00', string $endsAt = '2027-01-10T14:00'): array
{
    return [
        'name' => 'Eid Sale',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'is_active' => true,
        'items' => $items,
    ];
}

test('a typed time means Bangladesh time, and is stored as the true moment', function () {
    post(route('admin.flash-sales.store'), scheduledSale([
        ['product_id' => $this->product->id, 'sale_price' => 500],
    ]))->assertSessionHasNoErrors();

    $sale = FlashSale::firstOrFail();

    expect($sale->starts_at->toDateTimeString())->toBe('2027-01-10 04:00:00')
        ->and($sale->ends_at->toDateTimeString())->toBe('2027-01-10 08:00:00');
});

test('a sale starts and ends at the Bangladesh times the admin typed', function () {
    post(route('admin.flash-sales.store'), scheduledSale([
        ['product_id' => $this->product->id, 'sale_price' => 500],
    ]))->assertSessionHasNoErrors();

    $activeAt = function (string $moment): bool {
        $this->travelTo($moment);

        return FlashSaleItem::findActiveFor($this->product->id, null) !== null;
    };

    expect($activeAt('2027-01-10 09:59:00 Asia/Dhaka'))->toBeFalse()
        ->and($activeAt('2027-01-10 10:01:00 Asia/Dhaka'))->toBeTrue()
        ->and($activeAt('2027-01-10 13:59:00 Asia/Dhaka'))->toBeTrue()
        ->and($activeAt('2027-01-10 14:01:00 Asia/Dhaka'))->toBeFalse();
});

test('the edit form shows the times as they were typed, and resaving does not move them', function () {
    post(route('admin.flash-sales.store'), scheduledSale([
        ['product_id' => $this->product->id, 'sale_price' => 500],
    ]))->assertSessionHasNoErrors();

    $sale = FlashSale::firstOrFail();

    $this->get(route('admin.flash-sales.edit', $sale))->assertInertia(fn (Assert $page) => $page
        ->where('flashSale.starts_at_input', '2027-01-10T10:00')
        ->where('flashSale.ends_at_input', '2027-01-10T14:00'));

    put(route('admin.flash-sales.update', $sale), scheduledSale([
        ['product_id' => $this->product->id, 'sale_price' => 500],
    ]))->assertSessionHasNoErrors();

    expect($sale->fresh()->starts_at->toDateTimeString())->toBe('2027-01-10 04:00:00');
});

test('an end time before the start is still rejected', function () {
    post(route('admin.flash-sales.store'), scheduledSale([], '2027-01-10T14:00', '2027-01-10T10:00'))
        ->assertSessionHasErrors('ends_at');

    expect(FlashSale::count())->toBe(0);
});

test('editing a sale keeps sold counts and row identity, and applies the new price', function () {
    $sale = FlashSale::factory()->create();
    $item = $sale->items()->create(['product_id' => $this->product->id, 'sale_price' => 900, 'sold_count' => 10]);

    put(route('admin.flash-sales.update', $sale), scheduledSale([
        ['product_id' => $this->product->id, 'sale_price' => 850],
    ]))->assertSessionHasNoErrors();

    $saved = $sale->items()->sole();

    expect($saved->id)->toBe($item->id)
        ->and($saved->sold_count)->toBe(10)
        ->and($saved->sale_price)->toBe(850.0);
});

test('editing a sale adds new rows, removes dropped ones and tells variants apart', function () {
    $variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    $dropped = Product::factory()->create();
    $added = Product::factory()->create();
    $sale = FlashSale::factory()->create();
    $whole = $sale->items()->create(['product_id' => $this->product->id, 'sale_price' => 900, 'sold_count' => 4]);
    $specific = $sale->items()->create(['product_id' => $this->product->id, 'variant_id' => $variant->id, 'sale_price' => 800, 'sold_count' => 6]);
    $sale->items()->create(['product_id' => $dropped->id, 'sale_price' => 100, 'sold_count' => 2]);

    put(route('admin.flash-sales.update', $sale), scheduledSale([
        ['product_id' => (string) $this->product->id, 'variant_id' => '', 'sale_price' => 890],
        ['product_id' => (string) $this->product->id, 'variant_id' => (string) $variant->id, 'sale_price' => 790],
        ['product_id' => $added->id, 'sale_price' => 300],
    ]))->assertSessionHasNoErrors();

    expect($whole->fresh()->sold_count)->toBe(4)
        ->and($specific->fresh()->sold_count)->toBe(6)
        ->and($sale->items()->where('product_id', $dropped->id)->exists())->toBeFalse()
        ->and($sale->items()->where('product_id', $added->id)->sole()->sold_count)->toBe(0);
});

test('cancelling an order after the sale was edited still reduces its sold count', function () {
    $sale = FlashSale::factory()->create();
    $item = $sale->items()->create(['product_id' => $this->product->id, 'sale_price' => 900, 'sold_count' => 3]);
    $address = ['name' => 'T', 'phone' => '01700000000', 'address_line_1' => 'x', 'city' => 'Dhaka', 'state' => 'Dhaka'];
    $order = new Order;
    $order->forceFill([
        'order_number' => 'ORD-FS-1', 'user_id' => User::factory()->create()->id, 'status' => OrderStatus::PROCESSING,
        'payment_status' => 'pending', 'payment_method' => 'cod', 'subtotal' => 900, 'shipping_cost' => 0, 'tax' => 0,
        'discount' => 0, 'total' => 900, 'shipping_address' => $address, 'billing_address' => $address,
    ])->saveQuietly();
    $orderItem = OrderItem::forceCreate([
        'order_id' => $order->id, 'product_id' => $this->product->id, 'flash_sale_item_id' => $item->id,
        'product_name' => 'P', 'product_snapshot' => ['name' => 'P'], 'quantity' => 2, 'price' => 900, 'subtotal' => 1800,
    ]);

    put(route('admin.flash-sales.update', $sale), scheduledSale([
        ['product_id' => $this->product->id, 'sale_price' => 850],
    ]))->assertSessionHasNoErrors();

    expect($orderItem->fresh()->flash_sale_item_id)->toBe($item->id);

    $order->update(['status' => OrderStatus::CANCELLED]);

    expect($item->fresh()->sold_count)->toBe(1);
});
