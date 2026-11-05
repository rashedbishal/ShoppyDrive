<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Support\CartManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    public function __construct(private readonly CartManager $cartManager) {}

    /**
     * The public wishlist link. A guest's wishlist only exists in their
     * browser, so they get a page that can read it there; everyone else goes
     * to their real saved items at `account.wishlist`.
     */
    public function entry(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('account.wishlist');
        }

        return Inertia::render('account/GuestWishlist');
    }

    public function index(Request $request): Response
    {
        $items = Wishlist::where('user_id', $request->user()->id)
            // PERF-001: variants.media — ProductVariant's appended `image`
            // accessor otherwise lazy-loads it per variant on serialization.
            ->with(['product' => fn ($q) => $q->with(['images', 'brand', 'category', 'subCategory', 'variants.media'])])
            ->get();

        $currencySymbol = config('app.currency_symbol', '৳');

        // Same gap as every other storefront listing surface: this was
        // hardcoded to false/null unconditionally, so a wishlisted product
        // on an active flash sale always showed its regular price here.
        $flashPrices = FlashSaleItem::activePricesForProducts(
            $items->filter(fn ($item) => $item->product !== null)->pluck('product.id')->all(),
        );

        $products = $items
            ->filter(fn ($item) => $item->product !== null && $item->product->isPubliclyVisible())
            ->map(function ($item) use ($currencySymbol, $flashPrices) {
                $product = $item->product;
                $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

                $discountPercentage = null;
                if ($product->compare_price && $product->compare_price > $product->price) {
                    $discountPercentage = (int) round((($product->compare_price - $product->price) / $product->compare_price) * 100);
                }

                $flash = $flashPrices[$product->id] ?? null;

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
                    'attributes' => [],
                    'tags' => [],
                    'in_flash_sale' => $flash !== null,
                    'flash_sale_price' => $flash['price'] ?? null,
                    'flash_sale_ends_at' => $flash['ends_at'] ?? null,
                    'reviews_count' => 0,
                    'average_rating' => null,
                ]);
            })
            ->values();

        return Inertia::render('account/Wishlist', [
            'wishlist' => $products,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer', 'exists:products,id'],
        ]);

        $userId = $request->user()->id;

        foreach ($request->input('ids', []) as $productId) {
            Wishlist::firstOrCreate(['user_id' => $userId, 'product_id' => $productId]);
        }

        $wishlistIds = Wishlist::where('user_id', $userId)->pluck('product_id')->toArray();

        return response()->json(['wishlist_ids' => $wishlistIds]);
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

    /**
     * Adds what can be bought as it stands. A variant product needs the
     * customer to choose a size or colour, and a deleted or unlisted product
     * can't be bought, so both stay on the wishlist and are reported as
     * skipped. Only rows really added leave it, in one transaction, so a failed
     * cart write loses nothing. The user lock makes a double submit find
     * nothing left to add.
     */
    public function addAllToCart(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        [$cart, $added, $needsOptions, $unavailable] = DB::transaction(function () use ($user): array {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $productIds = Wishlist::where('user_id', $user->id)->pluck('product_id');
            $buyable = Product::query()->visible()->whereKey($productIds)->withExists('variants')->get();
            $addable = $buyable->where('variants_exists', false)->pluck('id');

            $cart = null;

            if ($addable->isNotEmpty()) {
                $cart = $this->cartManager->syncUserCart($user, $addable->map(fn (int $productId): array => [
                    'product_id' => $productId,
                    'variant_id' => null,
                    'quantity' => 1,
                ])->all(), merge: true);

                Wishlist::where('user_id', $user->id)->whereIn('product_id', $addable)->delete();
            }

            return [$cart, $addable->count(), $buyable->count() - $addable->count(), $productIds->count() - $buyable->count()];
        });

        $message = $this->addAllMessage($added, $needsOptions, $unavailable);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'added' => $added,
                'skipped_needs_options' => $needsOptions,
                'skipped_unavailable' => $unavailable,
                'cart' => $cart === null ? null : $this->cartManager->serialize($cart),
            ]);
        }

        return back()->with($added > 0 && $needsOptions + $unavailable === 0 ? 'success' : 'info', $message);
    }

    private function addAllMessage(int $added, int $needsOptions, int $unavailable): string
    {
        if ($added + $needsOptions + $unavailable === 0) {
            return 'Your wishlist is empty.';
        }

        if ($needsOptions + $unavailable === 0) {
            return 'All items added to cart';
        }

        return collect([
            $added > 0 ? $added.' '.Str::plural('item', $added).' added to your cart.' : null,
            $needsOptions === 1 ? '1 item needs a size or colour chosen on the product page, so it stayed on your wishlist.' : null,
            $needsOptions > 1 ? "{$needsOptions} items need a size or colour chosen on the product page, so they stayed on your wishlist." : null,
            $unavailable === 1 ? '1 item is no longer available, so it was not added.' : null,
            $unavailable > 1 ? "{$unavailable} items are no longer available, so they were not added." : null,
        ])->filter()->implode(' ');
    }

    public function clear(Request $request): RedirectResponse
    {
        Wishlist::where('user_id', $request->user()->id)->delete();

        return back();
    }
}
