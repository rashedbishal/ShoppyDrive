<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsAdminActions;
use App\Concerns\UpdatesStatusInBulk;
use App\Enums\CouponAppliesTo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponProductTarget;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Support\AdminDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    use RecordsAdminActions, UpdatesStatusInBulk;

    private const TARGET_FIELDS = ['target_category_ids', 'target_sub_category_ids', 'target_products'];

    public function index(Request $request): Response
    {
        $coupons = Coupon::when($request->search, fn ($q) => $q->where('code', 'like', "%{$request->search}%")->orWhere('name', 'like', "%{$request->search}%"))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/coupons/index', [
            'coupons' => $coupons,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/create', $this->targetOptions());
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        $data = AdminDateTime::fieldsFromInput($request->validated(), ['starts_at', 'expires_at']);
        $data['is_active'] = $data['is_active'] ?? true;

        DB::transaction(function () use ($data): void {
            $coupon = Coupon::create(Arr::except($data, self::TARGET_FIELDS));
            $this->syncTargets($coupon, $data);
        });

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon): Response
    {
        $coupon->load(['categories:id', 'subCategories:id', 'productTargets.product:id,name', 'productTargets.variant:id,sku,attributes']);

        return Inertia::render('admin/coupons/edit', [
            ...$this->targetOptions(),
            'coupon' => [
                ...$coupon->withoutRelations()->toArray(),
                'starts_at_input' => AdminDateTime::toInput($coupon->starts_at),
                'expires_at_input' => AdminDateTime::toInput($coupon->expires_at),
                'target_category_ids' => $coupon->categories->pluck('id')->all(),
                'target_sub_category_ids' => $coupon->subCategories->pluck('id')->all(),
                'target_products' => $coupon->productTargets->map(fn (CouponProductTarget $target): array => [
                    'product_id' => $target->product_id,
                    'variant_id' => $target->variant_id,
                    'product_name' => $target->product?->name ?? '',
                    'variant_label' => $target->variant ? $this->variantLabel($target->variant) : null,
                ])->values()->all(),
            ],
        ]);
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $data = AdminDateTime::fieldsFromInput($request->validated(), ['starts_at', 'expires_at']);

        DB::transaction(function () use ($coupon, $data): void {
            $coupon->update(Arr::except($data, self::TARGET_FIELDS));
            $this->syncTargets($coupon, $data);
        });

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    /**
     * Same response shape as Admin\FlashSaleController::searchProducts().
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        $products = Product::with('variants')
            ->active()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'label' => $this->variantLabel($variant),
                ])->values(),
            ]);

        return response()->json($products);
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, Coupon::class);

        return redirect()->route('admin.coupons.index')->with('success', $result['count'].' coupon(s) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:coupons,id'],
        ])['ids'];

        Coupon::whereIn('id', $ids)->delete();

        return redirect()->route('admin.coupons.index')->with('success', count($ids).' coupon(s) deleted.');
    }

    /**
     * @return array{categories: mixed, subCategories: mixed}
     */
    private function targetOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::orderBy('name')->get(['id', 'name', 'category_id']),
        ];
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $attributes = collect($variant->attributes ?? [])
            ->map(fn (mixed $value, string $key): string => "{$key}: {$value}")
            ->implode(', ');

        return $attributes !== '' ? $attributes : ($variant->sku ?? "Variant #{$variant->id}");
    }

    /**
     * A coupon for all products keeps no targets. Runs inside the save's transaction.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncTargets(Coupon $coupon, array $data): void
    {
        if (! array_key_exists('applies_to', $data)) {
            return;
        }

        $forSelected = $data['applies_to'] === CouponAppliesTo::SELECTED->value;
        $categoryIds = $forSelected ? ($data['target_category_ids'] ?? []) : [];
        $subCategoryIds = $forSelected ? ($data['target_sub_category_ids'] ?? []) : [];
        $products = collect($forSelected ? ($data['target_products'] ?? []) : [])
            ->map(fn (array $target): array => [
                'product_id' => (int) $target['product_id'],
                'variant_id' => empty($target['variant_id']) ? null : (int) $target['variant_id'],
            ]);

        $summary = fn (): array => [
            $coupon->categories()->pluck('categories.id')->sort()->values()->all(),
            $coupon->subCategories()->pluck('sub_categories.id')->sort()->values()->all(),
            $coupon->productTargets()->get()->map(fn (CouponProductTarget $target): string => $target->product_id.':'.$target->variant_id)->sort()->values()->all(),
        ];
        $before = $summary();

        $coupon->categories()->sync($categoryIds);
        $coupon->subCategories()->sync($subCategoryIds);

        $coupon->productTargets()->delete();
        $products->each(fn (array $target) => $coupon->productTargets()->create($target));

        if ($summary() !== $before) {
            $this->recordAdminAction('targets_updated', "Coupon {$coupon->code} now covers {$products->count()} product row(s), ".count($categoryIds).' categories and '.count($subCategoryIds).' subcategories.', $coupon);
        }
    }
}
