<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    use UpdatesStatusInBulk;

    public function index(Request $request): Response
    {
        $brands = Brand::withCount('products')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('created_at')->paginate(20)->withQueryString();

        return Inertia::render('admin/brands/index', [
            'brands' => $brands,
            'filters' => $request->only(['search', 'status']),
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
            $data['logo'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('logo'), 'brands');
        }
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;
        $brand = Brand::create($data);

        $this->syncHomepageSetting($brand);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Brand created.']);

        return redirect()->route('admin.brands.index');
    }

    public function edit(Brand $brand): Response
    {
        return Inertia::render('admin/brands/edit', ['brand' => $brand]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('logo')) {
            $data['logo'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('logo'), 'brands');
        }
        $data['is_active'] = $data['is_active'] ?? false;
        $brand->update($data);

        $this->syncHomepageSetting($brand);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Brand updated.']);

        return redirect()->route('admin.brands.index');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $products = $brand->products()->get(['products.id', 'products.name', 'products.slug']);

        if ($products->isNotEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Can't delete \"{$brand->name}\" — it's used by {$products->count()} product(s). Remove it from those products first.",
                'products' => $products->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                ])->all(),
            ]);

            return redirect()->route('admin.brands.index');
        }

        $brand->is_active = false;
        $this->syncHomepageSetting($brand);
        $brand->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Brand deleted.']);

        return redirect()->route('admin.brands.index');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, Brand::class, 'is_active', fn (Brand $brand) => $this->syncHomepageSetting($brand));

        Inertia::flash('toast', ['type' => 'success', 'message' => $result['count'].' brand(s) '.($result['active'] ? 'activated' : 'deactivated').'.']);

        return redirect()->route('admin.brands.index');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:brands,id'],
        ])['ids'];

        $brands = Brand::withCount('products')->whereIn('id', $ids)->get();
        $blocked = $brands->filter(fn (Brand $brand): bool => $brand->products_count > 0);

        // All-or-nothing: partially deleting the selection would be a
        // confusing outcome for a bulk action, so any brand still in use
        // blocks the whole batch rather than silently skipping it.
        if ($blocked->isNotEmpty()) {
            $products = $blocked
                ->flatMap(fn (Brand $brand) => $brand->products()->get(['products.id', 'products.name', 'products.slug']))
                ->unique('id')
                ->values();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Can't delete — {$blocked->count()} of the selected brand(s) are used by {$products->count()} product(s). Remove it from those products first.",
                'products' => $products->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                ])->all(),
            ]);

            return redirect()->route('admin.brands.index');
        }

        foreach ($brands as $brand) {
            $brand->is_active = false;
            $this->syncHomepageSetting($brand);
            $brand->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => count($ids).' brand(s) deleted.']);

        return redirect()->route('admin.brands.index');
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
