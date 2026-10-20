<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\HomepageSetting;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $activeFlashSale = FlashSale::with([
            'items.product.brand',
            'items.product.images',
        ])
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();

        if ($activeFlashSale) {
            foreach ($activeFlashSale->items as $item) {
                if ($item->product) {
                    $item->product->in_flash_sale = true;
                    $item->product->flash_sale_price = $item->sale_price;
                }
            }
        }

        $productEagerLoads = ['brand', 'images', 'category'];

        // Load CMS section settings for dynamic sections
        $sectionSettings = HomepageSetting::whereIn('section_key', ['new_arrivals', 'best_sellers', 'brands'])
            ->get()
            ->keyBy('section_key');

        $featuredProducts = Product::active()
            ->published()
            ->featured()
            ->with($productEagerLoads)
            ->withCount(['reviews as reviews_count'])
            ->withAvg('reviews as average_rating', 'rating')
            ->limit(8)
            ->get();

        $newArrivalsMeta = $sectionSettings->get('new_arrivals')?->metadata;
        $newArrivalIds = is_array($newArrivalsMeta) ? ($newArrivalsMeta['product_ids'] ?? []) : [];

        if (! empty($newArrivalIds)) {
            $newArrivals = Product::active()
                ->published()
                ->whereIn('id', $newArrivalIds)
                ->with($productEagerLoads)
                ->withCount(['reviews as reviews_count'])
                ->withAvg('reviews as average_rating', 'rating')
                ->get()
                ->sortBy(fn (Product $product) => array_search($product->id, $newArrivalIds))
                ->values();
        } else {
            $newArrivals = collect();
        }

        $bestSellersMeta = $sectionSettings->get('best_sellers')?->metadata;
        $bestSellerIds = is_array($bestSellersMeta) ? ($bestSellersMeta['product_ids'] ?? []) : [];

        if (! empty($bestSellerIds)) {
            $bestSellers = Product::active()
                ->published()
                ->whereIn('id', $bestSellerIds)
                ->with($productEagerLoads)
                ->withCount(['reviews as reviews_count'])
                ->withAvg('reviews as average_rating', 'rating')
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

        $categories = Category::active()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'image', 'icon', 'color', 'sort_order']);

        $currencySymbol = config('app.currency_symbol', '৳');

        $mapProduct = function (Product $product) use ($currencySymbol): array {
            $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
            $primaryImageUrl = $primaryImage?->path;

            $discountPercentage = null;
            if ($product->compare_price && $product->compare_price > $product->price) {
                $discountPercentage = (int) round((($product->compare_price - $product->price) / $product->compare_price) * 100);
            }

            return array_merge($product->toArray(), [
                'primary_image_url' => $primaryImageUrl,
                'discount_percentage' => $discountPercentage,
                'formatted_price' => $currencySymbol.number_format($product->price, 2),
                'formatted_compare_price' => $product->compare_price
                    ? $currencySymbol.number_format($product->compare_price, 2)
                    : null,
                'is_wishlisted' => false,
                'images' => ($product->images ?? collect())->toArray(),
                'media' => $product->images->toArray(),
                'videos' => [],
                'variants' => [],
                'attributes' => [],
                'tags' => [],
                'in_flash_sale' => $product->in_flash_sale ?? false,
                'flash_sale_price' => $product->flash_sale_price ?? null,
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
                'items' => $activeFlashSale->items
                    ->filter(fn ($item) => $item->product !== null)
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'flash_sale_id' => $item->flash_sale_id,
                        'product_id' => $item->product_id,
                        'sale_price' => $item->sale_price,
                        'stock_limit' => $item->stock_limit,
                        'sold_count' => $item->sold_count,
                        'product' => $mapFlashSaleProduct($item->product),
                    ])
                    ->values(),
            ];
        }

        return Inertia::render('welcome', [
            'activeFlashSale' => $flashSaleData,
            'featuredProducts' => $featuredProducts->map($mapProduct)->values(),
            'newArrivals' => $newArrivals->map($mapProduct)->values(),
            'bestSellers' => $bestSellers->map($mapProduct)->values(),
            'brands' => $brands,
            'categories' => $categories,
        ]);
    }
}
