<?php

declare(strict_types=1);

use App\Console\Commands\GenerateSitemapCommand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SlugRedirect;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * DISC-91 (SEO audit SEO-29 / F-08): a renamed product, category or
 * sub-category's old URL 301s to its current one, in one hop, only while
 * the record may be shown; live pages always win. SSR is off, as in
 * production.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

afterEach(function () {
    @unlink(GenerateSitemapCommand::path());
});

/** The test's one category, found by name so it's the same record after its slug is renamed. */
function redirectKitchen(): Category
{
    return Category::firstOrCreate(['name' => 'Kitchen'], ['slug' => 'kitchen', 'is_active' => true]);
}

function redirectableProduct(string $slug): Product
{
    $category = redirectKitchen();

    return Product::factory()->create([
        'name' => 'Frying Pan', 'slug' => $slug, 'category_id' => $category->id, 'sub_category_id' => null,
        'brand_id' => null, 'is_active' => true, 'published_at' => now()->subDay(),
    ]);
}

function renameSlug(Product|Category|SubCategory $record, string $slug): void
{
    $record->update(['slug' => $slug]);
}

// ── Basic redirects ─────────────────────────────────────────────────────────

test('a renamed product\'s old URL 301s to its current URL, which loads', function () {
    $product = redirectableProduct('old-pan');
    renameSlug($product, 'new-pan');

    $this->get('/product/old-pan')->assertStatus(301)->assertRedirect(route('product.show', 'new-pan'));
    $this->get(route('product.show', 'new-pan'))->assertOk();
});

test('a renamed category or sub-category\'s old URL 301s to its current /category/ URL', function () {
    $kitchen = redirectKitchen();
    $pots = SubCategory::factory()->create(['name' => 'Pots', 'slug' => 'pots', 'category_id' => $kitchen->id, 'is_active' => true]);

    renameSlug($kitchen, 'kitchen-dining');
    renameSlug($pots, 'cooking-pots');

    $this->get('/category/kitchen')->assertStatus(301)->assertRedirect(route('category.show', 'kitchen-dining'));
    $this->get('/category/pots')->assertStatus(301)->assertRedirect(route('category.show', 'cooking-pots'));
    $this->get(route('category.show', 'kitchen-dining'))->assertOk();
    $this->get(route('category.show', 'cooking-pots'))->assertOk();
});

test('the query string goes along with the redirect', function () {
    $kitchen = redirectKitchen();
    renameSlug($kitchen, 'kitchen-dining');
    renameSlug(redirectableProduct('old-pan'), 'new-pan');

    $this->get('/category/kitchen?page=2&sort=price_asc')
        ->assertRedirect(route('category.show', 'kitchen-dining').'?page=2&sort=price_asc');
    $this->get('/product/old-pan?ref=newsletter')
        ->assertRedirect(route('product.show', 'new-pan').'?ref=newsletter');
});

test('posting a review to an old product URL is not redirected', function () {
    renameSlug(redirectableProduct('old-pan'), 'new-pan');
    actingAs(User::factory()->create());

    $this->post('/product/old-pan/reviews', ['rating' => 5, 'comment' => 'Great'])->assertNotFound();
});

// ── Chains and loops ────────────────────────────────────────────────────────

test('a → b → c sends both a and b straight to c', function () {
    $product = redirectableProduct('a-pan');
    renameSlug($product, 'b-pan');
    renameSlug($product, 'c-pan');

    $this->get('/product/a-pan')->assertStatus(301)->assertRedirect(route('product.show', 'c-pan'));
    $this->get('/product/b-pan')->assertStatus(301)->assertRedirect(route('product.show', 'c-pan'));
});

test('a → b → a serves the page at a again, with no loop and no row for a', function () {
    $product = redirectableProduct('a-pan');
    renameSlug($product, 'b-pan');
    renameSlug($product, 'a-pan');

    $this->get('/product/a-pan')->assertOk();
    $this->get('/product/b-pan')->assertRedirect(route('product.show', 'a-pan'));
    expect(SlugRedirect::where('old_slug', 'a-pan')->exists())->toBeFalse();
});

