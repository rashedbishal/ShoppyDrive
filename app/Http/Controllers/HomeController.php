<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Services\BundleService;
use App\Support\LayoutCache;
use App\Support\ProductCardData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $activeFlashSale = FlashSale::with([
            // Excludes items whose product was deleted/deactivated since
            // being added to the sale — otherwise `item.product` would be
            // null and crash the frontend's product card rendering.
            'items' => static function ($query): void {
                $query->whereHas('product', static fn ($productQuery) => $productQuery->visible())
                    // PERF-001: ProductVariant::$appends = ['image'] lazy-loads
                    // its own `media` relation during serialization if not
                    // eager-loaded — one query per variant otherwise.
                    ->with(['product.brand', 'product.images', 'product.variants.media']);
            },
        ])
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();

        // A product can now have more than one active item at once (a
        // whole-product one, specific variant combinations, or several of
        // each — see Admin\FlashSaleController), and `items.product` is
        // the same shared model instance for every item that references
        // the same product_id (Eloquent's belongsTo eager loading matches
        // them from one query result), so naively overwriting these on
        // every iteration left whichever item happened to be last in the
        // list silently winning, even though its own `sale_price` was no
        // more "the" price than any other item's. Grouped by product and
        // resolved to its single lowest active price instead, both here
        // (for $mapProduct's generic flash fields) and in the deduped
        // `items` list below (one card per product, not one per item).
        $itemsByProduct = $activeFlashSale
            ? $activeFlashSale->items->filter(fn ($item) => $item->product !== null)->groupBy('product_id')
            : collect();

        foreach ($itemsByProduct as $productItems) {
            $product = $productItems->first()->product;
            $product->in_flash_sale = true;
            $product->flash_sale_price = $productItems->min('sale_price');
        }

        // PERF-001: variants.media, not just variants — ProductVariant's
        // appended `image` accessor lazy-loads its own media relation
        // during serialization otherwise, one query per variant.
        $productEagerLoads = ['brand', 'images', 'category', 'variants.media'];

        // Load CMS section settings for dynamic sections
        $sectionSettings = HomepageSetting::whereIn('section_key', ['new_arrivals', 'best_sellers', 'brands'])
            ->get()
            ->keyBy('section_key');

        $featuredProducts = Product::visible()
            ->featured()
            ->with($productEagerLoads)
            ->withCount(['reviews as reviews_count' => static fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews as average_rating' => static fn ($q) => $q->where('is_approved', true)], 'rating')
            ->limit(8)
            ->get();

        $newArrivalsMeta = $sectionSettings->get('new_arrivals')?->metadata;
        $newArrivalIds = is_array($newArrivalsMeta) ? ($newArrivalsMeta['product_ids'] ?? []) : [];

        if (! empty($newArrivalIds)) {
            $newArrivals = Product::visible()
                ->whereIn('id', $newArrivalIds)
                ->with($productEagerLoads)
                ->withCount(['reviews as reviews_count' => static fn ($q) => $q->where('is_approved', true)])
                ->withAvg(['reviews as average_rating' => static fn ($q) => $q->where('is_approved', true)], 'rating')
                ->get()
                ->sortBy(fn (Product $product) => array_search($product->id, $newArrivalIds))
                ->values();
        } else {
            $newArrivals = collect();
        }

        $bestSellersMeta = $sectionSettings->get('best_sellers')?->metadata;
        $bestSellerIds = is_array($bestSellersMeta) ? ($bestSellersMeta['product_ids'] ?? []) : [];

        if (! empty($bestSellerIds)) {
            $bestSellers = Product::visible()
                ->whereIn('id', $bestSellerIds)
                ->with($productEagerLoads)
                ->withCount(['reviews as reviews_count' => static fn ($q) => $q->where('is_approved', true)])
                ->withAvg(['reviews as average_rating' => static fn ($q) => $q->where('is_approved', true)], 'rating')
                ->get()
                ->sortBy(fn (Product $product) => array_search($product->id, $bestSellerIds))
                ->values();
        } else {
            $bestSellers = collect();
        }

        $brandsMeta = $sectionSettings->get('brands')?->metadata;
        $brandIds = is_array($brandsMeta) ? ($brandsMeta['brand_ids'] ?? []) : [];

        if (! empty($brandIds)) {
            $brands = Brand::active()
                ->whereIn('id', $brandIds)
                ->get(['id', 'name', 'slug', 'logo', 'description'])
                ->sortBy(fn (Brand $brand) => array_search($brand->id, $brandIds))
                ->values();
        } else {
            $brands = collect();
        }

        $bundleDeals = ProductBundle::query()
            ->active()
            ->with([
                // Same guard as the dedicated /bundle-deals page — excludes
                // bundles whose component product was deleted/deactivated
                // since being added.
                'items' => static function ($query): void {
                    $query->whereHas('product', static fn ($productQuery) => $productQuery->visible())
                        ->with([
                            'product' => static fn ($productQuery) => $productQuery->with('images')->withExists('variants'),
                            'variant',
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ProductBundle $bundle): bool => $bundle->items->count() >= 2)
            ->take(3)
            ->each(fn (ProductBundle $bundle) => $bundle->setAttribute('available_sets', app(BundleService::class)->availableSets($bundle)))
            ->values();

        // BIZ (flash-sale price consistency): $itemsByProduct's mutation
        // above only touches the Product instances loaded via the flash
        // sale's own `items.product` eager load — $featuredProducts,
        // $newArrivals and $bestSellers are three entirely separate
        // queries, so those are different PHP objects for the same DB
        // rows and never received `in_flash_sale`/`flash_sale_price` at
        // all. $mapProduct looks the price up by id instead of trusting a
        // dynamic property that may have been set on a different
        // instance representing the same product.
        $flashPricesForSections = FlashSaleItem::activePricesForProducts(
            $featuredProducts->pluck('id')
                ->merge($newArrivals->pluck('id'))
                ->merge($bestSellers->pluck('id'))
                ->unique()
                ->values()
                ->all(),
        );

        $categories = Category::active()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'image', 'icon', 'color', 'sort_order']);

        $mapProduct = function (Product $product) use ($flashPricesForSections): array {
            // Falls back to the dynamic property for a product this
            // closure is called on that wasn't part of the lookup built
            // above (the flash-sale carousel's own products, via
            // $mapFlashSaleProduct below, which already correctly set it
            // on this exact instance).
            $flash = $flashPricesForSections[$product->id] ?? null;

            return array_merge(ProductCardData::make($product), [
                'in_flash_sale' => $flash !== null || ($product->in_flash_sale ?? false),
                'flash_sale_price' => $flash['price'] ?? $product->flash_sale_price ?? null,
            ]);
        };

        $mapFlashSaleProduct = function (Product $product) use ($mapProduct): array {
            $mapped = $mapProduct($product);
            $mapped['in_flash_sale'] = $product->in_flash_sale ?? false;
            $mapped['flash_sale_price'] = $product->flash_sale_price ?? null;

            return $mapped;
        };

        $flashSaleData = null;
        if ($activeFlashSale) {
            $flashSaleData = [
                'id' => $activeFlashSale->id,
                'name' => $activeFlashSale->name,
                'starts_at' => $activeFlashSale->starts_at,
                'ends_at' => $activeFlashSale->ends_at,
                'is_active' => $activeFlashSale->is_active,
                // One card per product: the group's cheapest item stands
                // in for the product-level fields (id, flash_sale_id —
                // not meaningful once several items are combined, but
                // every item in a group shares the same flash_sale_id
                // regardless), sale_price is that lowest price, and
                // sold_count is combined across every item
                // for this product so the progress bar reflects the whole
                // campaign for it, not just whichever one was cheapest.
                'items' => $itemsByProduct->map(function ($productItems) use ($mapFlashSaleProduct) {
                    $cheapest = $productItems->sortBy('sale_price')->first();

                    return [
                        'id' => $cheapest->id,
                        'flash_sale_id' => $cheapest->flash_sale_id,
                        'product_id' => $cheapest->product_id,
                        'sale_price' => $cheapest->sale_price,
                        'sold_count' => $productItems->sum('sold_count'),
                        'product' => $mapFlashSaleProduct($cheapest->product),
                    ];
                })->values(),
            ];
        }

        return Inertia::render('welcome', [
            'activeFlashSale' => $flashSaleData,
            'featuredProducts' => $featuredProducts->map($mapProduct)->values(),
            'newArrivals' => $newArrivals->map($mapProduct)->values(),
            'bestSellers' => $bestSellers->map($mapProduct)->values(),
            'bundleDeals' => $bundleDeals,
            'brands' => $brands,
            'categories' => $categories,
            // Home-only — previously shared globally by HandleInertiaRequests
            // on every navigation regardless of destination page. Same
            // LayoutCache calls, same values, same shape; only moved to
            // where they're actually consumed (resources/js/pages/Home.tsx).
            // 'homepage_settings' here is the full section-config object —
            // it overrides the global once-prop's slimmed (announcement_bar
            // -only) version for this response only, since page props win
            // over shared props of the same key.
            'homepage_settings' => LayoutCache::homepageSettings(),
            'hero_slides' => LayoutCache::heroSlides(),
            'promotional_banners' => LayoutCache::promotionalBanners(),
        ]);
    }
}
