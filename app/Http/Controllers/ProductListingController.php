<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\PromotionalBanner;
use App\Models\Review;
use App\Models\SlugRedirect;
use App\Models\SubCategory;
use App\Support\CatalogFilterCache;
use App\Support\PageSeo;
use App\Support\ProductCardData;
use App\Support\ProductSearchQuery;
use App\Support\SearchTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductListingController extends Controller
{
    public const string ALL_PRODUCTS_TITLE = 'All Products: Watches, Night Lights, Toys & Gifts';

    public const string ALL_PRODUCTS_DESCRIPTION = 'Browse every product at ShoppyDrive: wrist watches, night lights, toys and gift items, with prices in taka and delivery inside and outside Dhaka.';

    /**
     * DISC-104: a category's share image is its own picture; without one (or
     * with only the placeholder) it is the photo of the product the listing
     * shows first, so the preview shows something from that category rather
     * than the site-wide default. No image anywhere still means the default.
     */
    private static function shareImage(?string $listingImage, ?Category $category, ?SubCategory $subCategory): ?string
    {
        if (filled($listingImage) && ! str_ends_with((string) $listingImage, '.svg')) {
            return $listingImage;
        }

        $firstProduct = Product::query()
            ->visible()
            ->when($category, fn ($query) => $query->where('category_id', $category->id))
            ->when($subCategory, fn ($query) => $query->where('sub_category_id', $subCategory->id))
            ->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('created_at')
            ->with('images')
            ->first();
        $path = $firstProduct?->primaryImagePath();

        return filled($path) && ! str_ends_with((string) $path, '.svg') ? $path : $listingImage;
    }

    public function index(Request $request): Response
    {
        // DISC-102: names what the shop sells instead of the bare "All Products";
        // the same title reaches the browser tab (seoTitle, DISC-88).
        $seoTitle = self::ALL_PRODUCTS_TITLE;
        PageSeo::share($seoTitle, self::ALL_PRODUCTS_DESCRIPTION, heading: 'All products');

        return $this->catalogResponse($request, null, null, route('products.index'), $seoTitle);
    }

    public function category(string $slug, Request $request): Response|RedirectResponse
    {
        // An inactive category/sub-category's URL must 404, not just hide
        // from menus — and a sub-category whose own parent category was
        // deactivated must 404 too, even if the sub-category itself is
        // still marked active.
        $category = Category::where('slug', $slug)->where('is_active', true)->first();
        $subCategory = null;

        if (! $category) {
            $subCategory = SubCategory::where('slug', $slug)
                ->where('is_active', true)
                ->whereHas('category', fn ($q) => $q->where('is_active', true))
                ->first();
        }

        // DISC-91: nothing to show. Only a slug no category or sub-category
        // holds any more (an old, renamed one) may 301 to its record's
        // current URL; a hidden record keeping this slug still 404s.
        if (! $category && ! $subCategory) {
            abort_if(Category::where('slug', $slug)->exists() || SubCategory::where('slug', $slug)->exists(), 404);

            return SlugRedirect::redirectOrNotFound(Category::SLUG_REDIRECT_NAMESPACE, $slug, $request);
        }

        $listing = $category ?? $subCategory;

        // DISC-83: Home, then a sub-category's real parent category, then
        // this listing. The parent is active (checked by the query above).
        $breadcrumbs = PageSeo::breadcrumbList([
            ['Home', route('home')],
            ...($subCategory ? [[$subCategory->category->name, route('category.show', $subCategory->category->slug)]] : []),
            [$listing->name, route('category.show', $listing->slug)],
        ]);

        $seoTitle = $listing->meta_title ?: $listing->name;
        PageSeo::share(
            $seoTitle,
            $listing->meta_description ?: PageSeo::descriptionFromContent($listing->description),
            self::shareImage($listing->image, $category, $subCategory),
            breadcrumbs: $breadcrumbs,
            // DISC-101: the no-JavaScript fallback's <h1> is the listing's name, like the page's own.
            heading: $listing->name,
        );

        return $this->catalogResponse($request, $category, $subCategory, route('category.show', $slug), $seoTitle);
    }

    /**
     * @param  string|null  $seoTitle  DISC-88: the title PageSeo printed, so the browser tab (set by
     *                                 ProductListing.tsx after JavaScript runs) shows the same one
     */
    private function catalogResponse(Request $request, ?Category $category, ?SubCategory $subCategory, string $listingUrl, ?string $seoTitle = null): Response
    {
        $query = Product::query()
            ->visible()
            ->with([
                'category',
                'subCategory',
                'brand',
                'images',
                // PERF-001: variants.media, not just variants — the
                // appended `image` accessor on ProductVariant lazy-loads
                // its own media relation during serialization otherwise.
                'variants.media',
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

        $search = SearchTerm::normalize($request->input('q'));
        if ($search !== '' && ! SearchTerm::isSearchable($search)) {
            $query->whereRaw('1 = 0');
        } elseif ($search !== '') {
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

        $minPrice = $request->filled('min_price') ? $request->integer('min_price') : null;
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        $maxPrice = $request->filled('max_price') ? $request->integer('max_price') : null;
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        $minimumRating = $request->integer('rating') ?: null;
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
            'price_asc' => $query->orderByDisplayedPrice('asc'),
            'price_desc' => $query->orderByDisplayedPrice('desc'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('created_at'),
            'top_rated' => $query->orderByRaw('COALESCE(average_rating, 0) DESC')->orderByDesc('reviews_count'),
            'most_reviewed' => $query->orderByDesc('reviews_count')->orderByRaw('COALESCE(average_rating, 0) DESC'),
            default => $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('created_at'),
        };

        $products = $query
            ->paginate(12)
            ->withQueryString();

        // Batched for just this page's ~12 products (not the whole filtered
        // set) — one extra query here, not one per product. Without this,
        // ProductCard fell back to the plain price column, showing it stale
        // for anything actually on an active flash sale right now (the
        // product detail page, via ProductController, already got this
        // right).
        $flashPrices = FlashSaleItem::activePricesForProducts(
            $products->getCollection()->pluck('id')->all(),
        );

        foreach ($products as $product) {
            $flash = $flashPrices[$product->id] ?? null;
            $product->in_flash_sale = $flash !== null;
            $product->flash_sale_price = $flash['price'] ?? null;
            $product->flash_sale_ends_at = $flash['ends_at'] ?? null;
        }

        // Wrapped in closures (not eagerly queried above) so a filter-triggered
        // partial reload — which only asks for `products`/`filters`, see
        // Filters sidebar's `onApply`/`router.get(..., { only: [...] })` — never
        // re-runs these queries at all, instead of just trimming them from the
        // response. On a full page load the filter options come from
        // CatalogFilterCache (identical for every visitor and listing page).
        return Inertia::render('catalog/ProductListing', [
            'category' => $category ?? $subCategory,
            // The banner's overlay colour: the category's own colour, or for a
            // sub-category its parent's (sub-categories have no colour field).
            'bannerColor' => $category?->color ?? $subCategory?->category?->color,
            'categories' => fn () => CatalogFilterCache::categories(),
            'brands' => fn () => CatalogFilterCache::brands(),
            'attributes' => fn () => CatalogFilterCache::attributes(),
            'products' => $products->through(ProductCardData::make(...)),
            'listingUrl' => $listingUrl,
            'maxPrice' => fn () => CatalogFilterCache::maxPrice(),
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
            'seoTitle' => $seoTitle,
        ]);
    }
}
