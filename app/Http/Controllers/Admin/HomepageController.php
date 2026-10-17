<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomepageSectionRequest;
use App\Models\Brand;
use App\Models\HomepageSetting;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    public function index(): Response
    {
        $sections = HomepageSetting::orderBy('sort_order')->get();

        return Inertia::render('admin/homepage/index', [
            'sections' => $sections,
        ]);
    }

    public function edit(HomepageSetting $setting): Response
    {
        $data = ['section' => $setting];

        if ($setting->section_key === 'featured_products') {
            $data['featuredProducts'] = Product::active()
                ->published()
                ->featured()
                ->orderBy('name')
                ->get(['id', 'name', 'price']);
        }

        if ($setting->section_key === 'new_arrivals' || $setting->section_key === 'best_sellers') {
            $productIds = $setting->metadata['product_ids'] ?? [];
            $data['sectionProducts'] = Product::active()
                ->published()
                ->whereIn('id', $productIds)
                ->orderBy('name')
                ->get(['id', 'name', 'price']);
        }

        if ($setting->section_key === 'brands') {
            $brandIds = $setting->metadata['brand_ids'] ?? [];
            $data['sectionBrands'] = Brand::whereIn('id', $brandIds)
                ->orderBy('name')
                ->get(['id', 'name', 'logo']);
        }

        return Inertia::render('admin/homepage/edit', $data);
    }

    public function update(HomepageSectionRequest $request, HomepageSetting $setting): RedirectResponse
    {
        $validated = $request->validated();

        $setting->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'eyebrow' => $validated['eyebrow'],
            'action_label' => $validated['action_label'],
            'action_href' => $validated['action_href'],
            'is_visible' => $validated['is_visible'],
            'metadata' => array_merge($setting->metadata ?? [], $validated['metadata'] ?? []),
        ]);

        if ($setting->section_key === 'featured_products') {
            $productIds = collect($validated['featured_products'] ?? [])
                ->pluck('id')
                ->filter()
                ->unique()
                ->toArray();

            // Sync: unfeature all, then feature the selected set
            Product::query()->update(['is_featured' => false]);

            if (! empty($productIds)) {
                Product::whereIn('id', $productIds)->update(['is_featured' => true]);
            }
        }

        if ($setting->section_key === 'new_arrivals' || $setting->section_key === 'best_sellers') {
            $productIds = collect($validated['section_products'] ?? [])
                ->pluck('id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $metadata = $setting->metadata ?? [];
            $metadata['product_ids'] = $productIds;
            $setting->update(['metadata' => $metadata]);
        }

        if ($setting->section_key === 'brands') {
            $brandIds = collect($validated['section_brands'] ?? [])
                ->pluck('id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $metadata = $setting->metadata ?? [];
            $metadata['brand_ids'] = $brandIds;
            $setting->update(['metadata' => $metadata]);

            // Sync: deactivate all, then activate the selected set
            Brand::query()->update(['is_active' => false]);
            if (! empty($brandIds)) {
                Brand::whereIn('id', $brandIds)->update(['is_active' => true]);
            }
        }

        return redirect()->route('admin.homepage.index')->with('success', 'Homepage section updated.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:homepage_settings,id'],
        ])['ids'];

        foreach ($ids as $index => $id) {
            HomepageSetting::whereKey($id)->update(['sort_order' => $index]);
        }

        return redirect()->route('admin.homepage.index');
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $products = Product::active()
            ->published()
            ->when($query, fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'price']);

        return response()->json($products);
    }

    public function searchBrands(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $brands = Brand::query()
            ->when($query, fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'logo']);

        return response()->json($brands);
    }
}
