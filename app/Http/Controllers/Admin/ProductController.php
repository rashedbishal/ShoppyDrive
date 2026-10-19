<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::with(['category:id,name', 'subCategory:id,name', 'brand:id,name'])
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('sku', 'like', "%{$request->search}%"))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->sub_category_id, fn ($q) => $q->where('sub_category_id', $request->sub_category_id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => $request->only(['search', 'status', 'category_id', 'sub_category_id']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/create', [
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::active()->orderBy('name')->get(['id', 'name', 'category_id']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'attributeValues' => $this->attributeValuesForForm(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $images = $data['images'] ?? [];
        $mediaTags = $data['media_tags'] ?? [];
        $variants = $data['variants'] ?? [];
        unset($data['images'], $data['media_tags'], $data['variants']);

        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_featured'] = $data['is_featured'] ?? false;
        $data['has_color_variant'] = $data['has_color_variant'] ?? false;
        $data['has_size_variant'] = $data['has_size_variant'] ?? false;

        if ($data['has_color_variant'] || $data['has_size_variant']) {
            $data = array_merge($data, $this->computeDenormalizedFields($variants));
        }

        if ($data['is_active'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $product = Product::create($data);

        $variantsByKey = $this->syncVariants($product, $variants);

        $this->syncMedia($product, $images, $mediaTags, $variantsByKey);

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/edit', [
            'product' => $product->load(['media', 'variants', 'category:id,name', 'subCategory:id,name', 'brand:id,name']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::active()->orderBy('name')->get(['id', 'name', 'category_id']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'attributeValues' => $this->attributeValuesForForm(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $images = $data['images'] ?? null;
        $mediaTags = $data['media_tags'] ?? [];
        $variants = $data['variants'] ?? [];
        unset($data['images'], $data['media_tags'], $data['variants']);

        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_featured'] = $data['is_featured'] ?? false;
        $data['free_delivery'] = $data['free_delivery'] ?? false;
        $data['has_color_variant'] = $data['has_color_variant'] ?? false;
        $data['has_size_variant'] = $data['has_size_variant'] ?? false;

        if ($data['has_color_variant'] || $data['has_size_variant']) {
            $data = array_merge($data, $this->computeDenormalizedFields($variants));
        }

        if ($data['is_active'] && $product->published_at === null) {
            $data['published_at'] = now();
        }

        $product->update($data);

        $variantsByKey = $this->syncVariants($product, $variants);

        if ($images !== null) {
            $this->syncMedia($product, $images, $mediaTags, $variantsByKey);
        }

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributeValuesForForm(): array
    {
        $values = AttributeValue::with('attribute')->orderBy('sort_order')->orderBy('value')->get();

        return [
            'colors' => $values->filter(fn (AttributeValue $value) => $value->attribute->name === 'Color')->values(),
            'sizes' => $values->filter(fn (AttributeValue $value) => $value->attribute->name === 'Size')->values(),
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
     * Reconciles ProductVariant rows by (color_value_id, size_value_id) — never a
     * wholesale delete-and-recreate, since cart_items/order_items cascade-delete
     * on product_variants and a live cart or historical order could reference a
     * removed combo. Combos no longer submitted are hard-deleted only when
     * nothing references them; otherwise the row is left frozen and simply
     * excluded from the attribute-values pivot below, so it becomes unreachable
     * from the storefront without touching cart/order history.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<string, ProductVariant> keyed by "{colorId|none}-{sizeId|none}"
     */
    private function syncVariants(Product $product, array $variants): array
    {
        $submittedKeys = [];
        $variantsByKey = [];

        foreach ($variants as $variant) {
            $colorId = $variant['color_value_id'] ?? null;
            $sizeId = $variant['size_value_id'] ?? null;
            $key = $this->variantKey($colorId, $sizeId);
            $submittedKeys[] = $key;

            $attributes = [];
            if ($colorId) {
                $attributes['Color'] = AttributeValue::find($colorId)?->value;
            }
            if ($sizeId) {
                $attributes['Size'] = AttributeValue::find($sizeId)?->value;
            }

            $variantsByKey[$key] = ProductVariant::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'color_value_id' => $colorId,
                    'size_value_id' => $sizeId,
                ],
                [
                    'sku' => $variant['sku'] ?? null,
                    'price' => $variant['price'],
                    'compare_price' => $variant['compare_price'] ?? null,
                    'cost_price' => $variant['cost_price'] ?? null,
                    'stock_quantity' => $variant['stock_quantity'],
                    'low_stock_threshold' => $variant['low_stock_threshold'] ?? 5,
                    'attributes' => $attributes,
                    'is_default' => filter_var($variant['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ],
            );
        }

        foreach ($product->variants()->get() as $existing) {
            $key = $this->variantKey($existing->color_value_id, $existing->size_value_id);
            if (in_array($key, $submittedKeys, true)) {
                continue;
            }

            $isReferenced = CartItem::where('variant_id', $existing->id)->exists()
                || OrderItem::where('variant_id', $existing->id)->exists();

            if (! $isReferenced) {
                $existing->delete();
            }
        }

        $valueIds = [];
        foreach ($variantsByKey as $variant) {
            if ($variant->color_value_id) {
                $valueIds[] = $variant->color_value_id;
            }
            if ($variant->size_value_id) {
                $valueIds[] = $variant->size_value_id;
            }
        }
        $product->attributeValues()->sync(array_values(array_unique($valueIds)));

        return $variantsByKey;
    }

    private function variantKey(?int $colorId, ?int $sizeId): string
    {
        return ($colorId ?? 'none').'-'.($sizeId ?? 'none');
    }

    /**
     * @param  array<int, string|UploadedFile>  $images
     * @param  array<int, string|null>  $mediaTags
     * @param  array<string, ProductVariant>  $variantsByKey
     */
    private function syncMedia(Product $product, array $images, array $mediaTags, array $variantsByKey): void
    {
        $product->media()->delete();

        if (empty($images)) {
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

        foreach ($images as $i => $fileOrUrl) {
            $type = 'image';
            if ($fileOrUrl instanceof UploadedFile) {
                $mime = $fileOrUrl->getMimeType();
                $type = str_starts_with($mime, 'video/') ? 'video' : 'image';
                $url = '/storage/'.$fileOrUrl->store('products', 'public');
            } else {
                $url = $fileOrUrl;
            }

            if (! $url) {
                continue;
            }

            ProductMedia::create([
                'product_id' => $product->id,
                'variant_id' => $this->resolveVariantIdFromTag($mediaTags[$i] ?? null, $variantsByKey),
                'path' => $url,
                'type' => $type,
                'is_primary' => $i === 0,
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * @param  array<string, ProductVariant>  $variantsByKey
     */
    private function resolveVariantIdFromTag(?string $tag, array $variantsByKey): ?int
    {
        if (! $tag) {
            return null;
        }

        if (str_starts_with($tag, 'color:')) {
            return $variantsByKey[$this->variantKey((int) substr($tag, 6), null)]->id ?? null;
        }

        if (str_starts_with($tag, 'size:')) {
            return $variantsByKey[$this->variantKey(null, (int) substr($tag, 5))]->id ?? null;
        }

        if (str_starts_with($tag, 'pair:')) {
            [$colorId, $sizeId] = array_map('intval', explode('-', substr($tag, 5)));

            return $variantsByKey[$this->variantKey($colorId, $sizeId)]->id ?? null;
        }

        return null;
    }
}
