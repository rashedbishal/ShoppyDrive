<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\FlashesDeletionBlockedByProducts;
use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubCategoryRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SubCategoryController extends Controller
{
    use FlashesDeletionBlockedByProducts, UpdatesStatusInBulk;

    public function index(Request $request): Response
    {
        $subCategories = SubCategory::with('category:id,name')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(20)->withQueryString();

        return Inertia::render('admin/sub-categories/index', [
            'subCategories' => $subCategories,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sub-categories/create', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SubCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = '/storage/'.$request->file('image')->store('sub-categories', 'public');
        } else {
            $data['image'] = '/placeholder.svg';
        }
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;
        SubCategory::create($data);

        return redirect()->route('admin.sub-categories.index')->with('success', 'Sub-category created.');
    }

    public function edit(SubCategory $subCategory): Response
    {
        return Inertia::render('admin/sub-categories/edit', [
            'subCategory' => $subCategory,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(SubCategoryRequest $request, SubCategory $subCategory): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = '/storage/'.$request->file('image')->store('sub-categories', 'public');
        } else {
            unset($data['image']);
        }
        $data['is_active'] = $data['is_active'] ?? false;
        $subCategory->update($data);

        return redirect()->route('admin.sub-categories.index')->with('success', 'Sub-category updated.');
    }

    public function destroy(SubCategory $subCategory): RedirectResponse
    {
        // Deleting a sub-category would also delete (hide) every product in
        // it, so refuse while it still has any — they must be moved or
        // deleted first.
        $products = $subCategory->products()->get(['id', 'name', 'slug']);

        if ($products->isNotEmpty()) {
            $this->flashDeletionBlockedByProducts(
                "Can't delete \"{$subCategory->name}\" — it's used by {$products->count()} product(s). Move or delete those products first.",
                $products,
            );

            return redirect()->route('admin.sub-categories.index');
        }

        $subCategory->delete();

        return redirect()->route('admin.sub-categories.index')->with('success', 'Sub-category deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, SubCategory::class);

        return redirect()->route('admin.sub-categories.index')->with('success', $result['count'].' sub-category(ies) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:sub_categories,id'],
        ])['ids'];

        $subCategories = SubCategory::whereIn('id', $ids)->get();
        $products = Product::whereIn('sub_category_id', $ids)->get(['id', 'name', 'slug', 'sub_category_id']);

        // All-or-nothing, like brands: any sub-category still in use blocks
        // the whole batch rather than silently skipping it.
        if ($products->isNotEmpty()) {
            $blockedCount = $products->pluck('sub_category_id')->unique()->count();

            $this->flashDeletionBlockedByProducts(
                "Can't delete — {$blockedCount} of the selected sub-category(ies) are used by {$products->count()} product(s). Move or delete those products first.",
                $products,
            );

            return redirect()->route('admin.sub-categories.index');
        }

        // Per-model delete (not a query-builder delete) so the model's
        // deleting hook runs, same as single delete.
        $subCategories->each(fn (SubCategory $subCategory) => $subCategory->delete());

        return redirect()->route('admin.sub-categories.index')->with('success', count($ids).' sub-category(ies) deleted.');
    }
}
