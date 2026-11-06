<?php

declare(strict_types=1);

/*
 * DB-001: deleting a brand used to cascade to trashed products still using
 * it, permanently destroying their reviews and stock history. The brand
 * delete now only clears their brand_id. Active products still block it.
 */

use App\Models\Brand;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('deleting a brand keeps its trashed products and their reviews, with no brand', function () {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);
    $review = Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 5, 'body' => 'Great', 'is_approved' => true]);
    $product->delete();

    actingAs(createMotherAdmin(), 'admin')->delete(route('admin.brands.destroy', $brand))->assertRedirect();

    expect(Brand::whereKey($brand->id)->exists())->toBeFalse();

    $survivor = Product::withTrashed()->find($product->id);
    expect($survivor)->not->toBeNull()
        ->and($survivor->trashed())->toBeTrue()
        ->and($survivor->brand_id)->toBeNull()
        ->and(Review::whereKey($review->id)->exists())->toBeTrue();
});

test('an active product still blocks deleting its brand', function () {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    actingAs(createMotherAdmin(), 'admin')->delete(route('admin.brands.destroy', $brand))->assertRedirect();

    expect(Brand::whereKey($brand->id)->exists())->toBeTrue()
        ->and($product->refresh()->brand_id)->toBe($brand->id);
});
