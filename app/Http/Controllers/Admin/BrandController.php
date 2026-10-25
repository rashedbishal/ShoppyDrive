<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use App\Models\HomepageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $brands = Brand::withCount('products')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')->paginate(20)->withQueryString();

        return Inertia::render('admin/brands/index', [
            'brands' => $brands,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/brands/create');
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('logo')) {
            $data['logo'] = '/storage/'.$request->file('logo')->store('brands', 'public');
        }
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;
        $brand = Brand::create($data);

        $this->syncHomepageSetting($brand);

        return redirect()->route('admin.brands.index')->with('success', 'Brand created.');
    }

    public function edit(Brand $brand): Response
    {
        return Inertia::render('admin/brands/edit', ['brand' => $brand]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('logo')) {
            $data['logo'] = '/storage/'.$request->file('logo')->store('brands', 'public');
        }
        $data['is_active'] = $data['is_active'] ?? false;
        $brand->update($data);

        $this->syncHomepageSetting($brand);

        return redirect()->route('admin.brands.index')->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->is_active = false;
        $this->syncHomepageSetting($brand);
        $brand->delete();

        return redirect()->route('admin.brands.index')->with('success', 'Brand deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:brands,id'],
        ])['ids'];

        foreach (Brand::whereIn('id', $ids)->get() as $brand) {
            $brand->is_active = false;
            $this->syncHomepageSetting($brand);
            $brand->delete();
        }

        return redirect()->route('admin.brands.index')->with('success', count($ids).' brand(s) deleted.');
    }

    private function syncHomepageSetting(Brand $brand): void
    {
        $setting = HomepageSetting::where('section_key', 'brands')->first();
        if ($setting) {
            $metadata = $setting->metadata ?? [];
            $brandIds = $metadata['brand_ids'] ?? [];

            if ($brand->is_active) {
                if (! in_array($brand->id, $brandIds)) {
                    $brandIds[] = $brand->id;
                }
            } else {
                $brandIds = array_values(array_filter($brandIds, fn ($id) => $id !== $brand->id));
            }

            $metadata['brand_ids'] = $brandIds;
            $setting->update(['metadata' => $metadata]);
        }
    }
}
