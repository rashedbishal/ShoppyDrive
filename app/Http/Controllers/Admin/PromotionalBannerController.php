<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PromotionalBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class PromotionalBannerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/homepage/promotional-banners/index', [
            'banners' => PromotionalBanner::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/homepage/promotional-banners/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', function ($attribute, $value, $fail): void {
                if ($value !== null && ! is_string($value) && ! ($value instanceof UploadedFile)) {
                    $fail("The {$attribute} must be an image file.");
                }
            }],
            'href' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:100'],
            'accent' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/'.$request->file('image')->store('promotional-banners', 'public');
        } else {
            $validated['image'] = '/placeholder.svg';
        }

        PromotionalBanner::create($validated);

        return redirect()->route('admin.promotional-banners.index')->with('success', 'Promotional banner created.');
    }

    public function edit(PromotionalBanner $promotionalBanner): Response
    {
        return Inertia::render('admin/homepage/promotional-banners/edit', [
            'banner' => $promotionalBanner,
            'bannerProducts' => $promotionalBanner->products()->get(['products.id', 'products.name', 'products.price']),
        ]);
    }

    public function update(Request $request, PromotionalBanner $promotionalBanner): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', function ($attribute, $value, $fail): void {
                if ($value !== null && ! is_string($value) && ! ($value instanceof UploadedFile)) {
                    $fail("The {$attribute} must be an image file.");
                }
            }],
            'href' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:100'],
            'accent' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer', 'exists:products,id'],
        ]);

        $productIds = collect($request->input('products', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if (! empty($productIds)) {
            $validated['href'] = "/products?banner_id={$promotionalBanner->id}";
        }

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/'.$request->file('image')->store('promotional-banners', 'public');
        } else {
            unset($validated['image']);
        }

        $promotionalBanner->update($validated);
        $promotionalBanner->products()->sync($productIds);

        return redirect()->route('admin.promotional-banners.index')->with('success', 'Promotional banner updated.');
    }

    public function destroy(PromotionalBanner $promotionalBanner): RedirectResponse
    {
        $promotionalBanner->delete();

        return redirect()->route('admin.promotional-banners.index')->with('success', 'Promotional banner deleted.');
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        $products = Product::active()
            ->published()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'price']);

        return response()->json($products);
    }
}
