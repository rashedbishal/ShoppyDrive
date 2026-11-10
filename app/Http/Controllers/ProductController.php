<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Support\DisplayOrderSettings;
use App\Support\ProductCardData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Product $product): Response
    {
        // FSA-05: inactive, draft and future-scheduled products don't exist
        // as far as the storefront is concerned.
        abort_unless($product->isPubliclyVisible(), 404);

        $product->load([
            'category',
            'brand',
            'images',
            'media',
            // PERF-001: variants.media, not just variants — the appended
            // `image` accessor on ProductVariant lazy-loads its own media
            // relation during serialization otherwise.
            'variants.media',
            // Capped at the 50 most recent for display only — the
            // aggregates below are computed independently of this limit.
            'reviews' => fn ($q) => $q->where('is_approved', true)->with('user:id,name,avatar')->latest()->limit(50),
        ]);

        // FSA-18: the review list above is capped at 50 for display, but the
        // header rating and the Reviews tab count must reflect every
        // approved review. Same withCount()/withAvg() convention as
        // ProductListingController/HomeController/etc., just via
        // loadCount()/loadAvg() since $product is already resolved.
        $product->loadCount(['reviews as reviews_count' => fn ($q) => $q->where('is_approved', true)]);
        $product->loadAvg(['reviews as average_rating' => fn ($q) => $q->where('is_approved', true)], 'rating');

        // Build attributes array from product_attribute_values → attribute
        $attributeValues = $product->attributeValues()->with('attribute')->orderBy('attribute_values.sort_order')->get();
        $attributes = $attributeValues
            ->groupBy('attribute_id')
            ->map(function ($values) {
                $attribute = $values->first()->attribute;

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'type' => $attribute->type instanceof \BackedEnum ? $attribute->type->value : $attribute->type,
                    'values' => $values->map(fn ($v) => [
                        'id' => $v->id,
                        'attribute_id' => $v->attribute_id,
                        'value' => $v->value,
                        'color_hex' => $v->color_hex,
                        'sort_order' => $v->sort_order,
                        'created_at' => $v->created_at,
                        'updated_at' => $v->updated_at,
                    ])->values(),
                    'created_at' => $attribute->created_at,
                    'updated_at' => $attribute->updated_at,
                ];
            })
            ->values();

        // FSA-18: a `GROUP BY rating` over every approved review for this
        // product, independent of the 50-review display cap above — not
        // the capped $product->reviews collection, which previously made
        // this wrong (and silently drifting, since it's the 50 *most
        // recent*) for any product with more than 50 approved reviews.
        $approvedRatingCounts = $product->reviews()
            ->where('is_approved', true)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $ratingBreakdown = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $ratingBreakdown[$star] = (int) ($approvedRatingCounts[$star] ?? 0);
        }

        $relatedProductsQuery = Product::query()
            ->visible()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['images', 'brand', 'variants.media'])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('is_approved', true)], 'rating');
        $relatedProducts = DisplayOrderSettings::applyToQuery($relatedProductsQuery, 'related_products', ['published_at', 'created_at'])
            ->limit(8)
            ->get();

        // Same gap as the listing/search/brand/featured-products surfaces:
        // "related products" here is its own separate query from the main
        // product above and never got a flash-sale lookup at all.
        $relatedFlashPrices = FlashSaleItem::activePricesForProducts($relatedProducts->pluck('id')->all());

        foreach ($relatedProducts as $related) {
            $flash = $relatedFlashPrices[$related->id] ?? null;
            $related->in_flash_sale = $flash !== null;
            $related->flash_sale_price = $flash['price'] ?? null;
            $related->flash_sale_ends_at = $flash['ends_at'] ?? null;
        }

        // The single source of truth for "is this on flash sale" (same
        // method CartManager::resolvePrice() and InventoryService use) —
        // without this, the detail page always showed the regular price,
        // and Add to Cart/Buy Now priced from it. `findActiveFor()` already
        // eager-loads `flashSale`, so this is the same campaign row
        // HomeController's countdown reads `ends_at` from — not a second,
        // independently-guessed end time.
        //
        // The admin can target a whole product OR specific variant
        // combinations (see Admin\FlashSaleController), so each variant
        // gets its own resolution too: `findActiveFor($product->id,
        // $variant->id)` prefers that variant's own item, and — since its
        // query matches a null `variant_id` OR this one — falls back to the
        // product-wide item when no more specific one exists, so a plain
        // whole-product sale still correctly applies to every variant.
        //
        // PERF-001: resolved in one batch (findActiveForVariants() returns
        // exactly what findActiveFor() would for each) instead of two
        // queries per variant.
        $flashSaleItems = FlashSaleItem::findActiveForVariants($product->id, $product->variants->pluck('id')->all());
        $flashSaleItem = $flashSaleItems['product'];

        $variants = $product->variants
            ->map(function ($variant) use ($flashSaleItems) {
                $variantFlashItem = $flashSaleItems['variants'][$variant->id] ?? null;

                return array_merge($variant->toArray(), [
                    'in_flash_sale' => $variantFlashItem !== null,
                    'flash_sale_price' => $variantFlashItem?->sale_price,
                    'flash_sale_ends_at' => $variantFlashItem?->flashSale?->ends_at,
                ]);
            })
            ->values();

        // FE-006: SSR isn't built by deploy (shared host can't run the SSR
        // daemon), so Inertia's own <Head> only takes effect after the
        // client hydrates — curl, Facebook's scraper and search crawlers
        // see none of it. Shared directly into the Blade root view so the
        // first, unhydrated response already carries real tags.
        $imagePath = $product->primaryImagePath();
        $imageUrl = $imagePath ? (Str::startsWith($imagePath, ['http://', 'https://']) ? $imagePath : url($imagePath)) : null;
        $description = $product->meta_description ?: $product->short_description;

        View::share('seo', [
            'title' => $product->meta_title ?: $product->name,
            'description' => $description,
            'canonical' => route('product.show', $product->slug),
            'image' => $imageUrl,
            'type' => 'product',
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $description,
                'image' => $imageUrl ? [$imageUrl] : [],
                'offers' => [
                    '@type' => 'Offer',
                    'priceCurrency' => 'BDT',
                    'price' => (string) $product->price,
                    'availability' => $product->stock_quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ],
            ],
        ]);

        return Inertia::render('catalog/ProductDetail', [
            'product' => array_merge($product->toArray(), [
                'attributes' => $attributes,
                'images' => ($product->images ?? collect())->map(fn ($img) => array_merge($img->toArray(), ['is_primary' => $img->is_primary]))->values()->toArray(),
                'variants' => $variants,
                'in_flash_sale' => $flashSaleItem !== null,
                'flash_sale_price' => $flashSaleItem?->sale_price,
                'flash_sale_ends_at' => $flashSaleItem?->flashSale?->ends_at,
            ]),
            'relatedProducts' => $relatedProducts->map(ProductCardData::make(...))->values(),
            'reviews' => $product->reviews,
            'ratingBreakdown' => $ratingBreakdown,
            'canReview' => Auth::guard('web')->user()?->hasOrderedProduct($product->id) ?? false,
            'hasReviewed' => Auth::guard('web')->user()?->hasReviewedProduct($product->id) ?? false,
            'shippingMethods' => [],
        ]);
    }
}
