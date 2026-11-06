<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Support\PageSeo;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        $brands = Brand::query()
            ->active()
            ->withCount([
                'products as products_count' => static fn ($query) => $query->visible(),
            ])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('catalog/BrandListing', [
            'brands' => $brands,
        ]);
    }

    public function show(Brand $brand): Response
    {
        // Implicit route-model binding resolves any brand by slug
        // regardless of status — an inactive brand's URL must 404, not
        // just be hidden from the /brands index.
        abort_unless($brand->is_active, 404);

        PageSeo::share($brand->name, $brand->description, $brand->logo);

        $products = Product::query()
            ->visible()
            ->where('brand_id', $brand->id)
            // PERF-001: variants.media — ProductVariant's appended `image`
            // accessor otherwise lazy-loads it per variant on serialization.
            ->with(['category', 'images', 'variants.media'])
            ->withCount([
                'reviews as reviews_count' => static fn ($query) => $query->where('is_approved', true),
            ])
            ->withAvg([
                'reviews as average_rating' => static fn ($query) => $query->where('is_approved', true),
            ], 'rating')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        // Same gap as the listing/search pages: never looked up a flash
        // sale for the products it shows.
        $flashPrices = FlashSaleItem::activePricesForProducts(
            $products->getCollection()->pluck('id')->all(),
        );

        foreach ($products as $product) {
            $flash = $flashPrices[$product->id] ?? null;
            $product->in_flash_sale = $flash !== null;
            $product->flash_sale_price = $flash['price'] ?? null;
            $product->flash_sale_ends_at = $flash['ends_at'] ?? null;
        }

        $brandWithCount = $brand->loadCount([
            'products as products_count' => static fn ($query) => $query->visible(),
        ]);

        return Inertia::render('catalog/BrandDetail', [
            'brand' => $brandWithCount,
            'products' => $products,
        ]);
    }
}
