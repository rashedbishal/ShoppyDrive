<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\StaleProductStockException;
use App\Exceptions\StockAdjustmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Services\ImageOptimizer;
use App\Services\StockAdjustmentService;
use App\Support\AdminListUrl;
use App\Support\CatalogFilterCache;
use App\Support\MotherPasswordGate;
use App\Support\ProductStockToken;
use App\Support\SearchSuggestionCache;
use App\Support\UnusedUploadRemover;
use App\Support\VariantAttributeLabels;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $products = Product::with([
            'category:id,name',
            'subCategory:id,name',
            'brand:id,name',
            'images' => fn ($q) => $q->select(['id', 'product_id', 'path', 'is_primary', 'sort_order'])->orderBy('sort_order'),
        ])
            // Orders and cancellations change a variant product's stock on its
            // variants, not on `products.stock_quantity` (that column is only
            // re-summed when the product is saved), so the list totals the
            // variants itself, in the query.
            ->withCount('variants')
            ->withSum('variants', 'stock_quantity')
            ->when($request->search, fn ($q) => $q->where(fn ($match) => $match
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('sku', 'like', "%{$request->search}%")))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->sub_category_id, fn ($q) => $q->where('sub_category_id', $request->sub_category_id))
            // `id` breaks ties between products created in the same second (imports,
            // seeders): without it the database may order them differently after any
            // row is updated, so an edited product jumped around the list.
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($products, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        $products->getCollection()->transform(function (Product $product) {
            $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

            return array_merge(Arr::except($product->toArray(), 'images'), [
                'primary_image_url' => $primaryImage?->path,
                // Same rule as Product::available_stock: the variants' total
                // when the product has variants, otherwise its own stock.
                'available_stock' => $product->variants_count > 0
                    ? (int) $product->variants_sum_stock_quantity
                    : (int) $product->stock_quantity,
            ]);
        });

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => $request->only(['search', 'status', 'category_id', 'sub_category_id']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::active()->orderBy('name')->get(['id', 'name']),
            'cryBabyEnabledCount' => Product::where('cry_baby_enabled', true)->count(),
        ]);
    }

    /**
     * Turn the product-page "cry baby" easter egg on or off for every product at once.
     */
    public function updateCryBabyForAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $isEnabled = (bool) $validated['enabled'];

        Product::query()->update(['cry_baby_enabled' => $isEnabled]);

        return back()->with('success', $isEnabled
            ? 'Cry baby easter egg turned on for all products.'
            : 'Cry baby easter egg turned off for all products.');
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/create', [
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::active()->orderBy('name')->get(['id', 'name', 'category_id']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'variantAttributes' => $this->variantAttributesForForm(),
        ]);
    }

    public function store(ProductRequest $request, StockAdjustmentService $stock): RedirectResponse
    {
        // Owner decision 2026-11-14: a new product's starting stock needs the
        // admin's password and is recorded, like a change on the edit form.
        $admin = $request->user('admin');
        $passwordVerified = $this->confirmStockPassword($request, $admin);

        if ($passwordVerified instanceof RedirectResponse) {
            return $passwordVerified;
        }

        $stockReason = $this->stockReason($request);

        $data = $request->validated();
        $images = $data['images'] ?? [];
        $mediaTags = $data['media_tags'] ?? [];
        $variants = $data['variants'] ?? [];
        $primaryIndex = isset($data['primary_image_index']) ? (int) $data['primary_image_index'] : null;
        $hoverIndex = isset($data['hover_image_index']) ? (int) $data['hover_image_index'] : null;
        unset($data['images'], $data['media_tags'], $data['variants'], $data['primary_image_index'], $data['hover_image_index']);

        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_featured'] = $data['is_featured'] ?? false;
        $data = array_merge($data, $this->variantAttributeFields($data['variant_attribute_ids'] ?? []));
        $data['cry_baby_enabled'] = $data['cry_baby_enabled'] ?? false;

        if ($data['variant_attribute_ids'] !== []) {
            $data = array_merge($data, $this->computeDenormalizedFields($variants));
        }

        if ($data['is_active'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        try {
            $this->saveAtomically($images, function (array $media) use ($data, $variants, $mediaTags, $primaryIndex, $hoverIndex, $stock, $admin, $passwordVerified, $stockReason): void {
                $product = Product::create($data);

                $variantsByKey = $this->syncVariants($product, $variants);

                $nothingYet = ['product' => 0, 'has_variants' => false, 'variants' => []];
                $this->recordStockChanges($stock, $admin, $product, $nothingYet, $this->stockSnapshot($product), $passwordVerified, $stockReason, StockAdjustmentService::SOURCE_PRODUCT_CREATE);

                $this->syncMedia($product, $media, $mediaTags, $variantsByKey, $primaryIndex, $hoverIndex);
            });
        } catch (StockAdjustmentException $e) {
            return back()->withErrors(['stock_password' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException $e) {
            return $this->duplicateSkuResponse($e);
        }

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        // FSA-04: cost_price is hidden on both models for storefront
        // responses; the edit form is the one screen that needs it.
        // PERF-001: variants.media — ProductVariant's appended `image`
        // accessor otherwise lazy-loads it per variant on serialization.
        $product->load(['media', 'variants.media', 'category:id,name', 'subCategory:id,name', 'brand:id,name'])
            ->makeVisible('cost_price');
        $product->variants->each->makeVisible('cost_price');

        return Inertia::render('admin/products/edit', [
            'product' => $product,
            'stockToken' => ProductStockToken::for($product),
            // Owner decisions 2026-11-14: polled by the form (see stockState()).
            'stockStateUrl' => route('admin.products.stock-state', $product),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::active()->orderBy('name')->get(['id', 'name', 'category_id']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'variantAttributes' => $this->variantAttributesForForm(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, StockAdjustmentService $stock): RedirectResponse
    {
        // Owner decisions 2026-11-14: changing stock here needs the admin's
        // password, like Stock Management. Checked before the transaction so
        // a wrong password's lockout hit isn't rolled back with it.
        $admin = $request->user('admin');
        $passwordVerified = $this->confirmStockPassword($request, $admin);

        if ($passwordVerified instanceof RedirectResponse) {
            return $passwordVerified;
        }

        $stockReason = $this->stockReason($request);

        $data = $request->validated();
        $images = $data['images'] ?? null;
        $mediaTags = $data['media_tags'] ?? [];
        $variants = $data['variants'] ?? [];
        $stockToken = $data['stock_token'];
        $primaryIndex = isset($data['primary_image_index']) ? (int) $data['primary_image_index'] : null;
        $hoverIndex = isset($data['hover_image_index']) ? (int) $data['hover_image_index'] : null;
        unset($data['images'], $data['media_tags'], $data['variants'], $data['stock_token'], $data['primary_image_index'], $data['hover_image_index']);

        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_featured'] = $data['is_featured'] ?? false;
        $data['free_delivery'] = $data['free_delivery'] ?? false;
        $data = array_merge($data, $this->variantAttributeFields($data['variant_attribute_ids'] ?? []));
        $data['cry_baby_enabled'] = $data['cry_baby_enabled'] ?? false;

        if ($data['variant_attribute_ids'] !== []) {
            $data = array_merge($data, $this->computeDenormalizedFields($variants));
        }

        if ($data['is_active'] && $product->published_at === null) {
            $data['published_at'] = now();
        }

        // MEDIA-VIDEO-TYPE-001: a retained (not re-uploaded) media item arrives
        // as a bare path string with no type info — looked up here, by path,
        // against what's currently stored, so storeUploads() can keep a kept
        // video's real type instead of defaulting it to 'image'.
        $existingMediaTypes = $product->media()->pluck('type', 'path')->all();

        try {
            $this->saveAtomically($images, function (?array $media) use ($product, $data, $variants, $mediaTags, $primaryIndex, $hoverIndex, $stockToken, $stock, $admin, $passwordVerified, $stockReason): void {
                ProductStockToken::lockAndVerify($product, $stockToken);
                $stockBefore = $this->stockSnapshot($product);

                $product->update($data);

                $variantsByKey = $this->syncVariants($product, $variants);

                $this->recordStockChanges($stock, $admin, $product, $stockBefore, $this->stockSnapshot($product), $passwordVerified, $stockReason, StockAdjustmentService::SOURCE_PRODUCT_EDIT);

                if ($media !== null) {
                    $this->syncMedia($product, $media, $mediaTags, $variantsByKey, $primaryIndex, $hoverIndex);
                }
            }, $existingMediaTypes);
        } catch (StaleProductStockException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withErrors(['stock_token' => $e->getMessage()]);
        } catch (StockAdjustmentException $e) {
            return back()->withErrors(['stock_password' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException $e) {
            return $this->duplicateSkuResponse($e);
        }

        return AdminListUrl::redirect('admin.products.index')->with('success', 'Product updated.');
    }

    /**
     * Owner decisions 2026-11-14: what the edit form polls every few seconds,
     * so stock changed elsewhere (Stock Management, orders, cancellations)
     * shows without a refresh. Read-only; carries a fresh stock token so the
     * form can keep saving when only untouched fields changed.
     */
    public function stockState(Product $product): JsonResponse
    {
        return response()->json($this->liveStock($product));
    }

    /**
     * @return array{token: string, stock_quantity: int, variants: list<array{key: string, stock_quantity: int}>}
     */
    private function liveStock(Product $product): array
    {
        return [
            'token' => ProductStockToken::for($product),
            'stock_quantity' => (int) Product::query()->whereKey($product->id)->value('stock_quantity'),
            'variants' => ProductVariant::query()
                ->where('product_id', $product->id)
                ->orderBy('id')
                ->get(['id', 'stock_quantity', 'attribute_value_ids', 'color_value_id', 'size_value_id'])
                ->map(fn (ProductVariant $variant): array => [
                    'key' => $this->stockKey($variant->valueIds()),
                    'stock_quantity' => (int) $variant->stock_quantity,
                ])
                ->values()
                ->all(),
        ];
    }

    /** The same key the edit form builds from a variant row's value ids. */
    private function stockKey(array $valueIds): string
    {
        $ids = array_map('intval', $valueIds);
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * Checks the admin's password when the form sent one (owner decisions
     * 2026-11-14), before the save's transaction so a wrong password's lockout
     * hit isn't rolled back with it. Returns whether it was confirmed, or the
     * error response. Whether one was actually needed is decided once the
     * save knows what stock changed (recordStockChanges()).
     */
    private function confirmStockPassword(Request $request, Admin $admin): bool|RedirectResponse
    {
        $password = (string) $request->input('stock_password', '');

        if ($password === '') {
            return false;
        }

        if (MotherPasswordGate::isLocked($admin)) {
            return back()->withErrors(['stock_password' => MotherPasswordGate::lockedMessage($admin)]);
        }

        if (! MotherPasswordGate::verify($admin, $password)) {
            return back()->withErrors(['stock_password' => 'Incorrect password.']);
        }

        return true;
    }

    private function stockReason(Request $request): ?string
    {
        $reason = $request->input('stock_reason');

        return is_string($reason) ? mb_substr($reason, 0, 255) : null;
    }

    /**
     * @return array{product: int, has_variants: bool, variants: array<int, array{stock: int, sku: string|null, threshold: int|null, attributes: array<string, string>|null}>}
     */
    private function stockSnapshot(Product $product): array
    {
        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->get(['id', 'stock_quantity', 'sku', 'low_stock_threshold', 'attributes'])
            ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => [
                'stock' => (int) $variant->stock_quantity,
                'sku' => $variant->sku,
                'threshold' => $variant->low_stock_threshold,
                'attributes' => is_array($variant->attributes) ? $variant->attributes : null,
            ]])
            ->all();

        return [
            'product' => (int) Product::query()->whereKey($product->id)->value('stock_quantity'),
            'has_variants' => $variants !== [],
            'variants' => $variants,
        ];
    }

    /**
     * Records every stock change the edit form made, the same way Stock
     * Management does, after refusing the save if the admin's password wasn't
     * confirmed (owner decisions 2026-11-14). Covered:
     *  - the product's own stock, when it has no variants;
     *  - each existing variant whose stock changed;
     *  - a new variant's starting stock (0 → n);
     *  - a removed variant's stock (n → 0);
     *  - switching between "no variants" and "with variants": the product's
     *    own stock counts as removed (n → 0) or set (0 → n), and the variants
     *    as added or removed.
     * For a product with variants, the product's own column is just their
     * total, so it is never recorded separately.
     *
     * @param  array{product: int, has_variants: bool, variants: array<int, array{stock: int, sku: string|null, threshold: int|null, attributes: array<string, string>|null}>}  $before
     * @param  array{product: int, has_variants: bool, variants: array<int, array{stock: int, sku: string|null, threshold: int|null, attributes: array<string, string>|null}>}  $after
     *
     * @throws StockAdjustmentException
     */
    private function recordStockChanges(StockAdjustmentService $stock, Admin $admin, Product $product, array $before, array $after, bool $passwordVerified, ?string $reason, string $source): void
    {
        /** @var list<array{0: int|null, 1: int, 2: int, 3: array{stock: int, sku: string|null, threshold: int|null, attributes: array<string, string>|null}|null}> $changes */
        $changes = [];

        $productStockBefore = $before['has_variants'] ? 0 : $before['product'];
        $productStockAfter = $after['has_variants'] ? 0 : $after['product'];

        if ($productStockBefore !== $productStockAfter) {
            $changes[] = [null, $productStockBefore, $productStockAfter, null];
        }

        foreach ($before['variants'] + $after['variants'] as $variantId => $row) {
            $from = $before['variants'][$variantId]['stock'] ?? 0;
            $to = $after['variants'][$variantId]['stock'] ?? 0;

            if ($from !== $to) {
                $changes[] = [$variantId, $from, $to, $before['variants'][$variantId] ?? $after['variants'][$variantId]];
            }
        }

        if ($changes === []) {
            return;
        }

        if (! $passwordVerified) {
            throw StockAdjustmentException::passwordRequired();
        }

        $existing = ProductVariant::query()->whereKey(array_filter(array_column($changes, 0)))->get()->keyBy('id');

        foreach ($changes as [$variantId, $from, $to, $row]) {
            $variant = null;

            if ($variantId !== null) {
                // A removed variant is gone from the database, so it is
                // described from the snapshot taken before the save.
                $variant = $existing->get($variantId) ?? (new ProductVariant)->forceFill([
                    'id' => $variantId,
                    'product_id' => $product->id,
                    'sku' => $row['sku'],
                    'low_stock_threshold' => $row['threshold'],
                    'attributes' => $row['attributes'],
                ]);
            }

            $stock->record($admin, $product, $variant, $from, $to, $reason, $source);
        }
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Blocks deletion while any order (including a trashed/cancelled one still
        // awaiting a permanent delete) has a line item for this exact product —
        // scoped by product_id, so an order that also contains other products
        // doesn't falsely block or get missed. Admin has to permanently delete
        // those orders first (Admin > Orders > trash > force delete).
        if (OrderItem::where('product_id', $product->id)->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This product has orders and cannot be deleted. Delete all orders containing this product first.',
            ]);

            return back();
        }

        // No order history left to protect, so this is a real, permanent
        // delete rather than a soft-delete — Product::booted()'s deleting
        // hook re-checks the same order-history condition as a backstop.
        $product->forceDelete();

        return AdminListUrl::redirect('admin.products.index')->with('success', 'Product permanently deleted.');
    }

    /**
     * MED-06: the database has the last word on SKUs. This covers what
     * validation can't see: a simultaneous save, a variant kept for order
     * history, or values only the column's collation treats as equal.
     */
    private function duplicateSkuResponse(UniqueConstraintViolationException $e): RedirectResponse
    {
        if (! str_contains($e->getMessage(), 'sku')) {
            throw $e;
        }

        return back()->withErrors(['variants' => 'One of these SKUs is already in use by another variant. Choose a different SKU.'])->withInput();
    }

    /**
     * MED-07: a product, its variants, attribute values and media commit
     * together. Files can't roll back, so uploads are stored first, holding no
     * database locks, and removed again if anything fails. Files of media the
     * save replaces are removed only after it commits, and only when nothing
     * still uses them — order lines keep their path in
     * `product_snapshot.image`, so an ordered product's old photo stays
     * (see syncMedia()).
     *
     * Not retried on deadlock: a retry would reuse a model that already
     * believes the rolled-back attributes are saved, and skip writing them.
     *
     * The model events flush these caches before the commit, when a reader
     * could refill them with old data, so they're flushed again after it.
     *
     * @param  array<int, string|UploadedFile|null>|null  $images
     * @param  Closure(array<int, array{path: string|null, type: string}>|null): void  $save
     * @param  array<string, string>  $existingMediaTypes  Current product_media type keyed by path — see storeUploads().
     */
    private function saveAtomically(?array $images, Closure $save, array $existingMediaTypes = []): void
    {
        $storedFiles = [];

        try {
            $media = $images === null ? null : $this->storeUploads($images, $storedFiles, $existingMediaTypes);

            DB::transaction(fn () => $save($media));
        } catch (Throwable $e) {
            Storage::disk('public')->delete($storedFiles);

            throw $e;
        }

        CatalogFilterCache::flush();
        SearchSuggestionCache::flush();
    }

    /**
     * @param  array<int, string|UploadedFile|null>  $images
     * @param  array<int, string>  $storedFiles  Each stored path, added as it's written, so a later failure can remove it.
     * @param  array<string, string>  $existingMediaTypes  Current product_media type keyed by path. A retained path
     *                                                     (not re-uploaded) has no type info of its own — this is
     *                                                     how it keeps its real type instead of defaulting to
     *                                                     'image' (MEDIA-VIDEO-TYPE-001). Fresh uploads never
     *                                                     consult this; their type is always detected from the
     *                                                     file's own MIME type below.
     * @return array<int, array{path: string|null, type: string}>
     */
    private function storeUploads(array $images, array &$storedFiles, array $existingMediaTypes = []): array
    {
        return array_map(function (string|UploadedFile|null $fileOrUrl) use (&$storedFiles, $existingMediaTypes): array {
            if (! $fileOrUrl instanceof UploadedFile) {
                $type = $fileOrUrl !== null ? ($existingMediaTypes[$fileOrUrl] ?? 'image') : 'image';

                return ['path' => $fileOrUrl, 'type' => $type];
            }

            $type = str_starts_with((string) $fileOrUrl->getMimeType(), 'video/') ? 'video' : 'image';
            $storedPath = app(ImageOptimizer::class)->store($fileOrUrl, 'products');
            $storedFiles[] = $storedPath;

            return ['path' => '/storage/'.$storedPath, 'type' => $type];
        }, $images);
    }

    /**
     * Every attribute a product can build variants from, with its values,
     * for the product form's variant editor.
     *
     * @return array<int, array{id: int, name: string, type: string, has_images: bool, values: array<int, array{id: int, value: string, color_hex: string|null}>}>
     */
    private function variantAttributesForForm(): array
    {
        return Attribute::query()
            ->with(['values' => fn ($query) => $query->orderBy('sort_order')->orderBy('value')])
            ->orderBy('name')
            ->get()
            ->map(fn (Attribute $attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'type' => $attribute->type->value,
                'has_images' => $attribute->has_images,
                'values' => $attribute->values->map(fn (AttributeValue $value): array => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'color_hex' => $value->color_hex,
                ])->all(),
            ])
            ->all();
    }

    /**
     * The product's variant attributes, plus the older Color/Size flags kept
     * current for code (and rollbacks) that still read them.
     *
     * @param  array<int, int|string>  $attributeIds
     * @return array{variant_attribute_ids: array<int, int>, has_color_variant: bool, has_size_variant: bool}
     */
    private function variantAttributeFields(array $attributeIds): array
    {
        $attributeIds = array_values(array_map('intval', $attributeIds));
        $names = Attribute::query()->whereKey($attributeIds)->pluck('name')->all();

        return [
            'variant_attribute_ids' => $attributeIds,
            'has_color_variant' => in_array('Color', $names, true),
            'has_size_variant' => in_array('Size', $names, true),
        ];
    }

    /**
     * Denormalized product-level price/stock fields, derived from the submitted
     * variants so the rest of the app (catalog sort/filter, flash sales, cards)
     * keeps reading flat product fields unaware variants exist.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<string, mixed>
     */
    private function computeDenormalizedFields(array $variants): array
    {
        if ($variants === []) {
            return [];
        }

        $default = null;
        foreach ($variants as $variant) {
            if (filter_var($variant['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $default = $variant;
                break;
            }
        }
        $default ??= $variants[0];

        return [
            'price' => min(array_column($variants, 'price')),
            'compare_price' => $default['compare_price'] ?? null,
            'cost_price' => $default['cost_price'] ?? null,
            'stock_quantity' => array_sum(array_column($variants, 'stock_quantity')),
            'low_stock_threshold' => $default['low_stock_threshold'] ?? 5,
        ];
    }

    /**
     * Reconciles ProductVariant rows by their set of attribute values (e.g.
     * Red + 12pcs) — never a wholesale delete-and-recreate, since
     * cart_items/order_items reference product_variants and a live cart or
     * historical order could reference a removed combination. Combinations no
     * longer submitted are hard-deleted only when nothing references them;
     * otherwise the row is left frozen and simply excluded from the
     * attribute-values pivot below, so it becomes unreachable from the
     * storefront without touching cart/order history.
     *
     * Each variant stores its values in the product's attribute order
     * (`attribute_value_ids`) and its labels ({"Color": "Red", "Set": "12pcs"}).
     * The older `color_value_id`/`size_value_id` (kept current for a
     * rollback) are written only for a product built from Color and/or Size
     * alone: they are unique per product, so Blue/M/6pcs and Blue/M/12pcs
     * would collide there, and nothing reads them for other products.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<string, ProductVariant> keyed by variantKey()
     */
    private function syncVariants(Product $product, array $variants): array
    {
        $attributeIds = array_map('intval', $product->variant_attribute_ids ?? []);
        $values = AttributeValue::with('attribute')
            ->whereKey(collect($variants)->flatMap(fn (array $variant) => $variant['value_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->all())
            ->get()
            ->keyBy('id');
        $existingByKey = $this->prepareVariantRows($product, $variants);
        $variantsByKey = [];
        $colorAndSizeOnly = Attribute::query()->whereKey($attributeIds)->pluck('name')
            ->every(fn (string $name): bool => in_array($name, ['Color', 'Size'], true));

        foreach ($variants as $variant) {
            $valueIds = $this->orderedValueIds($variant['value_ids'] ?? [], $attributeIds, $values);
            $key = $this->variantKey($valueIds);
            $legacyValue = fn (string $attributeName): ?int => $colorAndSizeOnly
                ? collect($valueIds)->first(fn (int $valueId): bool => $values->get($valueId)?->attribute?->name === $attributeName)
                : null;

            $fields = [
                // Normalizes a blank/whitespace-only SKU to null, matching
                // ProductRequest's own validation-time normalization —
                // otherwise a literal '' saved here would (a) collide with
                // another blank SKU under the unique index, where multiple
                // NULLs don't, and (b) break every `$variant?->sku ?? $product->sku`
                // fallback elsewhere in the app, since `??` only falls back
                // on null, not on an empty string.
                'sku' => ($sku = trim((string) ($variant['sku'] ?? ''))) !== '' ? $sku : null,
                'price' => $variant['price'],
                'compare_price' => $variant['compare_price'] ?? null,
                'cost_price' => $variant['cost_price'] ?? null,
                'stock_quantity' => $variant['stock_quantity'],
                'low_stock_threshold' => $variant['low_stock_threshold'] ?? 5,
                'attributes' => VariantAttributeLabels::for($valueIds, $values),
                'attribute_value_ids' => $valueIds,
                'color_value_id' => $legacyValue('Color'),
                'size_value_id' => $legacyValue('Size'),
                'is_default' => filter_var($variant['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            $existing = $existingByKey[$key] ?? null;

            if ($existing !== null) {
                $existing->update($fields);
                $variantsByKey[$key] = $existing;
            } else {
                $variantsByKey[$key] = ProductVariant::create(['product_id' => $product->id, ...$fields]);
            }
        }

        $product->attributeValues()->sync(
            collect($variantsByKey)->flatMap(fn (ProductVariant $variant) => $variant->valueIds())->unique()->values()->all(),
        );

        return $variantsByKey;
    }

    /**
     * Rows are written one at a time, but SKUs are unique at every step. So
     * first remove what the save retires and clear each SKU about to change,
     * letting two variants swap SKUs or a new variant take over a retired
     * one's. A retired variant still referenced keeps its SKU.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<string, ProductVariant> the existing rows the submission keeps, keyed by variantKey()
     */
    private function prepareVariantRows(Product $product, array $variants): array
    {
        $submittedSkus = [];

        foreach ($variants as $variant) {
            $sku = trim((string) ($variant['sku'] ?? ''));

            $submittedSkus[$this->variantKey(array_map('intval', $variant['value_ids'] ?? []))] = $sku !== '' ? $sku : null;
        }

        $kept = [];

        foreach ($product->variants()->orderBy('id')->get() as $existing) {
            $key = $this->variantKey($existing->valueIds());

            if (array_key_exists($key, $submittedSkus) && ! isset($kept[$key])) {
                if ($existing->sku !== $submittedSkus[$key]) {
                    $existing->update(['sku' => null]);
                }

                $kept[$key] = $existing;

                continue;
            }

            $isReferenced = CartItem::where('variant_id', $existing->id)->exists()
                || OrderItem::where('variant_id', $existing->id)->exists();

            if (! $isReferenced) {
                $existing->delete();
            }
        }

        return $kept;
    }

    /**
     * The submitted value ids in the product's attribute order (Color before
     * Set if the product lists them that way).
     *
     * @param  array<int, int|string>  $valueIds
     * @param  array<int, int>  $attributeIds
     * @param  Collection<int, AttributeValue>  $values
     * @return array<int, int>
     */
    private function orderedValueIds(array $valueIds, array $attributeIds, $values): array
    {
        $position = array_flip($attributeIds);
        $valueIds = array_map('intval', $valueIds);

        usort($valueIds, fn (int $a, int $b): int => ($position[(int) $values->get($a)?->attribute_id] ?? PHP_INT_MAX) <=> ($position[(int) $values->get($b)?->attribute_id] ?? PHP_INT_MAX));

        return $valueIds;
    }

    /**
     * The same combination always gives the same key, whatever order its
     * values arrive in.
     *
     * @param  array<int, int>  $valueIds
     */
    private function variantKey(array $valueIds): string
    {
        sort($valueIds);

        return $valueIds === [] ? 'none' : implode('-', $valueIds);
    }

    /**
     * @param  array<int, array{path: string|null, type: string}>  $media
     * @param  array<int, string|null>  $mediaTags
     * @param  array<string, ProductVariant>  $variantsByKey
     */
    private function syncMedia(Product $product, array $media, array $mediaTags, array $variantsByKey, ?int $primaryIndex = null, ?int $hoverIndex = null): void
    {
        // Files this save drops (replaced or removed) leave the disk once it
        // commits — unless an old order, a checkout in progress or another
        // product still shows them (UnusedUploadRemover). A save that rolls
        // back deletes nothing.
        $droppedPaths = array_diff(
            $product->media()->pluck('path')->all(),
            array_filter(array_column($media, 'path')),
        );
        app(UnusedUploadRemover::class)->removeIfUnused(...array_values($droppedPaths));

        $product->media()->delete();

        if (empty($media)) {
            ProductMedia::create([
                'product_id' => $product->id,
                'path' => '/placeholder.svg',
                'type' => 'image',
                'alt' => $product->name,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            return;
        }

        // An index that doesn't point at a real item falls back rather than
        // leaving the product with no primary image. A card swaps to an image,
        // so the hover pick can't be a video, or the image already showing.
        $primaryIndex = ($media[$primaryIndex]['path'] ?? null) ? $primaryIndex : array_key_first(array_filter($media, fn (array $item) => (bool) $item['path']));
        $isHoverPick = fn (int $i, array $item): bool => $i === $hoverIndex && $i !== $primaryIndex && $item['type'] === 'image';

        foreach ($media as $i => $item) {
            if (! $item['path']) {
                continue;
            }

            ProductMedia::create([
                'product_id' => $product->id,
                ...$this->resolveMediaTag($mediaTags[$i] ?? null, $variantsByKey),
                'path' => $item['path'],
                'type' => $item['type'],
                'is_primary' => $i === $primaryIndex,
                'is_hover' => $isHoverPick($i, $item),
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * Images are chosen per attribute value, not per variant: `value:{id}`
     * (e.g. Red, or any value of an attribute that allows images) stores the
     * value, so the image belongs to every variant with it (see
     * ProductVariant::getImageAttribute()). Forms opened before this change
     * send `color:{id}`, `size:{id}` or `pair:{colour}-{size}`; those count as
     * that colour, size, or the pair's colour. On a product with a single
     * variant attribute the image also links that value's variant, as before.
     * A value no variant of this product has is ignored.
     *
     * @param  array<string, ProductVariant>  $variantsByKey
     * @return array{variant_id: int|null, attribute_value_id: int|null}
     */
    private function resolveMediaTag(?string $tag, array $variantsByKey): array
    {
        $none = ['variant_id' => null, 'attribute_value_id' => null];

        $valueId = match (true) {
            ! $tag => null,
            str_starts_with($tag, 'value:') => (int) substr($tag, 6),
            str_starts_with($tag, 'color:') => (int) substr($tag, 6),
            str_starts_with($tag, 'size:') => (int) substr($tag, 5),
            str_starts_with($tag, 'pair:') => (int) explode('-', substr($tag, 5))[0],
            default => null,
        };

        $usedByAVariant = $valueId !== null && collect($variantsByKey)
            ->contains(fn (ProductVariant $variant): bool => in_array($valueId, $variant->valueIds(), true));

        if (! $usedByAVariant) {
            return $none;
        }

        return [
            'variant_id' => $variantsByKey[$this->variantKey([$valueId])]->id ?? null,
            'attribute_value_id' => $valueId,
        ];
    }
}
