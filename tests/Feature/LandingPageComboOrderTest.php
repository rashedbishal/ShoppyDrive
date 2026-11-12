<?php

declare(strict_types=1);

use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/*
 * Landing-page combos: a set of the promoted product's own variants (a couple
 * set of Bubu + Dudu) sold at the sum of its parts or one fixed price. It is
 * expanded into ordinary variant order lines, so stock always comes from the
 * real variants.
 */
beforeEach(function () {
    Mail::fake();
    $this->admin = createMotherAdmin();

    $this->product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 0]);
    $this->bubu = ProductVariant::factory()->create(['product_id' => $this->product->id, 'price' => 600, 'stock_quantity' => 10, 'attributes' => ['Character' => 'Bubu']]);
    $this->dudu = ProductVariant::factory()->create(['product_id' => $this->product->id, 'price' => 700, 'stock_quantity' => 4, 'attributes' => ['Character' => 'Dudu']]);

    $this->page = LandingPage::create([
        'title' => 'Bubu Dudu', 'slug' => 'bubu-'.uniqid(), 'product_id' => $this->product->id, 'is_active' => true,
        'sections' => ['combos' => [
            ['id' => 'couple', 'name' => 'Couple set', 'description' => '', 'badge' => 'Best value', 'price' => 1000, 'items' => [
                ['variant_id' => $this->bubu->id, 'quantity' => 1],
                ['variant_id' => $this->dudu->id, 'quantity' => 1],
            ]],
            ['id' => 'sum', 'name' => 'Sum set', 'price' => null, 'items' => [
                ['variant_id' => $this->bubu->id, 'quantity' => 1],
                ['variant_id' => $this->dudu->id, 'quantity' => 1],
            ]],
        ]],
    ]);
});

function comboPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Customer', 'phone' => '01712345678', 'email' => 'c@example.com',
        'address_line1' => '123 Main St', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
    ], $overrides);
}

test('the page lists each combo with its live price, saving and how many sets are in stock', function () {
    get(route('lp.show', $this->page->slug))->assertOk()->assertInertia(fn ($p) => $p
        ->has('combos', 2)
        ->where('combos.0.id', 'couple')
        ->where('combos.0.price', 1000)
        ->where('combos.0.compare_price', 1300)
        ->where('combos.0.stock_quantity', 4)
        ->where('combos.1.price', 1300)
        ->where('combos.1.compare_price', null));
});

test('a combo whose variant no longer exists is not offered', function () {
    $this->dudu->delete();

    get(route('lp.show', $this->page->slug))->assertOk()->assertInertia(fn ($p) => $p->has('combos', 0));
});

test('ordering a fixed-price combo creates one line per variant that adds up to the combo price', function () {
    post(route('lp.order', $this->page->slug), comboPayload(['combos' => [['combo_id' => 'couple', 'quantity' => 2]]]))->assertSessionHasNoErrors();

    $order = Order::query()->with('items')->sole();

    expect($order->items)->toHaveCount(2)
        ->and((float) $order->subtotal)->toBe(2000.0)
        ->and($order->items->sum(fn ($item) => (float) $item->subtotal))->toBe(2000.0)
        ->and($order->items->pluck('quantity')->all())->toBe([2, 2])
        ->and($order->items->first()->product_snapshot['combo']['name'])->toBe('Couple set')
        ->and($this->bubu->refresh()->stock_quantity)->toBe(8)
        ->and($this->dudu->refresh()->stock_quantity)->toBe(2);
});

test('a combo without a fixed price charges what its variants cost on their own', function () {
    post(route('lp.order', $this->page->slug), comboPayload(['combos' => [['combo_id' => 'sum', 'quantity' => 1]]]))->assertSessionHasNoErrors();

    expect((float) Order::query()->sole()->subtotal)->toBe(1300.0);
});

test('a combo and a single variant can be ordered together, each taking its own stock', function () {
    post(route('lp.order', $this->page->slug), comboPayload([
        'combos' => [['combo_id' => 'couple', 'quantity' => 1]],
        'items' => [['variant_id' => $this->bubu->id, 'quantity' => 2]],
    ]))->assertSessionHasNoErrors();

    $order = Order::query()->with('items')->sole();

    expect($order->items)->toHaveCount(3)
        ->and((float) $order->subtotal)->toBe(2200.0)
        ->and($this->bubu->refresh()->stock_quantity)->toBe(7)
        ->and($this->dudu->refresh()->stock_quantity)->toBe(3);
});

