<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PlaceOrderRequest;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function index(): Response
    {
        $cart = Cart::with('items.product')->where('session_id', session()->getId())->first();

        return Inertia::render('checkout/index', [
            'cart' => $cart,
        ]);
    }

    public function store(PlaceOrderRequest $request): RedirectResponse
    {
        $order = DB::transaction(function () use ($request) {
            // Simplified order creation logic for now
            return Order::create([
                'order_number' => 'ORD-'.time(),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $request->payment_method,
                'subtotal' => 100.00,
                'total' => 100.00,
                'shipping_address' => $request->only(['name', 'address_line1', 'city', 'state', 'postal_code']),
                'billing_address' => $request->only(['name', 'address_line1', 'city', 'state', 'postal_code']),
            ]);
        });

        return redirect()->route('order.confirmation', $order->id);
    }
}
