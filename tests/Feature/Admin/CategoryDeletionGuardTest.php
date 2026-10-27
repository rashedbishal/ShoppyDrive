<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-category-guard@example.com',
        'password' => bcrypt('password'),
    ]);

    actingAs($this->admin, 'admin');
});

/**
 * Asserts the response flashed the blocked-delete error toast listing exactly $productIds.
 *
 * @param  array<int, int>  $productIds
 */
function assertDeletionBlocked($response, string $messageFragment, array $productIds): void
{
    $response->assertSessionHas('inertia.flash_data', function ($flash) use ($messageFragment, $productIds) {
        $toast = $flash['toast'] ?? null;

        return $toast !== null
            && $toast['type'] === 'error'
            && str_contains($toast['message'], $messageFragment)
            && collect($toast['products'])->pluck('id')->sort()->values()->all() === collect($productIds)->sort()->values()->all();
    });
}

// ── Sub-categories ───────────────────────────────────────────────────────────

test('an empty sub-category can be deleted', function () {
    $subCategory = SubCategory::factory()->create();

    $this->delete(route('admin.sub-categories.destroy', $subCategory))
        ->assertRedirect(route('admin.sub-categories.index'));

    expect(SubCategory::find($subCategory->id))->toBeNull();
});

test('deleting a sub-category that still has products is blocked and nothing is deleted', function () {
    $subCategory = SubCategory::factory()->create(['name' => 'Sneakers']);
    $product = Product::factory()->create(['category_id' => $subCategory->category_id, 'sub_category_id' => $subCategory->id]);

    $response = $this->delete(route('admin.sub-categories.destroy', $subCategory))
        ->assertRedirect(route('admin.sub-categories.index'));

    assertDeletionBlocked($response, "Can't delete \"Sneakers\" — it's used by 1 product(s)", [$product->id]);
    expect(SubCategory::find($subCategory->id))->not->toBeNull()
        ->and(Product::find($product->id))->not->toBeNull();
});

test('a soft-deleted product does not block deleting its sub-category', function () {
    $subCategory = SubCategory::factory()->create();
    Product::factory()->create(['category_id' => $subCategory->category_id, 'sub_category_id' => $subCategory->id])->delete();

    $this->delete(route('admin.sub-categories.destroy', $subCategory));

    expect(SubCategory::find($subCategory->id))->toBeNull();
});

test('bulk-deleting sub-categories is all-or-nothing when any still has products', function () {
    $empty = SubCategory::factory()->create();
    $used = SubCategory::factory()->create();
    $products = Product::factory()->count(2)->create(['category_id' => $used->category_id, 'sub_category_id' => $used->id]);

    $response = $this->delete(route('admin.sub-categories.destroyMany'), ['ids' => [$empty->id, $used->id]]);

    assertDeletionBlocked($response, '1 of the selected sub-category(ies) are used by 2 product(s)', $products->pluck('id')->all());
    expect(SubCategory::find($empty->id))->not->toBeNull()
        ->and(SubCategory::find($used->id))->not->toBeNull();
});

test('bulk-deleting empty sub-categories deletes them', function () {
    $subCategories = SubCategory::factory()->count(2)->create();

    $this->delete(route('admin.sub-categories.destroyMany'), ['ids' => $subCategories->pluck('id')->all()])
        ->assertRedirect(route('admin.sub-categories.index'));

    expect(SubCategory::whereIn('id', $subCategories->pluck('id'))->count())->toBe(0);
});

// ── Categories ───────────────────────────────────────────────────────────────

test('an empty category can be deleted along with its empty sub-categories', function () {
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);

    $this->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::find($category->id))->toBeNull()
        ->and(SubCategory::find($subCategory->id))->toBeNull();
});

test('deleting a category with a product filed directly under it is blocked', function () {
    $category = Category::factory()->create(['name' => 'Footwear']);
    $otherSubCategory = SubCategory::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'sub_category_id' => $otherSubCategory->id]);

    $response = $this->delete(route('admin.categories.destroy', $category));

    assertDeletionBlocked($response, "Can't delete \"Footwear\" — it's used by 1 product(s)", [$product->id]);
    expect(Category::find($category->id))->not->toBeNull()
        ->and(Product::find($product->id))->not->toBeNull();
});

test('deleting a category whose sub-category has a product is blocked', function () {
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);
    $product = Product::factory()->create(['category_id' => Category::factory(), 'sub_category_id' => $subCategory->id]);

    $response = $this->delete(route('admin.categories.destroy', $category));

    assertDeletionBlocked($response, 'used by 1 product(s)', [$product->id]);
    expect(Category::find($category->id))->not->toBeNull()
        ->and(SubCategory::find($subCategory->id))->not->toBeNull();
});

test('bulk-deleting categories is all-or-nothing when any still has products', function () {
    $empty = Category::factory()->create();
    $usedDirectly = Category::factory()->create();
    $usedViaSubCategory = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $usedViaSubCategory->id]);
    $direct = Product::factory()->create(['category_id' => $usedDirectly->id, 'sub_category_id' => SubCategory::factory()->create(['category_id' => $usedDirectly->id])->id]);
    $viaSub = Product::factory()->create(['category_id' => $usedViaSubCategory->id, 'sub_category_id' => $subCategory->id]);

    $response = $this->delete(route('admin.categories.destroyMany'), ['ids' => [$empty->id, $usedDirectly->id, $usedViaSubCategory->id]]);

    assertDeletionBlocked($response, '2 of the selected category(ies) are used by 2 product(s)', [$direct->id, $viaSub->id]);
    expect(Category::whereIn('id', [$empty->id, $usedDirectly->id, $usedViaSubCategory->id])->count())->toBe(3);
});

test('bulk-deleting empty categories deletes them', function () {
    $categories = Category::factory()->count(2)->create();

    $this->delete(route('admin.categories.destroyMany'), ['ids' => $categories->pluck('id')->all()])
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::whereIn('id', $categories->pluck('id'))->count())->toBe(0);
});
