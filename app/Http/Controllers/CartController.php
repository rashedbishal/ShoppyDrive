<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\SyncCartRequest;
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
        return Inertia::render('cart/index', [
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

    public function sync(SyncCartRequest $request): JsonResponse
    {
        $cart = $this->cartManager->syncUserCart(
            $request->user(),
            $request->validated('items'),
            $request->boolean('merge'),
        );

        return response()->json([
            'cart' => $this->cartManager->serialize($cart),
        ]);
    }
}
