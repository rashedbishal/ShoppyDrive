<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function setDisplayOrder(string $scenario, string $order): void
{
    Setting::updateOrCreate(['key' => "display_order.{$scenario}"], ['value' => $order, 'group' => 'display_order']);
}

test('related products show newest first by default', function () {
    $category = Category::factory()->create();
    $older = Product::factory()->create(['category_id' => $category->id, 'is_active' => true, 'published_at' => now()->subDays(2)]);
    $viewed = Product::factory()->create(['category_id' => $category->id, 'is_active' => true, 'published_at' => now()->subDays(5)]);
    $newer = Product::factory()->create(['category_id' => $category->id, 'is_active' => true, 'published_at' => now()->subDay()]);

    $this->get(route('product.show', $viewed->slug))
        ->assertInertia(fn ($page) => $page
            ->where('relatedProducts.0.id', $newer->id)
            ->where('relatedProducts.1.id', $older->id)
        );
});

test('related products are genuinely randomized when set to random', function () {
    setDisplayOrder('related_products', 'random');
    $category = Category::factory()->create();
    $viewed = Product::factory()->create(['category_id' => $category->id, 'is_active' => true, 'published_at' => now()->subDay()]);
    Product::factory()->count(3)->create(['category_id' => $category->id, 'is_active' => true, 'published_at' => now()->subDay()]);

    DB::enableQueryLog();
    $this->get(route('product.show', $viewed->slug))->assertOk();
    $query = collect(DB::getQueryLog())->first(fn ($q) => str_contains($q['query'], 'from "products"') && str_contains($q['query'], 'category_id'));
    DB::disableQueryLog();

    expect($query)->not->toBeNull()
        ->and(mb_strtolower($query['query']))->toContain('random()');
});

test('the wishlist shows the most recently added item first by default', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $first = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $second = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $first->id]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $second->id]);

    actingAs($user)
        ->get(route('account.wishlist'))
        ->assertInertia(fn ($page) => $page
            ->where('wishlist.0.id', $second->id)
            ->where('wishlist.1.id', $first->id)
        );
});

test('a brand\'s product list shows newest first by default', function () {
    $brand = Brand::factory()->create(['is_active' => true]);
    $older = Product::factory()->create(['brand_id' => $brand->id, 'is_active' => true, 'published_at' => now()->subDays(2)]);
    $newer = Product::factory()->create(['brand_id' => $brand->id, 'is_active' => true, 'published_at' => now()->subDay()]);

    $this->get(route('brand.show', $brand->slug))
        ->assertInertia(fn ($page) => $page
            ->where('products.data.0.id', $newer->id)
            ->where('products.data.1.id', $older->id)
        );
});

test('bundle deals show newest first by default on the public page', function () {
    $older = ProductBundle::factory()->create(['is_active' => true, 'created_at' => now()->subDays(2)]);
    $newer = ProductBundle::factory()->create(['is_active' => true, 'created_at' => now()->subDay()]);

    foreach ([$older, $newer] as $bundle) {
        ProductBundleItem::factory()->count(2)->create(['product_bundle_id' => $bundle->id]);
    }

    $this->get(route('bundles.index'))
        ->assertInertia(fn ($page) => $page
            ->where('bundles.0.id', $newer->id)
            ->where('bundles.1.id', $older->id)
        );
});

test('flash sale items show newest-added first by default on the home page', function () {
    $sale = FlashSale::factory()->create(['is_active' => true, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    $older = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $newer = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $olderItem = FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $older->id, 'variant_id' => null, 'created_at' => now()->subMinute()]);
    $newerItem = FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $newer->id, 'variant_id' => null, 'created_at' => now()]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('activeFlashSale.items.0.id', $newerItem->id)
            ->where('activeFlashSale.items.1.id', $olderItem->id)
        );
});