// ── Collisions and reassignment ─────────────────────────────────────────────

test('a live page always wins over an old slug pointing elsewhere', function () {
    renameSlug(redirectableProduct('pan'), 'big-pan');
    $newcomer = Product::factory()->create([
        'name' => 'Another Pan', 'slug' => 'pan', 'category_id' => redirectKitchen()->id, 'sub_category_id' => null,
        'brand_id' => null, 'is_active' => true, 'published_at' => now()->subDay(),
    ]);

    $this->get('/product/pan')->assertOk()->assertInertia(fn ($page) => $page->where('product.id', $newcomer->id));
    expect(SlugRedirect::where('old_slug', 'pan')->exists())->toBeFalse();
});

test('an old slug given up again by a later record now points at that later record', function () {
    $first = redirectableProduct('pan');
    renameSlug($first, 'first-pan');
    $second = Product::factory()->create([
        'name' => 'Second', 'slug' => 'pan', 'category_id' => redirectKitchen()->id, 'sub_category_id' => null,
        'brand_id' => null, 'is_active' => true, 'published_at' => now()->subDay(),
    ]);
    renameSlug($second, 'second-pan');

    expect(SlugRedirect::where('namespace', 'product')->where('old_slug', 'pan')->count())->toBe(1);
    $this->get('/product/pan')->assertRedirect(route('product.show', 'second-pan'));
});

test('categories and sub-categories share one /category/ namespace, separate from products', function () {
    $kitchen = redirectKitchen();
    $pots = SubCategory::factory()->create(['slug' => 'pots', 'category_id' => $kitchen->id, 'is_active' => true]);
    renameSlug($pots, 'cooking-pots');
    renameSlug(redirectableProduct('pots'), 'steel-pots');

    expect(SlugRedirect::where('old_slug', 'pots')->pluck('namespace')->sort()->values()->all())->toBe(['category', 'product']);
    $this->get('/category/pots')->assertRedirect(route('category.show', 'cooking-pots'));
    $this->get('/product/pots')->assertRedirect(route('product.show', 'steel-pots'));

    // A category created on the freed slug takes it over.
    Category::create(['name' => 'Pots', 'slug' => 'pots', 'is_active' => true]);
    $this->get('/category/pots')->assertOk();
    expect(SlugRedirect::where('namespace', 'category')->where('old_slug', 'pots')->exists())->toBeFalse();
});

test('DISC-89 still refuses a sub-category slug a category uses', function () {
    actingAs(createAdmin(), 'admin');
    $kitchen = redirectKitchen();

    $this->post(route('admin.sub-categories.store'), ['name' => 'Kitchen', 'slug' => 'kitchen', 'category_id' => $kitchen->id])
        ->assertSessionHasErrors('slug');
});

