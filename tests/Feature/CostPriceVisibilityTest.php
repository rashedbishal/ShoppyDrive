<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * FSA-04: wholesale cost_price must never reach a storefront response. Each
 * check scans the whole rendered page payload (page props plus shared props
 * such as the cart), so a new prop that serializes a product can't leak it.
 */

/**
 * @return array{product: Product, variant: ProductVariant}
 */
function costedProduct(): array
{
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'name' => 'Costed Sneaker',
        'category_id' => $category->id,
        'price' => 300,
        'cost_price' => 123.45,
        'stock_quantity' => 0,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 300,
        'cost_price' => 111.11,
        'stock_quantity' => 5,
    ]);

    return ['product' => $product, 'variant' => $variant];
}

test('product detail page omits product and variant cost_price', function () {
    ['product' => $product] = costedProduct();

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('product.cost_price')
            ->has('product.variants.0', fn (Assert $variant) => $variant->missing('cost_price')->etc())
            ->etc())
        ->assertDontSee('cost_price');
});

test('listing, category and search pages omit cost_price', function (string $url) {
    costedProduct();

    $this->get($url)->assertOk()->assertSee('Costed Sneaker')->assertDontSee('cost_price');
})->with([
    'listing' => ['/products'],
    'search' => ['/search?q=Costed'],
]);

test('the shared cart and checkout props omit cost_price', function () {
    ['product' => $product, 'variant' => $variant] = costedProduct();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('cart.store'), ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    $this->get(route('cart.index'))
        ->assertInertia(fn (Assert $page) => $page->has('cart.items', 1)->etc())
        ->assertDontSee('cost_price');

    $this->get(route('checkout.index', ['buy_now_product' => $product->id, 'buy_now_variant' => $variant->id]))
        ->assertInertia(fn (Assert $page) => $page->has('buyNowItem.variant')->etc())
        ->assertDontSee('cost_price');
});

test('the admin product edit screen still receives cost_price', function () {
    ['product' => $product, 'variant' => $variant] = costedProduct();

    $this->actingAs(createMotherAdmin(), 'admin')
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.cost_price', 123.45)
            ->where('product.variants.0.id', $variant->id)
            ->where('product.variants.0.cost_price', 111.11)
            ->etc());
});
