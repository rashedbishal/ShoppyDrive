<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\StockAdjustmentException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Services\StockAdjustmentService;
use App\Support\AdminListUrl;
use App\Support\MotherPasswordGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin › Stock Management (owner decisions 2026-11-14): one row per product,
 * or per variant for products with variants, and password-confirmed manual
 * add/remove. The page re-reads its rows every few seconds (polling) so stock
 * changes made elsewhere show without a refresh.
 */
class StockController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response|RedirectResponse
    {
        $search = trim((string) $request->input('search', ''));

        $products = Product::query()
            ->with([
                'category:id,name',
                'subCategory:id,name',
                'images' => fn ($q) => $q->select(['id', 'product_id', 'path', 'is_primary', 'sort_order'])->orderBy('sort_order'),
                // Only the columns this page shows: never ProductVariant's
                // appended `image` accessor, which would lazy-load media (PERF-001).
                'variants' => fn ($q) => $q->select(['id', 'product_id', 'sku', 'price', 'stock_quantity', 'low_stock_threshold', 'attributes'])->orderBy('id'),
            ])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $match) => $this->applySearch($match, $search)))
            ->when($request->category_id, fn (Builder $q) => $q->where('category_id', $request->category_id))
            ->when($request->sub_category_id, fn (Builder $q) => $q->where('sub_category_id', $request->sub_category_id))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($products, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        $products->getCollection()->transform(fn (Product $product): array => $this->productRow($product));

        return Inertia::render('admin/stock/index', [
            'products' => $products,
            'filters' => $request->only(['search', 'category_id', 'sub_category_id']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::active()->orderBy('name')->get(['id', 'name', 'category_id']),
            'canAdjust' => (bool) $request->user('admin')?->canAccess('stock', 'update'),
        ]);
    }

    public function adjust(Request $request, StockAdjustmentService $stock): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'direction' => ['required', 'in:add,remove'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.StockAdjustmentService::MAX_STOCK],
            'reason' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $admin = $request->user('admin');

        if (MotherPasswordGate::isLocked($admin)) {
            return back()->withErrors(['password' => MotherPasswordGate::lockedMessage($admin)]);
        }

        if (! MotherPasswordGate::verify($admin, $validated['password'])) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $delta = $validated['direction'] === 'add' ? (int) $validated['quantity'] : -(int) $validated['quantity'];

        try {
            $result = $stock->adjust(
                $admin,
                (int) $validated['product_id'],
                isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
                $delta,
                $validated['reason'] ?? null,
            );
        } catch (StockAdjustmentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('success', "Stock updated: {$result['before']} → {$result['after']}.");
    }

    /**
     * Name or SKU (the product's or a variant's) contains the text; a number
     * also matches a product or variant price exactly.
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->where('name', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhereHas('variants', fn (Builder $variant) => $variant->where('sku', 'like', $like));

        if (is_numeric($search)) {
            $price = (float) $search;
            $query->orWhere('price', $price)
                ->orWhereHas('variants', fn (Builder $variant) => $variant->where('price', $price));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product): array
    {
        $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'is_active' => (bool) $product->is_active,
            'stock_quantity' => (int) $product->stock_quantity,
            'low_stock_threshold' => $product->low_stock_threshold,
            'category' => $product->category?->name,
            'sub_category' => $product->subCategory?->name,
            'image' => $primaryImage?->path,
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => (float) $variant->price,
                'stock_quantity' => (int) $variant->stock_quantity,
                'low_stock_threshold' => $variant->low_stock_threshold,
                'label' => is_array($variant->attributes) && $variant->attributes !== []
                    ? implode(' / ', array_map('strval', $variant->attributes))
                    : 'Variant #'.$variant->id,
            ])->values()->all(),
        ];
    }
}
