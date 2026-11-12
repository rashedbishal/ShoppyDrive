<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * Product images are chosen per colour: an image with `attribute_value_id`
 * belongs to every variant of that colour, and a variant's `image` (cart,
 * checkout, orders, landing page) is its own image first, then its colour's.
 */
beforeEach(function () {
    $color = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);
    $size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    $this->red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red', 'color_hex' => '#ff0000']);
    $this->sizeM = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']);
    $this->sizeXl = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XL']);
});

function colorSizeProduct(): array
{
    $product = Product::factory()->create(['has_color_variant' => true, 'has_size_variant' => true, 'stock_quantity' => 10]);
    $variant = fn ($size) => ProductVariant::factory()->create([
        'product_id' => $product->id,
        'color_value_id' => test()->red->id,
        'size_value_id' => $size->id,
        'attributes' => ['Color' => 'Red', 'Size' => $size->value],
        'stock_quantity' => 5,
        'price' => 500,
    ]);

    return [$product, $variant(test()->sizeM), $variant(test()->sizeXl)];
}

function colorImage(Product $product, string $path, int $sortOrder, array $overrides = []): ProductMedia
{
    return ProductMedia::create(array_merge([
        'product_id' => $product->id,
        'attribute_value_id' => test()->red->id,
        'path' => $path,
        'type' => 'image',
        'is_primary' => false,
        'sort_order' => $sortOrder,
    ], $overrides));
}

test('a variant uses its own image first, otherwise its color\'s image', function () {
    [$product, $redM, $redXl] = colorSizeProduct();
    colorImage($product, '/storage/products/red.jpg', 1);
    colorImage($product, '/storage/products/red-m-only.jpg', 2, ['variant_id' => $redM->id, 'attribute_value_id' => null]);

    expect($redM->fresh()->image)->toBe('/storage/products/red-m-only.jpg');
    expect($redXl->fresh()->image)->toBe('/storage/products/red.jpg');
});

test('a video chosen for a color is never used as a variant thumbnail', function () {
    [$product, , $redXl] = colorSizeProduct();
    colorImage($product, '/storage/products/red.mp4', 1, ['type' => 'video']);

    expect($redXl->fresh()->image)->toBeNull();
});

test('the product page sends each variant its color image without repeating the gallery per variant', function () {
    [$product] = colorSizeProduct();
    colorImage($product, '/storage/products/red.jpg', 1);

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.variants.0.image', '/storage/products/red.jpg')
            ->where('product.variants.1.image', '/storage/products/red.jpg')
            ->missing('product.variants.0.media')
            ->where('product.media.0.attribute_value_id', $this->red->id));
});

test('a cart line for any size shows its color\'s image', function () {
    [$product, , $redXl] = colorSizeProduct();
    colorImage($product, '/storage/products/red.jpg', 1);

    actingAs(User::factory()->create())->postJson(route('cart.sync'), [
        'items' => [['product_id' => $product->id, 'variant_id' => $redXl->id, 'quantity' => 1]],
    ])->assertOk()->assertJsonPath('cart.items.0.variant.image', '/storage/products/red.jpg');
});

test('listing pages load variant images without a query per product', function () {
    $mediaQueries = function (): int {
        $count = 0;
        DB::listen(function ($query) use (&$count) {
            if (str_contains($query->sql, 'product_media')) {
                $count++;
            }
        });
        $this->get(route('products.index'))->assertOk();

        return $count;
    };

    [$first] = colorSizeProduct();
    colorImage($first, '/storage/products/red-1.jpg', 1);
    $withOneProduct = $mediaQueries();

    foreach ([2, 3, 4] as $n) {
        [$product] = colorSizeProduct();
        colorImage($product, "/storage/products/red-{$n}.jpg", 1);
    }

    expect($mediaQueries())->toBe($withOneProduct);
});
