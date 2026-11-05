<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class FlashSaleController extends Controller
{
    public function index(): Response
    {
        $activeSale = FlashSale::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with([
                // Excludes items whose product was deleted/deactivated since
                // being added to the sale — otherwise `item.product` would be
                // null and crash the frontend's product card rendering.
                'items' => static function ($query): void {
                    $query->whereHas('product', static fn ($productQuery) => $productQuery->visible())
                        ->with([
                            'product' => static function ($productQuery): void {
                                $productQuery->with(['brand', 'images', 'variants.media'])
                                    ->withCount([
                                        'reviews as reviews_count' => static fn ($q) => $q->where('is_approved', true),
                                    ])
                                    ->withAvg([
                                        'reviews as average_rating' => static fn ($q) => $q->where('is_approved', true),
                                    ], 'rating');
                            },
                        ]);
                },
            ])
            ->first();

        $upcomingSales = FlashSale::query()
            ->where('is_active', true)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(3)
            ->get();

        $featuredProducts = Product::query()
            ->visible()
            ->featured()
            ->with(['brand', 'images', 'variants.media'])
            ->withCount([
                'reviews as reviews_count' => static fn ($q) => $q->where('is_approved', true),
            ])
            ->withAvg([
                'reviews as average_rating' => static fn ($q) => $q->where('is_approved', true),
            ], 'rating')
            ->limit(8)
            ->get();

        // This page's own "Featured Products" section is a separate query
        // from $activeSale's items above, so a featured product that also
        // happens to be on the active sale (e.g. one the admin marked
        // featured specifically because it's on sale) never got the
        // sale's price here — the same instance-identity gap
        // HomeController had for its equivalent sections.
        $flashPrices = FlashSaleItem::activePricesForProducts($featuredProducts->pluck('id')->all());

        foreach ($featuredProducts as $product) {
            $flash = $flashPrices[$product->id] ?? null;
            $product->in_flash_sale = $flash !== null;
            $product->flash_sale_price = $flash['price'] ?? null;
            $product->flash_sale_ends_at = $flash['ends_at'] ?? null;
        }

        return Inertia::render('catalog/FlashSales', [
            'activeSale' => $activeSale,
            'upcomingSales' => $upcomingSales,
            'featuredProducts' => $featuredProducts,
        ]);
    }
}
