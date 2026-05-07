<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
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
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $images = $data['images'] ?? [];
        unset($data['images']);

        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_featured'] = $data['is_featured'] ?? false;

        if ($data['is_active'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $product = Product::create($data);

        if (empty($images)) {
            ProductMedia::create([
                'product_id' => $product->id,
                'path' => '/placeholder.svg',
                'type' => 'image',
                'alt' => $product->name,
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        } else {
            foreach ($images as $i => $fileOrUrl) {
                $type = 'image';
                if ($fileOrUrl instanceof UploadedFile) {
                    $mime = $fileOrUrl->getMimeType();
                    $type = str_starts_with($mime, 'video/') ? 'video' : 'image';
                    $url = '/storage/'.$fileOrUrl->store('products', 'public');
                } else {
                    $url = $fileOrUrl;
                }
                if ($url) {
                    ProductMedia::create([
                        'product_id' => $product->id,
                        'path' => $url,
                        'type' => $type,
                        'is_primary' => $i === 0,
                        'sort_order' => $i,
                    ]);
                }
            }
        }

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/edit', [
            'product' => $product->load(['media', 'category:id,name', 'subCategory:id,name', 'brand:id,name']),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'subCategories' => SubCategory::active()->orderBy('name')->get(['id', 'name', 'category_id']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $images = $data['images'] ?? null;
        unset($data['images']);

        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $data['is_active'] = $data['is_active'] ?? false;
        $data['is_featured'] = $data['is_featured'] ?? false;

        if ($data['is_active'] && $product->published_at === null) {
            $data['published_at'] = now();
        }

        $product->update($data);

        if ($images !== null) {
            $product->media()->delete();
            foreach ($images as $i => $fileOrUrl) {
                $type = 'image';
                if ($fileOrUrl instanceof UploadedFile) {
                    $mime = $fileOrUrl->getMimeType();
                    $type = str_starts_with($mime, 'video/') ? 'video' : 'image';
                    $url = '/storage/'.$fileOrUrl->store('products', 'public');
                } else {
                    $url = $fileOrUrl;
                }
                if ($url) {
                    ProductMedia::create([
                        'product_id' => $product->id,
                        'path' => $url,
                        'type' => $type,
                        'is_primary' => $i === 0,
                        'sort_order' => $i,
                    ]);
                }
            }
        }

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }
}
