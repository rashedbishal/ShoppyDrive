<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductBundleRequest;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Services\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductBundleController extends Controller
{
    use UpdatesStatusInBulk;

    public function index(Request $request): Response
    {
        $bundles = ProductBundle::withCount('items')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/product-bundles/index', [
            'bundles' => $bundles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/product-bundles/create');
    }

    public function store(ProductBundleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $data['slug'] = $this->uniqueSlug(Str::slug($data['name']));
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = (ProductBundle::max('sort_order') ?? -1) + 1;

        if (request()->hasFile('image')) {
            $data['image'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('image'), 'product-bundles');
        } else {
            $data['image'] = '/placeholder.svg';
        }

        $bundle = ProductBundle::create($data);

        $this->syncItems($bundle, $items);

        return redirect()->route('admin.product-bundles.index')->with('success', 'Bundle created.');
    }

    public function edit(ProductBundle $productBundle): Response
    {
        return Inertia::render('admin/product-bundles/edit', [
            'bundle' => $productBundle->load([
                'items.product:id,name,price,sku,has_color_variant,has_size_variant',
                'items.product.variants.colorValue',
                'items.product.variants.sizeValue',
            ]),
        ]);
    }

    public function update(ProductBundleRequest $request, ProductBundle $productBundle): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        if ($data['name'] !== $productBundle->name) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['name']), $productBundle->id);
        }

        $data['is_active'] = $data['is_active'] ?? false;

        if ($request->hasFile('image')) {
            $data['image'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('image'), 'product-bundles');
        } else {
            unset($data['image']);
        }

        $productBundle->update($data);

        $this->syncItems($productBundle, $items);

        return redirect()->route('admin.product-bundles.index')->with('success', 'Bundle updated.');
    }

    public function destroy(ProductBundle $productBundle): RedirectResponse
    {
        $productBundle->items()->delete();
        $productBundle->delete();

        return redirect()->route('admin.product-bundles.index')->with('success', 'Bundle deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, ProductBundle::class);

        return redirect()->route('admin.product-bundles.index')->with('success', $result['count'].' bundle(s) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:product_bundles,id'],
        ])['ids'];

        foreach (ProductBundle::whereIn('id', $ids)->get() as $bundle) {
            $bundle->items()->delete();
            $bundle->delete();
        }

        return redirect()->route('admin.product-bundles.index')->with('success', count($ids).' bundle(s) deleted.');
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        $products = Product::active()
            ->published()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->with(['variants.colorValue', 'variants.sizeValue'])
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'price', 'sku', 'has_color_variant', 'has_size_variant']);

        return response()->json($products);
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int}>  $items
     */
    private function syncItems(ProductBundle $bundle, array $items): void
    {
        $bundle->items()->delete();

        foreach ($items as $item) {
            ProductBundleItem::create([
                'product_bundle_id' => $bundle->id,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'quantity' => $item['quantity'],
            ]);
        }
    }

    private function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $candidate = $slug;
        $suffix = 1;

        while (
            ProductBundle::where('slug', $candidate)
                ->when($excludeId !== null, fn ($q) => $q->whereKeyNot($excludeId))
                ->exists()
        ) {
            $suffix++;
            $candidate = "{$slug}-{$suffix}";
        }

        return $candidate;
    }
}
