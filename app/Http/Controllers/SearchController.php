<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\SubCategory;
use App\Support\ProductCardData;
use App\Support\ProductSearchQuery;
use App\Support\SearchSuggestionCache;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SearchTerm::normalize($request->input('q'));
        $sort = $request->string('sort', 'relevance')->toString();
        $categorySlug = $request->string('category')->toString() ?: null;
        $view = $request->string('view', 'grid')->toString();

        $minPrice = $request->filled('min_price') ? $request->integer('min_price') : null;
        $maxPrice = $request->filled('max_price') ? $request->integer('max_price') : null;

        $brandSlugs = collect($request->input('brand', []))
            ->flatten()
            ->map(static fn ($slug) => (string) $slug)
            ->filter()
            ->values()
            ->all();

        $minimumRating = $request->filled('rating') ? $request->integer('rating') : null;

        $products = Product::query()
            ->visible()
            // PERF-001: variants.media — ProductVariant's appended `image`
            // accessor otherwise lazy-loads it per variant on serialization.
            ->with(['images', 'brand', 'category', 'variants.media'])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->when($query !== '' && ! SearchTerm::isSearchable($query), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(SearchTerm::isSearchable($query), fn ($q) => $q->where(fn ($sub) => ProductSearchQuery::apply($sub, $query)
                // PERF-007: looked up first, because an OR across whereHas
                // subqueries makes MySQL read every product.
                ->orWhereIn('brand_id', Brand::where('name', 'like', "%{$query}%")->pluck('id'))
                ->orWhereIn('category_id', Category::where('name', 'like', "%{$query}%")->pluck('id'))
                ->orWhereIn('sub_category_id', SubCategory::where('name', 'like', "%{$query}%")->pluck('id'))
            ))
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)))
            ->when($brandSlugs !== [], fn ($q) => $q->whereHas('brand', fn ($b) => $b->whereIn('slug', $brandSlugs)))
            ->when($minPrice !== null, fn ($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice !== null, fn ($q) => $q->where('price', '<=', $maxPrice))
            ->when($request->boolean('in_stock'), fn ($q) => $q->inStock())
            ->when($minimumRating !== null && $minimumRating > 0, fn ($q) => $q->whereIn(
                'products.id',
                Review::query()
                    ->select('product_id')
                    ->where('is_approved', true)
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= ?', [$minimumRating])
            ))
            ->tap(function ($q) use ($sort) {
                match ($sort) {
                    'price_asc' => $q->orderByDisplayedPrice('asc'),
                    'price_desc' => $q->orderByDisplayedPrice('desc'),
                    'newest' => $q->orderByDesc('published_at')->orderByDesc('created_at'),
                    'rating' => $q->orderByRaw('COALESCE(average_rating, 0) DESC')->orderByDesc('reviews_count'),
                    default => $q->orderByDesc('is_featured')->orderByDesc('published_at'),
                };
            })
            ->paginate(12)
            ->withQueryString();

        $this->logSearch($request, $query, $products->total());

        // Same gap as ProductListingController's listing grid, same fix:
        // batched for just this page's ~12 products, one extra query, not
        // one per product.
        $flashPrices = FlashSaleItem::activePricesForProducts(
            $products->getCollection()->pluck('id')->all(),
        );

        foreach ($products as $product) {
            $flash = $flashPrices[$product->id] ?? null;
            $product->in_flash_sale = $flash !== null;
            $product->flash_sale_price = $flash['price'] ?? null;
            $product->flash_sale_ends_at = $flash['ends_at'] ?? null;
        }

        $categories = Category::query()
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        $brands = Brand::query()
            ->select(['id', 'name', 'slug'])
            ->active()
            ->withCount([
                'products as products_count' => static fn ($q) => $q->visible(),
            ])
            ->orderBy('name')
            ->get();

        $catalogMaxPrice = (int) (Product::query()->visible()->max('price') ?? 50000);

        return Inertia::render('search/SearchResults', [
            'query' => $query,
            'products' => $products->through(ProductCardData::make(...)),
            'categories' => $categories,
            'brands' => $brands,
            'maxPrice' => $catalogMaxPrice,
            'filters' => [
                'q' => $query,
                'category' => $categorySlug,
                'brand' => $brandSlugs,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'rating' => $minimumRating,
                'in_stock' => $request->boolean('in_stock'),
                'sort' => $sort,
                'view' => $view,
                'page' => $products->currentPage(),
            ],
        ]);
    }

    /**
     * Dashboard search insights: counts a typed search once, on its first
     * results page. Filtering, sorting, paging and Inertia prefetches of the
     * same search aren't new searches, and bots aren't counted.
     */
    private function logSearch(Request $request, string $query, int $resultsCount): void
    {
        $isRefinement = collect(['category', 'brand', 'min_price', 'max_price', 'rating', 'in_stock', 'sort', 'page'])
            ->contains(fn (string $key): bool => $request->filled($key));

        if (! SearchTerm::isSearchable($query)
            || $isRefinement
            || $request->header('Purpose') === 'prefetch'
            || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|bingpreview/i', (string) $request->userAgent()) === 1) {
            return;
        }

        SearchLog::record($query, $resultsCount);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = SearchTerm::normalize($request->input('q'));

        $empty = ['products' => [], 'brands' => [], 'categories' => [], 'subcategories' => []];

        if (! SearchTerm::isSearchable($query)) {
            return response()->json($empty);
        }

        return response()->json(SearchSuggestionCache::remember($query, function () use ($query): array {
            $matchedProducts = Product::query()
                ->visible()
                ->with(['images'])
                ->where(fn ($q) => ProductSearchQuery::apply($q, $query, includeShortDescription: false))
                ->orderByDesc('is_featured')
                ->limit(4)
                ->get();

            // Same gap as the listing/search-results pages, same fix — only
            // a handful of rows here, so one extra query for all of them.
            // This result is itself cached (below) for up to 5 minutes, so
            // a sale starting or ending can lag here by that long, the same
            // backstop staleness SearchSuggestionCache already accepts for
            // a product's scheduled published_at.
            $flashPrices = FlashSaleItem::activePricesForProducts($matchedProducts->pluck('id')->all());

            $products = $matchedProducts->map(function ($p) use ($flashPrices) {
                $flash = $flashPrices[$p->id] ?? null;

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => $p->price,
                    'compare_price' => $p->compare_price,
                    'primary_image_url' => $p->primaryImagePath(),
                    'images' => $p->images->map(fn ($i) => ['path' => $i->path])->values(),
                    'in_flash_sale' => $flash !== null,
                    'flash_sale_price' => $flash['price'] ?? null,
                ];
            });

            $brands = Brand::query()
                ->active()
                ->where('name', 'like', "%{$query}%")
                ->limit(3)
                ->get(['id', 'name', 'slug']);

            $categories = Category::query()
                ->active()
                ->where('name', 'like', "%{$query}%")
                ->limit(3)
                ->get(['id', 'name', 'slug']);

            $subcategories = SubCategory::query()
                ->active()
                // A sub-category whose parent category is now inactive
                // must not be suggested either — its own URL 404s.
                ->whereHas('category', fn ($q) => $q->where('is_active', true))
                ->where('name', 'like', "%{$query}%")
                ->with(['category:id,name,slug'])
                ->limit(3)
                ->get(['id', 'name', 'slug', 'category_id'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'category_name' => $s->category?->name,
                ]);

            return [
                'products' => $products->toArray(),
                'brands' => $brands->toArray(),
                'categories' => $categories->toArray(),
                'subcategories' => $subcategories->toArray(),
            ];
        }));
    }
}
