<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionalBanner;
use App\Models\Review;
use App\Models\SubCategory;
use App\Support\ProductSearchQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductListingController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->catalogResponse($request, null, null, route('products.index'));
    }

    public function category(string $slug, Request $request): Response
    {
        $category = Category::where('slug', $slug)->first();
        $subCategory = null;

        if (! $category) {
            $subCategory = SubCategory::where('slug', $slug)->firstOrFail();
        }

        return $this->catalogResponse($request, $category, $subCategory, route('category.show', $slug));
    }

    private function catalogResponse(Request $request, ?Category $category, ?SubCategory $subCategory, string $listingUrl): Response
    {
        $query = Product::query()
            ->active()
            ->published()
            ->with([
                'category',
                'subCategory',
                'brand',
                'images',
            ])
            ->withCount([
                'reviews as reviews_count' => static fn ($reviewQuery) => $reviewQuery->where('is_approved', true),
            ])
            ->withAvg([
                'reviews as average_rating' => static fn ($reviewQuery) => $reviewQuery->where('is_approved', true),
            ], 'rating');

        if ($category !== null) {
            $query->where('category_id', $category->id);
        }

        if ($subCategory !== null) {
            $query->where('sub_category_id', $subCategory->id);
        }

        $bannerId = $request->integer('banner_id') ?: null;
        $banner = null;
        if ($bannerId !== null) {
            $banner = PromotionalBanner::find($bannerId);
            if ($banner !== null) {
                $query->whereHas('promotionalBanners', fn ($q) => $q->where('promotional_banners.id', $bannerId));
            }
        }

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $query->where(static fn ($productQuery) => ProductSearchQuery::apply($productQuery, $search));
        }

        $brandSlugs = collect($request->input('brand', []))
            ->flatten()
            ->map(static fn ($brandSlug) => (string) $brandSlug)
            ->filter()
            ->values()
            ->all();

        if ($brandSlugs !== []) {
            $query->whereHas('brand', static function ($brandQuery) use ($brandSlugs): void {
                $brandQuery->whereIn('slug', $brandSlugs);
            });
        }

        $minPrice = $request->integer('min_price');
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        $maxPrice = $request->filled('max_price') ? $request->integer('max_price') : null;
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        $minimumRating = $request->integer('rating');
        if ($minimumRating !== null && $minimumRating > 0) {
            $query->whereIn(
                'products.id',
                Review::query()
                    ->select('product_id')
                    ->where('is_approved', true)
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= ?', [$minimumRating]),
            );
        }

        $attributes = collect($request->input('attributes', []))
            ->map(static fn ($values) => collect($values)
                ->flatten()
                ->map(static fn ($value) => (int) $value)
                ->filter(static fn (int $value) => $value > 0)
                ->values()
                ->all())
            ->filter(static fn (array $values) => $values !== [])
            ->all();

        foreach ($attributes as $attributeId => $attributeValueIds) {
            $query->whereHas('attributeValues', static function ($attributeQuery) use ($attributeId, $attributeValueIds): void {
                $attributeQuery
                    ->where('attribute_values.attribute_id', (int) $attributeId)
                    ->whereIn('attribute_values.id', $attributeValueIds);
            });
        }

        match ($request->string('sort', 'relevance')->toString()) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('created_at'),
            'top_rated' => $query->orderByRaw('COALESCE(average_rating, 0) DESC')->orderByDesc('reviews_count'),
            'most_reviewed' => $query->orderByDesc('reviews_count')->orderByRaw('COALESCE(average_rating, 0) DESC'),
            default => $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('created_at'),
        };

        $products = $query
            ->paginate(12)
            ->withQueryString();

        // Wrapped in closures (not eagerly queried above) so a filter-triggered
        // partial reload — which only asks for `products`/`filters`, see
        // Filters sidebar's `onApply`/`router.get(..., { only: [...] })` — never
        // re-runs these queries at all, instead of just trimming them from the
        // response. They're still resolved normally on the first full page load.
        return Inertia::render('catalog/ProductListing', [
            'category' => $category ?? $subCategory,
            'categories' => fn () => Category::query()
                ->active()
                ->withCount([
                    'products as products_count' => static fn ($productQuery) => $productQuery->active()->published(),
                ])
                ->with([
                    'subCategories' => static function ($childQuery): void {
                        $childQuery
                            ->active()
                            ->orderBy('sort_order')
                            ->withCount([
                                'products as products_count' => static fn ($productQuery) => $productQuery->active()->published(),
                            ]);
                    },
                ])
                ->orderBy('sort_order')
                ->get(),
            'brands' => fn () => Brand::query()
                ->active()
                ->withCount([
                    'products as products_count' => static fn ($productQuery) => $productQuery->active()->published(),
                ])
                ->orderBy('name')
                ->get(),
            'attributes' => fn () => Attribute::query()
                ->with([
                    'values' => static fn ($valueQuery) => $valueQuery->orderBy('sort_order'),
                ])
                ->orderBy('name')
                ->get(),
            'products' => $products,
            'listingUrl' => $listingUrl,
            'maxPrice' => fn () => (int) (Product::query()
                ->active()
                ->published()
                ->max('price') ?? 0),
            'filters' => [
                'q' => $search,
                'brand' => $brandSlugs,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'rating' => $minimumRating,
                'in_stock' => $request->boolean('in_stock'),
                'attributes' => $attributes,
                'sort' => $request->string('sort', 'relevance')->toString(),
                'view' => $request->string('view', 'grid')->toString(),
                'page' => $products->currentPage(),
                'banner_id' => $bannerId,
            ],
            'title' => $banner?->title ?? $category?->name ?? $subCategory?->name ?? 'All Products',
            'description' => $banner?->description ?? $category?->description ?? $subCategory?->description ?? 'Explore a premium BDT catalog with filters, sorting, and mobile-friendly browsing.',
        ]);
    }
}