test('a combo that needs more of a variant than is in stock places no order and changes no stock', function () {
    post(route('lp.order', $this->page->slug), comboPayload(['combos' => [['combo_id' => 'couple', 'quantity' => 5]]]))
        ->assertSessionHasErrors();

    expect(Order::query()->count())->toBe(0)
        ->and($this->bubu->refresh()->stock_quantity)->toBe(10)
        ->and($this->dudu->refresh()->stock_quantity)->toBe(4);
});

test('an unknown combo id is refused', function () {
    post(route('lp.order', $this->page->slug), comboPayload(['combos' => [['combo_id' => 'nope', 'quantity' => 1]]]))
        ->assertSessionHasErrors('combos.0.combo_id');

    expect(Order::query()->count())->toBe(0);
});

test('combo units count toward the page limit', function () {
    $this->page->update(['max_order_quantity' => 3]);

    post(route('lp.order', $this->page->slug), comboPayload(['combos' => [['combo_id' => 'couple', 'quantity' => 2]]]))
        ->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('an order with neither options nor combos is refused', function () {
    post(route('lp.order', $this->page->slug), comboPayload())->assertSessionHasErrors('items');
});

test('the optional order note is saved on the order', function () {
    post(route('lp.order', $this->page->slug), comboPayload(['items' => [['variant_id' => $this->bubu->id, 'quantity' => 1]], 'notes' => 'Please gift wrap']))
        ->assertSessionHasNoErrors();

    expect(Order::query()->sole()->notes)->toBe('Please gift wrap');
});

test('the confirmation page shows the page contact details, falling back to the site contact details', function () {
    post(route('lp.order', $this->page->slug), comboPayload(['items' => [['variant_id' => $this->bubu->id, 'quantity' => 1]]]));
    $guestToken = Order::query()->sole()->guest_token;
    Setting::updateOrCreate(['key' => 'contact_phone'], ['value' => '01900000000', 'group' => 'general']);

    get(route('lp.confirmation', $guestToken))->assertInertia(fn ($p) => $p->where('support.phone', '01900000000'));

    $this->page->update(['sections' => [...$this->page->sections, 'contact' => ['phone' => '01811111111', 'email' => 'help@example.com']]]);

    get(route('lp.confirmation', $guestToken))->assertInertia(fn ($p) => $p
        ->where('support.phone', '01811111111')
        ->where('support.email', 'help@example.com'));
});

test('the admin can save combos built from the page product\'s variants', function () {
    $sections = $this->page->getSections();
    $sections['combos'] = [['id' => 'trio', 'name' => 'Trio', 'price' => 1500, 'items' => [['variant_id' => $this->bubu->id, 'quantity' => 2], ['variant_id' => $this->dudu->id, 'quantity' => 1]]]];

    actingAs($this->admin, 'admin')
        ->put(route('admin.landing-pages.update', $this->page), ['title' => 'Bubu Dudu', 'slug' => $this->page->slug, 'product_id' => $this->product->id, 'is_active' => true, 'sections' => $sections])
        ->assertSessionHasNoErrors();

    expect($this->page->refresh()->sections['combos'][0]['id'])->toBe('trio');
});

test('the admin cannot save a combo containing another product\'s variant', function () {
    $other = ProductVariant::factory()->create(['stock_quantity' => 5]);
    $sections = $this->page->getSections();
    $sections['combos'] = [['id' => 'bad', 'name' => 'Bad', 'price' => 1, 'items' => [['variant_id' => $other->id, 'quantity' => 1]]]];

    actingAs($this->admin, 'admin')
        ->put(route('admin.landing-pages.update', $this->page), ['title' => 'Bubu Dudu', 'slug' => $this->page->slug, 'product_id' => $this->product->id, 'is_active' => true, 'sections' => $sections])
        ->assertSessionHasErrors('sections.combos.0.items.0.variant_id');
});
