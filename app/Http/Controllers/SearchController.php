<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubCategory;
use App\Support\ProductSearchQuery;
use App\Support\SearchSuggestionCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('q'));
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
            ->active()
            ->published()
            ->with(['images', 'brand', 'category'])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->when($query !== '', fn ($q) => $q->where(fn ($sub) => ProductSearchQuery::apply($sub, $query)
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$query}%"))
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$query}%"))
                ->orWhereHas('subCategory', fn ($s) => $s->where('name', 'like', "%{$query}%"))
            ))
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)))
            ->when($brandSlugs !== [], fn ($q) => $q->whereHas('brand', fn ($b) => $b->whereIn('slug', $brandSlugs)))
            ->when($minPrice !== null, fn ($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice !== null, fn ($q) => $q->where('price', '<=', $maxPrice))
            ->when($request->boolean('in_stock'), fn ($q) => $q->where('stock_quantity', '>', 0))
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
                    'price_asc' => $q->orderBy('price'),
                    'price_desc' => $q->orderByDesc('price'),
                    'newest' => $q->orderByDesc('published_at')->orderByDesc('created_at'),
                    'rating' => $q->orderByRaw('COALESCE(average_rating, 0) DESC')->orderByDesc('reviews_count'),
                    default => $q->orderByDesc('is_featured')->orderByDesc('published_at'),
                };
            })
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        $brands = Brand::query()
            ->active()
            ->withCount([
                'products as products_count' => static fn ($q) => $q->active()->published(),
            ])
            ->orderBy('name')
            ->get();

        $catalogMaxPrice = (int) (Product::query()->active()->published()->max('price') ?? 50000);

        return Inertia::render('search/SearchResults', [
            'query' => $query,
            'products' => $products,
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

    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        $empty = ['products' => [], 'brands' => [], 'categories' => [], 'subcategories' => []];

        if (strlen($query) < 2) {
            return response()->json($empty);
        }

        return response()->json(SearchSuggestionCache::remember($query, function () use ($query): array {
            $products = Product::query()
                ->active()
                ->published()
                ->with(['images'])
                ->where(fn ($q) => ProductSearchQuery::apply($q, $query, includeShortDescription: false))
                ->orderByDesc('is_featured')
                ->limit(4)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => $p->price,
                    'compare_price' => $p->compare_price,
                    'primary_image_url' => $p->images->first()?->path,
                    'images' => $p->images->map(fn ($i) => ['path' => $i->path])->values(),
                ]);

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
