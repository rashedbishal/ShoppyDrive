<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TrackIncompleteOrderRequest;
use App\Models\IncompleteOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;

class IncompleteOrderController extends Controller
{
    /**
     * Called from the checkout page while a guest/customer has a valid
     * 11-digit phone number entered and items to order, but hasn't placed
     * the order yet. Upserts by `attempt_token` (one row per checkout page
     * visit) so repeated edits within the same visit don't pile up rows —
     * the matching row is deleted once a real order is placed with the same
     * phone number (see CheckoutController::store and
     * OrderService::createFromPending).
     */
    public function store(TrackIncompleteOrderRequest $request): JsonResponse
    {
        $items = collect($request->validated('items'))
            ->map(function (array $item): ?array {
                $product = Product::with('images')->find($item['product_id']);

                if (! $product) {
                    return null;
                }

                $variant = $item['variant_id'] ?? null
                    ? ProductVariant::with('media')->find($item['variant_id'])
                    : null;

                $price = (float) ($variant?->price ?? $product->price);
                $quantity = (int) $item['quantity'];

                return [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $price * $quantity,
                    'image' => $variant?->image ?? $product->primaryImagePath(),
                    'variant_attributes' => $variant?->attributes,
                ];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return response()->json(['status' => 'ignored']);
        }

        IncompleteOrder::updateOrCreate(
            ['attempt_token' => $request->validated('attempt_token')],
            [
                'user_id' => $request->user()?->id,
                'name' => $request->validated('name') ?: null,
                'phone' => $request->validated('phone'),
                'items' => $items->all(),
                'subtotal' => $items->sum('subtotal'),
            ],
        );

        return response()->json(['status' => 'tracked']);
    }
}
