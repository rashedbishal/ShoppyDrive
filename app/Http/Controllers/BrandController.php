<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        $brands = Brand::query()
            ->active()
            ->withCount([
                'products as products_count' => static fn ($query) => $query->active()->published(),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('catalog/BrandListing', [
            'brands' => $brands,
        ]);
    }

    public function show(Brand $brand): Response
    {
        $products = Product::query()
            ->active()
            ->published()
            ->where('brand_id', $brand->id)
            ->with(['category', 'images'])
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

        $brandWithCount = $brand->loadCount([
            'products as products_count' => static fn ($query) => $query->active()->published(),
        ]);

        return Inertia::render('catalog/BrandDetail', [
            'brand' => $brandWithCount,
            'products' => $products,
        ]);
    }
}
