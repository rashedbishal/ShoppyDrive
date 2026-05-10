<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    public function index(Request $request): Response
    {
        $items = Wishlist::where('user_id', $request->user()->id)
            ->with(['product' => fn ($q) => $q->with(['images', 'brand', 'category'])])
            ->get();

        $currencySymbol = config('app.currency_symbol', '৳');

        $products = $items
            ->filter(fn ($item) => $item->product !== null)
            ->map(function ($item) use ($currencySymbol) {
                $product = $item->product;
                $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

                $discountPercentage = null;
                if ($product->compare_price && $product->compare_price > $product->price) {
                    $discountPercentage = (int) round((($product->compare_price - $product->price) / $product->compare_price) * 100);
                }

                return array_merge($product->toArray(), [
                    'primary_image_url' => $primaryImage?->path,
                    'discount_percentage' => $discountPercentage,
                    'formatted_price' => $currencySymbol.number_format($product->price, 2),
                    'formatted_compare_price' => $product->compare_price
                        ? $currencySymbol.number_format($product->compare_price, 2)
                        : null,
                    'is_wishlisted' => true,
                    'images' => $product->images->toArray(),
                    'media' => $product->images->toArray(),
                    'videos' => [],
                    'variants' => [],
                    'attributes' => [],
                    'tags' => [],
                    'in_flash_sale' => false,
                    'flash_sale_price' => null,
                    'reviews_count' => 0,
                    'average_rating' => null,
                ]);
            })
            ->values();

        return Inertia::render('account/Wishlist', [
            'wishlist' => $products,
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $userId = $request->user()->id;
        $productId = $request->integer('product_id');

        $existing = Wishlist::where('user_id', $userId)->where('product_id', $productId)->first();

        if ($existing) {
            $existing->delete();
            $wishlisted = false;
        } else {
            Wishlist::create(['user_id' => $userId, 'product_id' => $productId]);
            $wishlisted = true;
        }

        $wishlistIds = Wishlist::where('user_id', $userId)->pluck('product_id')->toArray();

        return response()->json([
            'wishlisted' => $wishlisted,
            'wishlist_ids' => $wishlistIds,
        ]);
    }

    public function clear(Request $request): \Illuminate\Http\RedirectResponse
    {
        Wishlist::where('user_id', $request->user()->id)->delete();
        
        return back();
    }
}
