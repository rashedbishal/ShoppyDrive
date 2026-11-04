<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FlashSaleRequest;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\AdminDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FlashSaleController extends Controller
{
    use UpdatesStatusInBulk;

    public function index(Request $request): Response
    {
        $flashSales = FlashSale::withCount('items')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/flash-sales/index', [
            'flashSales' => $flashSales,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/flash-sales/create');
    }

    /**
     * Same response shape as Admin\IncompleteOrderController::searchProducts()
     * — each product's variants (with their own stock) embedded directly —
     * so the create/edit form can offer "whole product" or a specific
     * variant combination when a product has any.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        $products = Product::with(['images', 'variants'])
            ->active()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'available_stock' => $product->available_stock,
                'image' => $product->primaryImagePath(),
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => (float) $variant->price,
                    'stock_quantity' => $variant->stock_quantity,
                    'attributes' => $variant->attributes,
                ])->values(),
            ]);

        return response()->json($products);
    }

    public function store(FlashSaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['is_active'] = $data['is_active'] ?? false;
        $data['starts_at'] = AdminDateTime::fromInput($data['starts_at']);
        $data['ends_at'] = AdminDateTime::fromInput($data['ends_at']);
        $flashSale = FlashSale::create($data);

        foreach ($items as $item) {
            FlashSaleItem::create([
                'flash_sale_id' => $flashSale->id,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'sale_price' => $item['sale_price'],
            ]);
        }

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale created.');
    }

    public function edit(FlashSale $flashSale): Response
    {
        $flashSale->load([
            // The stock shown for a whole-product item comes from the appended
            // available_stock, which needs variants loaded to give the live
            // total. The stock_quantity column alone goes stale for a variant
            // product.
            'items.product:id,name,price,sku,stock_quantity',
            'items.product.variants:id,product_id,stock_quantity',
            'items.variant:id,product_id,sku,price,stock_quantity,attributes',
        ]);

        return Inertia::render('admin/flash-sales/edit', [
            'flashSale' => [
                ...$flashSale->toArray(),
                'starts_at_input' => AdminDateTime::toInput($flashSale->starts_at),
                'ends_at_input' => AdminDateTime::toInput($flashSale->ends_at),
            ],
        ]);
    }

    public function update(FlashSaleRequest $request, FlashSale $flashSale): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? null;
        unset($data['items']);

        $data['is_active'] = $data['is_active'] ?? false;
        $data['starts_at'] = AdminDateTime::fromInput($data['starts_at']);
        $data['ends_at'] = AdminDateTime::fromInput($data['ends_at']);

        DB::transaction(function () use ($flashSale, $data, $items): void {
            $flashSale->update($data);

            if ($items !== null) {
                $this->syncItems($flashSale, $items);
            }
        });

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale updated.');
    }

    /**
     * Updates a row that stays in the sale rather than recreating it: its
     * sold_count is real sales history, and order items point at its id so a
     * cancellation or return can reduce that count later.
     *
     * @param  array<int, array{product_id: int|string, variant_id?: int|string|null, sale_price: int|float|string}>  $items
     */
    private function syncItems(FlashSale $flashSale, array $items): void
    {
        $rowKey = fn (int|string $productId, int|string|null $variantId): string => $productId.':'.($variantId ?? 'whole');
        $existing = $flashSale->items->keyBy(fn (FlashSaleItem $item) => $rowKey($item->product_id, $item->variant_id));
        $keptIds = [];

        foreach ($items as $item) {
            $saved = $existing->get($rowKey($item['product_id'], $item['variant_id'] ?? null));

            if ($saved !== null) {
                $saved->update(['sale_price' => $item['sale_price']]);
                $keptIds[] = $saved->id;

                continue;
            }

            $keptIds[] = $flashSale->items()->create([
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'sale_price' => $item['sale_price'],
            ])->id;
        }

        $flashSale->items()->whereNotIn('id', $keptIds)->delete();
    }

    public function destroy(FlashSale $flashSale): RedirectResponse
    {
        $flashSale->items()->delete();
        $flashSale->delete();

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, FlashSale::class);

        return redirect()->route('admin.flash-sales.index')->with('success', $result['count'].' flash sale(s) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:flash_sales,id'],
        ])['ids'];

        foreach (FlashSale::whereIn('id', $ids)->get() as $flashSale) {
            $flashSale->items()->delete();
            $flashSale->delete();
        }

        return redirect()->route('admin.flash-sales.index')->with('success', count($ids).' flash sale(s) deleted.');
    }
}
