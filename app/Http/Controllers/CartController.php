<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Models\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request): Response
    {
        $cart = Cart::with('items.product')->where('session_id', session()->getId())->first();

        return Inertia::render('cart/index', [
            'cart' => $cart,
        ]);
    }

    public function store(AddToCartRequest $request): RedirectResponse
    {
        $cart = Cart::firstOrCreate(['session_id' => session()->getId()]);

        $cart->items()->updateOrCreate(
            ['product_id' => $request->product_id, 'variant_id' => $request->variant_id],
            ['quantity' => $request->quantity, 'price' => 10.00] // Placeholder price
        );

        return back()->with('success', 'Added to cart');
    }
}
