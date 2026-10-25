<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
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
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ])['ids'];

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

        return redirect()->route('admin.categories.index');
    }
}
