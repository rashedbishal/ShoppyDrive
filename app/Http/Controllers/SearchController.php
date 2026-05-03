<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
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
        $minPrice = $request->filled('min_price') ? $request->integer('min_price') : null;
        $maxPrice = $request->filled('max_price') ? $request->integer('max_price') : null;
        $categorySlug = $request->string('category')->toString() ?: null;
        $inStock = $request->boolean('in_stock');

        $products = Product::query()
            ->active()
            ->published()
            ->with(['images', 'brand', 'category'])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->when($query !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('name', 'ilike', "%{$query}%")
                ->orWhere('sku', 'ilike', "%{$query}%")
                ->orWhere('short_description', 'ilike', "%{$query}%")
            ))
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)))
            ->when($minPrice !== null, fn ($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice !== null, fn ($q) => $q->where('price', '<=', $maxPrice))
            ->when($inStock, fn ($q) => $q->where('stock_quantity', '>', 0))
            ->tap(function ($q) use ($sort) {
                match ($sort) {
                    'price_asc' => $q->orderBy('price'),
                    'price_desc' => $q->orderByDesc('price'),
                    'newest' => $q->orderByDesc('published_at'),
                    'rating' => $q->orderByRaw('COALESCE(average_rating, 0) DESC'),
                    default => $q->orderByDesc('is_featured')->orderByDesc('published_at'),
                };
            })
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        $catalogMaxPrice = (int) (Product::query()->active()->published()->max('price') ?? 50000);

        return Inertia::render('search/SearchResults', [
            'query' => $query,
            'products' => $products,
            'categories' => $categories,
            'maxPrice' => $catalogMaxPrice,
            'filters' => [
                'q' => $query,
                'category' => $categorySlug,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'in_stock' => $inStock,
                'sort' => $sort,
            ],
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        if (strlen($query) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->active()
            ->published()
            ->with(['images'])
            ->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$query}%")
                ->orWhere('sku', 'ilike', "%{$query}%")
            )
            ->orderByDesc('is_featured')
            ->limit(6)
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

        return response()->json(['products' => $products]);
    }
}
