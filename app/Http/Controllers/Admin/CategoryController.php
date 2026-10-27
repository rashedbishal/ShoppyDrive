<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\FlashesDeletionBlockedByProducts;
use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Support\CatalogFilterCache;
use App\Support\LayoutCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use FlashesDeletionBlockedByProducts, UpdatesStatusInBulk;

    public function index(Request $request): Response
    {
        $categories = Category::withCount([
            'subCategories',
            'products as direct_products_count',
        ])
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/categories/create');
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = '/storage/'.$request->file('image')->store('categories', 'public');
        } else {
            $data['image'] = '/placeholder.svg';
        }
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = (Category::max('sort_order') ?? -1) + 1;
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('admin/categories/edit', [
            'category' => $category->loadCount(['subCategories', 'products as direct_products_count']),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = '/storage/'.$request->file('image')->store('categories', 'public');
        } else {
            unset($data['image']);
        }
        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }
        $data['is_active'] = $data['is_active'] ?? false;
        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Deleting a category would also delete (hide) every product in it
        // and its sub-categories, so refuse while it still has any — they
        // must be moved or deleted first.
        $products = $this->productsUsingCategories([$category->id]);

        if ($products->isNotEmpty()) {
            $this->flashDeletionBlockedByProducts(
                "Can't delete \"{$category->name}\" — it's used by {$products->count()} product(s). Move or delete those products first.",
                $products,
            );

            return redirect()->route('admin.categories.index');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, Category::class);

        return redirect()->route('admin.categories.index')->with('success', $result['count'].' category(ies) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ])['ids'];

        $products = $this->productsUsingCategories($ids);

        // All-or-nothing, like brands: any category still in use blocks the
        // whole batch rather than silently skipping it.
        if ($products->isNotEmpty()) {
            $blockedCount = $products->pluck('category_id')
                ->merge(SubCategory::whereIn('id', $products->pluck('sub_category_id')->filter())->pluck('category_id'))
                ->intersect($ids)
                ->unique()
                ->count();

            $this->flashDeletionBlockedByProducts(
                "Can't delete — {$blockedCount} of the selected category(ies) are used by {$products->count()} product(s). Move or delete those products first.",
                $products,
            );

            return redirect()->route('admin.categories.index');
        }

        Category::whereIn('id', $ids)->get()->each(fn (Category $category) => $category->delete());

        return redirect()->route('admin.categories.index')->with('success', count($ids).' category(ies) deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ])['ids'];

        foreach ($ids as $index => $id) {
            Category::whereKey($id)->update(['sort_order' => $index]);
        }

        // Bulk update() fires no model events, so clear the menu cache and
        // the product-listing filters (which list categories in this order).
        LayoutCache::forget(...Category::LAYOUT_CACHE_KEYS);
        CatalogFilterCache::flush();

        return redirect()->route('admin.categories.index');
    }

    /**
     * Products filed directly under any of these categories or under one of
     * their sub-categories (a product's category_id isn't guaranteed to match
     * its sub-category's parent, so both are checked).
     *
     * @param  array<int, int>  $categoryIds
     * @return Collection<int, Product>
     */
    private function productsUsingCategories(array $categoryIds): Collection
    {
        $subCategoryIds = SubCategory::whereIn('category_id', $categoryIds)->pluck('id');

        return Product::query()
            ->where(fn ($query) => $query
                ->whereIn('category_id', $categoryIds)
                ->orWhereIn('sub_category_id', $subCategoryIds))
            ->get(['id', 'name', 'slug', 'category_id', 'sub_category_id']);
    }
}
