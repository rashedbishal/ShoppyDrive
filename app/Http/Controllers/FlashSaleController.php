<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FlashSale;
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
                'items' => static function ($query): void {
                    $query->with([
                        'product' => static function ($productQuery): void {
                            $productQuery->with(['brand', 'images'])
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
            ->active()
            ->published()
            ->featured()
            ->with(['brand', 'images'])
            ->withCount([
                'reviews as reviews_count' => static fn ($q) => $q->where('is_approved', true),
            ])
            ->withAvg([
                'reviews as average_rating' => static fn ($q) => $q->where('is_approved', true),
            ], 'rating')
            ->limit(8)
            ->get();

        return Inertia::render('catalog/FlashSales', [
            'activeSale' => $activeSale,
            'upcomingSales' => $upcomingSales,
            'featuredProducts' => $featuredProducts,
        ]);
    }
}
