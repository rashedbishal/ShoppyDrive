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

class ParentCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::whereNull('parent_id')
            ->withCount([
                'children',
                'products as direct_products_count',
            ])
            ->when($request->search, fn ($q) => $q->where('name', 'ilike', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/parent-categories/index', [
            'categories' => $categories,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/parent-categories/create');
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $data['parent_id'] = null;
        $data['is_active'] = $data['is_active'] ?? true;
        Category::create($data);

        return redirect()->route('admin.parent-categories.index')->with('success', 'Parent category created.');
    }

    public function edit(Category $parentCategory): Response
    {
        return Inertia::render('admin/parent-categories/edit', [
            'category' => $parentCategory->loadCount(['children', 'products as direct_products_count']),
        ]);
    }

    public function update(CategoryRequest $request, Category $parentCategory): RedirectResponse
    {
        $data = $request->validated();
        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }
        $data['parent_id'] = null;
        $data['is_active'] = $data['is_active'] ?? false;
        $parentCategory->update($data);

        return redirect()->route('admin.parent-categories.index')->with('success', 'Parent category updated.');
    }

    public function destroy(Category $parentCategory): RedirectResponse
    {
        $parentCategory->delete();

        return redirect()->route('admin.parent-categories.index')->with('success', 'Parent category deleted.');
    }
}
