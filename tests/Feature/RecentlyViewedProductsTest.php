<?php

use App\Http\Controllers\ProductController;
use App\Models\Category;
use App\Models\Product;

/*
 * DISC-70: the product page's "Recently Viewed" row loads its cards from
 * the server by id, so a product an admin hides disappears from it, while
 * an out-of-stock product (still on sale) stays.
 */

/**
 * @param  list<int|string>  $ids
 * @return list<int>
 */
function recentlyViewedIdsFor(array $ids): array
{
    return test()->getJson(route('products.recently-viewed', ['ids' => $ids]))
        ->assertOk()
        ->json('products.*.id');
}

test('a product made inactive in the CMS drops out of recently viewed', function () {
    $kept = Product::factory()->create();
    $hidden = Product::factory()->create();

    expect(recentlyViewedIdsFor([$kept->id, $hidden->id]))->toBe([$kept->id, $hidden->id]);

    $hidden->update(['is_active' => false]);

    expect(recentlyViewedIdsFor([$kept->id, $hidden->id]))->toBe([$kept->id]);
});

test('recently viewed leaves out every product the storefront hides', function (array $attributes) {
    $hidden = Product::factory()->create($attributes);

    expect(recentlyViewedIdsFor([$hidden->id]))->toBe([]);
})->with([
    'draft' => [['published_at' => null]],
    'future-scheduled' => [['published_at' => now()->addDay()]],
    'inactive category' => [fn (): array => ['category_id' => Category::factory()->create(['is_active' => false])->id]],
]);

test('an out-of-stock product stays in recently viewed with its live stock', function () {
    $soldOut = Product::factory()->create(['stock_quantity' => 0]);

    $this->getJson(route('products.recently-viewed', ['ids' => [$soldOut->id]]))
        ->assertOk()
        ->assertJsonPath('products.0.id', $soldOut->id)
        ->assertJsonPath('products.0.stock_quantity', 0);
});

test('recently viewed keeps the viewing order and ignores junk and extra ids', function () {
    $products = Product::factory()->count(ProductController::RECENTLY_VIEWED_LIMIT + 1)->create();
    $newestFirst = $products->pluck('id')->reverse()->values()->all();

    expect(recentlyViewedIdsFor(['abc', '-3', $newestFirst[0], $newestFirst[0], ...array_slice($newestFirst, 1)]))
        ->toBe(array_slice($newestFirst, 0, ProductController::RECENTLY_VIEWED_LIMIT));
});

test('recently viewed with no ids returns no products', function () {
    $this->getJson(route('products.recently-viewed'))
        ->assertOk()
        ->assertExactJson(['products' => []]);
});
