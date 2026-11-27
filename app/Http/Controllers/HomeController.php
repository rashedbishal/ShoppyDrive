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
use App\Models\Setting;
use App\Services\BundleService;
use App\Support\DisplayOrderSettings;
use App\Support\ImageVariants;
use App\Support\LayoutCache;
use App\Support\PageSeo;
use App\Support\PreloadImage;
use App\Support\ProductCardData;
use App\Support\SiteJsonLd;
use Illuminate\Support\Collection;
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
                DisplayOrderSettings::applyToQuery($query, 'flash_sale_items');
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

        $featuredProductsQuery = Product::visible()
            ->featured()
            ->with($productEagerLoads)
            ->withCount(['reviews as reviews_count' => static fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews as average_rating' => static fn ($q) => $q->where('is_approved', true)], 'rating');
        $featuredProducts = DisplayOrderSettings::applyToQuery($featuredProductsQuery, 'featured_products', ['published_at', 'created_at'])
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
                ->get();
            $newArrivals = DisplayOrderSettings::applyToCollection($newArrivals, 'new_arrivals', fn (Product $product) => $product->published_at ?? $product->created_at);
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
                ->get();
            $bestSellers = DisplayOrderSettings::applyToCollection($bestSellers, 'best_sellers', fn (Product $product) => $product->published_at ?? $product->created_at);
        } else {
            $bestSellers = collect();
        }

        $brandsMeta = $sectionSettings->get('brands')?->metadata;
        $brandIds = is_array($brandsMeta) ? ($brandsMeta['brand_ids'] ?? []) : [];

        if (! empty($brandIds)) {
            $brands = Brand::active()
                ->whereIn('id', $brandIds)
                ->select(['id', 'name', 'slug', 'logo', 'description', 'created_at'])
                ->withCount([
                    'products as products_count' => static fn ($query) => $query->visible(),
                ])
                ->get();
            $brands = DisplayOrderSettings::applyToCollection($brands, 'homepage_brands', fn (Brand $brand) => $brand->created_at);
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
            ]);
        $bundleDeals = DisplayOrderSettings::applyToQuery($bundleDeals, 'bundle_deals')
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

        $featuredCards = $featuredProducts->map($mapProduct)->values();
        $newArrivalCards = $newArrivals->map($mapProduct)->values();
        $bestSellerCards = $bestSellers->map($mapProduct)->values();
        $homepageSettings = LayoutCache::homepageSettings();

        // DISC-61/DISC-64: business JSON-LD plus the products (with the
        // prices their cards show) of every section a visitor can actually
        // see. DISC-76: its own title (matching Home.tsx's <Head>); the
        // image keeps PageSeo's site default.
        $shownCards = collect([
            'flash_sale' => collect($flashSaleData['items'] ?? [])->pluck('product'),
            'featured_products' => $featuredCards,
            'new_arrivals' => $newArrivalCards,
            'best_sellers' => $bestSellerCards,
        ])->filter(fn ($cards, string $section): bool => (bool) ($homepageSettings[$section]['is_visible'] ?? false))
            ->flatten(1)
            ->all();

        // DISC-87: the admin's SEO title/description (Admin › Homepage), else
        // the title above and the site default description.
        $seoSettings = Setting::whereIn('key', ['home_meta_title', 'home_meta_description', 'home_h1', 'home_intro'])->pluck('value', 'key');
        $seoTitle = self::filledText($seoSettings['home_meta_title'] ?? null) ?? 'Online Shopping in Bangladesh';

        PageSeo::share($seoTitle, self::filledText($seoSettings['home_meta_description'] ?? null), jsonLd: SiteJsonLd::forHome($shownCards));

        // IMG-F3: the hero slider opens on its first slide (Home.tsx); its
        // image, or a video's poster, is the page's largest early paint, but
        // only while the hero is the first visible section (an admin may
        // move it down the page, where a high-priority preload would compete
        // with what is really at the top).
        $openingSlide = $this->heroIsFirstSection($homepageSettings) ? (LayoutCache::heroSlides()[0] ?? null) : null;

        if ($openingSlide !== null && ImageVariants::publicPath($openingSlide['image'] ?? null) !== null) {
            PreloadImage::share($openingSlide['image'], $openingSlide['image_srcset'] ?? null, PreloadImage::HERO_SIZES);
        } else {
            PreloadImage::share($openingSlide['poster'] ?? null, $openingSlide['poster_srcset'] ?? null, PreloadImage::HERO_SIZES);
        }

        return Inertia::render('welcome', [
            'activeFlashSale' => $flashSaleData,
            'featuredProducts' => $featuredCards,
            'newArrivals' => $newArrivalCards,
            'bestSellers' => $bestSellerCards,
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
            'homepage_settings' => $homepageSettings,
            'hero_slides' => LayoutCache::heroSlides(),
            'promotional_banners' => LayoutCache::promotionalBanners(),
            'seo_title' => $seoTitle,
            'intro' => self::intro($seoSettings),
        ]);
    }

    /**
     * IMG-F3: whether the hero slider is the first of Home.tsx's ordered
     * section blocks that is switched on. Conservative: a visible section
     * that happens to render nothing (no active flash sale) still counts,
     * so the hero is then simply not preloaded.
     *
     * @param  array<string, mixed>  $homepageSettings
     */
    private function heroIsFirstSection(array $homepageSettings): bool
    {
        $orderedBlocks = ['hero_slider', 'flash_sale', 'categories', 'featured_products', 'promotional_banners', 'new_arrivals', 'brands', 'best_sellers', 'bundle_deals'];
        $first = collect($orderedBlocks)
            ->filter(fn (string $key): bool => (bool) ($homepageSettings[$key]['is_visible'] ?? false))
            ->sortBy(fn (string $key): int => (int) ($homepageSettings[$key]['sort_order'] ?? 0))
            ->first();

        return $first === 'hero_slider';
    }

    /**
     * DISC-90: the visible H1 and intro line at the top of the homepage
     * (Admin › Homepage). The heading always has text. The intro uses the
     * default until an admin saves one; saving it empty hides it (a cleared
     * field is stored as null, so the saved row is what tells the two
     * apart). Defaults claim only what the site already says elsewhere
     * (sections on the page, BDT prices, nationwide delivery in the trust
     * card).
     *
     * Admin › Homepage pre-fills its form with this, so saving it unchanged
     * keeps what visitors already see.
     *
     * @param  Collection<string, mixed>|null  $settings  Setting values by key; read here when not given
     * @return array{heading: string, text: string|null}
     */
    public static function intro(?Collection $settings = null): array
    {
        $settings ??= Setting::whereIn('key', ['home_h1', 'home_intro'])->pluck('value', 'key');
        $siteName = self::filledText(LayoutCache::settings()['site_name'] ?? null) ?? config('app.name');

        return [
            'heading' => self::filledText($settings['home_h1'] ?? null) ?? 'Online Shopping in Bangladesh',
            'text' => $settings->has('home_intro')
                ? self::filledText($settings['home_intro'])
                : "Shop new arrivals, best sellers, flash sales and bundle deals at {$siteName}, with prices in taka and delivery across Bangladesh.",
        ];
    }

    private static function filledText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
