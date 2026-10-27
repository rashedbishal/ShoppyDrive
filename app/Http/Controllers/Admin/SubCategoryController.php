<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubCategoryRequest;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SubCategoryController extends Controller
{
    use UpdatesStatusInBulk;

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

        SubCategory::whereIn('id', $ids)->delete();

        return redirect()->route('admin.sub-categories.index')->with('success', count($ids).' sub-category(ies) deleted.');
    }
}