test('a rename through the admin form is recorded, after the form normalises the slug', function () {
    actingAs(createAdmin(), 'admin');
    $kitchen = redirectKitchen();

    $this->put(route('admin.categories.update', $kitchen), ['name' => 'Kitchen', 'slug' => 'Kitchen Dining', 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect($kitchen->fresh()->slug)->toBe('kitchen-dining');
    auth('admin')->logout();
    $this->get('/category/kitchen')->assertRedirect(route('category.show', 'kitchen-dining'));
});

// ── Invalid targets ─────────────────────────────────────────────────────────

test('no redirect to a product customers can\'t see', function (string $hide) {
    $product = redirectableProduct('old-pan');
    renameSlug($product, 'new-pan');

    match ($hide) {
        'inactive' => $product->update(['is_active' => false]),
        'draft' => $product->update(['published_at' => null]),
        'soft-deleted' => $product->delete(),
        'category off' => redirectKitchen()->update(['is_active' => false]),
    };

    $this->get('/product/old-pan')->assertNotFound();
})->with(['inactive', 'draft', 'soft-deleted', 'category off']);

test('no redirect to an inactive or deleted category or sub-category', function () {
    $kitchen = redirectKitchen();
    $pots = SubCategory::factory()->create(['slug' => 'pots', 'category_id' => $kitchen->id, 'is_active' => true]);
    $pans = SubCategory::factory()->create(['slug' => 'pans', 'category_id' => $kitchen->id, 'is_active' => true]);
    renameSlug($pots, 'cooking-pots');
    renameSlug($pans, 'frying-pans');
    renameSlug($kitchen, 'kitchen-dining');

    $pots->update(['is_active' => false]);
    $pans->delete();
    $this->get('/category/pots')->assertNotFound();
    $this->get('/category/pans')->assertNotFound();

    $kitchen->update(['is_active' => false]);
    $this->get('/category/kitchen')->assertNotFound();
});

test('a hidden record that still holds the slug keeps its 404 rather than redirecting', function () {
    $kitchen = redirectKitchen();
    $pots = SubCategory::factory()->create(['slug' => 'pots', 'category_id' => $kitchen->id, 'is_active' => true]);
    renameSlug($pots, 'cooking-pots');
    // Old row 'pots' points at the sub-category; a hidden category now holds 'pots'.
    SlugRedirect::create(['namespace' => 'category', 'old_slug' => 'hidden', 'redirectable_type' => $pots->getMorphClass(), 'redirectable_id' => $pots->id]);
    Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

    $this->get('/category/hidden')->assertNotFound();
});

test('a permanent delete removes the record\'s redirects, including cascaded ones', function () {
    $product = redirectableProduct('old-pan');
    renameSlug($product, 'new-pan');
    $product->forceDelete();
    expect(SlugRedirect::count())->toBe(0);

    $kitchen = redirectKitchen();
    $pots = SubCategory::factory()->create(['slug' => 'pots', 'category_id' => $kitchen->id]);
    $lid = Product::factory()->create(['slug' => 'lid', 'category_id' => $kitchen->id, 'sub_category_id' => $pots->id]);
    renameSlug($pots, 'cooking-pots');
    renameSlug($lid, 'pot-lid');
    renameSlug($kitchen, 'kitchen-dining');
    expect(SlugRedirect::count())->toBe(3);

    $kitchen->forceDelete();

    expect(SlugRedirect::count())->toBe(0);
});

test('a soft delete keeps the redirect, which works again after a restore', function () {
    $product = redirectableProduct('old-pan');
    renameSlug($product, 'new-pan');
    $product->delete();
    $this->get('/product/old-pan')->assertNotFound();

    $product->restore();

    $this->get('/product/old-pan')->assertRedirect(route('product.show', 'new-pan'));
});

test('unknown slugs are still 404', function () {
    redirectKitchen();

    $this->get('/product/never-existed')->assertNotFound();
    $this->get('/category/never-existed')->assertNotFound();
});

// ── SEO compatibility ───────────────────────────────────────────────────────

test('the destination keeps its own canonical and title, and the sitemap lists only current URLs', function () {
    $kitchen = redirectKitchen();
    $kitchen->update(['meta_title' => 'Kitchenware']);
    renameSlug($kitchen, 'kitchen-dining');
    renameSlug(redirectableProduct('old-pan'), 'new-pan');

    $html = $this->get(route('category.show', 'kitchen-dining'))->assertOk()->getContent();
    expect($html)->toContain('rel="canonical" href="'.route('category.show', 'kitchen-dining').'"')
        ->toContain('<title>Kitchenware - '.config('app.name').'</title>');

    $this->artisan('sitemap:generate')->assertSuccessful();
    $locs = array_map('strval', (new SimpleXMLElement((string) file_get_contents(GenerateSitemapCommand::path())))->xpath('//*[local-name()="loc"]'));

    expect($locs)->toContain(route('category.show', 'kitchen-dining'))
        ->toContain(route('product.show', 'new-pan'))
        ->not->toContain(route('category.show', 'kitchen'))
        ->not->toContain(route('product.show', 'old-pan'));
});
