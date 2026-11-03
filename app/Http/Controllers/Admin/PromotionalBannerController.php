<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PromotionalBanner;
use App\Rules\RasterImageOrUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionalBannerController extends Controller
{
    /** Keeps the badge pill from wrapping or overflowing the card at any breakpoint. */
    private const BADGE_MAX_LENGTH = 40;

    /** The banner renders two per row, so the headline has roughly half the hero's width to work with. */
    private const TITLE_MAX_LENGTH = 80;

    /** Clamped to 2 lines on the storefront; longer text would just get cut off. */
    private const DESCRIPTION_MAX_LENGTH = 150;

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
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX_LENGTH],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX_LENGTH],
            'image' => ['nullable', new RasterImageOrUrl],
            'href' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:'.self::BADGE_MAX_LENGTH],
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
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX_LENGTH],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX_LENGTH],
            'image' => ['nullable', new RasterImageOrUrl],
            'href' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:'.self::BADGE_MAX_LENGTH],
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
        } elseif (blank($validated['image'] ?? null)) {
            // The admin cleared the image without picking a replacement — RasterImageOrUrl
            // accepts a blank value as "keep as-is", so an empty string here means the
            // field was explicitly emptied, not merely left untouched (the client always
            // resends the current path otherwise). The column isn't nullable, so fall back
            // to the same placeholder used when a banner is first created without an image.
            $validated['image'] = '/placeholder.svg';
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
