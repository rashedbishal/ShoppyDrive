<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\CartVersionConflictException;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\SyncCartRequest;
use App\Models\Wishlist;
use App\Support\CartManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartManager $cartManager) {}

    public function index(Request $request): Response
    {
        return Inertia::render('cart/Cart', [
            'cart' => $this->cartManager->serializeForRequest($request),
        ]);
    }

    public function store(AddToCartRequest $request): RedirectResponse
    {
        if ($request->user() !== null) {
            $this->cartManager->syncUserCart($request->user(), [[
                'product_id' => $request->integer('product_id'),
                'variant_id' => $request->integer('variant_id') ?: null,
                'quantity' => $request->integer('quantity'),
            ]], merge: true);

            return back()->with('success', 'Added to cart');
        }

        return back()->with('success', 'Added to cart');
    }

    /**
     * A full-cart save carrying `base_version` is refused with 409 and the
     * current cart when the cart changed since that version, so the device
     * can re-apply its own change on top instead of deleting lines it never
     * saw. The one-time login merge only adds, so it is never refused.
     */
    public function sync(SyncCartRequest $request): JsonResponse
    {
        $merge = $request->boolean('merge');

        try {
            $cart = $this->cartManager->syncUserCart(
                $request->user(),
                $request->validated('items'),
                $merge,
                expectedVersion: $merge ? null : $request->validated('base_version'),
            );
        } catch (CartVersionConflictException) {
            return response()->json([
                'conflict' => true,
                'cart' => $this->cartManager->serializeForRequest($request),
            ], 409);
        }

        return response()->json([
            'cart' => $this->cartManager->serialize($cart),
        ]);
    }

    /**
     * Polled by the storefront so a cart or wishlist changed on another
     * device shows up without a page load. Sends the cart only when its
     * version differs from the one the device already has; the wishlist is
     * just product ids (at most a few hundred), so those always come back.
     */
    public function state(Request $request): JsonResponse
    {
        $user = $request->user();
        $version = $this->cartManager->versionForUser($user);
        $wishlistIds = Wishlist::where('user_id', $user->id)->pluck('product_id')->all();

        if ($request->query('version') === $version) {
            return response()->json(['changed' => false, 'version' => $version, 'wishlist_ids' => $wishlistIds]);
        }

        $cart = $this->cartManager->serializeForRequest($request);

        return response()->json([
            'changed' => true,
            'version' => $cart['version'] ?? CartManager::EMPTY_VERSION,
            'cart' => $cart,
            'wishlist_ids' => $wishlistIds,
        ]);
    }
}
