<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CartManager
{
    public function getCurrentCart(Request $request): ?Cart
    {
        $query = Cart::query()->with([
            'coupon',
            'items.product.images',
            'items.variant.media',
        ]);

        if ($request->user() !== null) {
            return $query->where('user_id', $request->user()->id)->first();
        }

        try {
            return $query->where('session_id', $request->session()->getId())->first();
        } catch (\RuntimeException) {
            return null;
        }
    }

    public function getOrCreateUserCart(User $user): Cart
    {
        $sessionId = null;

        try {
            $sessionId = request()->session()->getId();
        } catch (\RuntimeException) {
            // Session not set, likely in a test or console command
        }

        return Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['session_id' => $sessionId],
        );
    }

    public function getOrCreateSessionCart(string $sessionId): Cart
    {
        return Cart::firstOrCreate(
            ['session_id' => $sessionId, 'user_id' => null]
        );
    }

    public function mergeSessionCartIntoUserCart(User $user, string $sessionId): Cart
    {
        $userCart = $this->getOrCreateUserCart($user);
        $sessionCart = Cart::with(['items.product.images', 'items.variant.media'])
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->first();

        if ($sessionCart === null || $sessionCart->is($userCart)) {
            return $userCart->loadMissing(['coupon', 'items.product.images', 'items.variant.media']);
        }

        $this->mergeItems($userCart, $sessionCart->items);

        $sessionCart->items()->delete();
        $sessionCart->delete();

        return $userCart->fresh(['coupon', 'items.product.images', 'items.variant.media']);
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int}>  $items
     */
    public function syncUserCart(User $user, array $items, bool $merge = false): Cart
    {
        $cart = $this->getOrCreateUserCart($user);

        if ($merge) {
            $this->mergeItemPayloads($cart, $items);
        } else {
            $this->replaceItems($cart, $items);
        }

        return $cart->fresh(['coupon', 'items.product.images', 'items.variant.media']);
    }

    public function syncSessionCart(string $sessionId, array $items, bool $merge = false): Cart
    {
        $cart = $this->getOrCreateSessionCart($sessionId);

        if ($merge) {
            $this->mergeItemPayloads($cart, $items);
        } else {
            $this->replaceItems($cart, $items);
        }

        return $cart->fresh(['coupon', 'items.product.images', 'items.variant.media']);
    }

    public function serialize(?Cart $cart): ?array
    {
        if ($cart === null) {
            return null;
        }

        $subtotal = (float) $cart->items->sum(fn (CartItem $item): float => (float) $item->price * $item->quantity);
        $discount = 0.0;
        $shippingEstimate = 0.0;
        $tax = 0.0;
        $total = max(0, $subtotal - $discount + $shippingEstimate + $tax);

        return [
            'items' => $cart->items->map(fn (CartItem $item): array => [
                'id' => $item->id,
                'item_key' => $this->itemKey($item->product_id, $item->variant_id),
                'product' => $item->product->toArray(),
                'variant' => $item->variant?->toArray(),
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'formatted_price' => $this->formatMoney((float) $item->price),
                'subtotal' => (float) $item->price * $item->quantity,
                'formatted_subtotal' => $this->formatMoney((float) $item->price * $item->quantity),
            ])->values()->all(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_estimate' => $shippingEstimate,
            'tax' => $tax,
            'total' => $total,
            'coupon' => $cart->coupon?->toArray(),
            'item_count' => (int) $cart->items->sum('quantity'),
            'formatted_subtotal' => $this->formatMoney($subtotal),
            'formatted_total' => $this->formatMoney($total),
        ];
    }

    public function serializeForRequest(Request $request): ?array
    {
        return $this->serialize($this->getCurrentCart($request));
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int}>  $items
     */
    private function replaceItems(Cart $cart, array $items): void
    {
        $normalized = $this->normalizePayloads($items);
        $existingKeys = $cart->items()
            ->get(['id', 'product_id', 'variant_id'])
            ->mapWithKeys(fn (CartItem $item): array => [$this->itemKey($item->product_id, $item->variant_id) => $item])
            ->all();

        foreach ($normalized as $payload) {
            $itemKey = $this->itemKey($payload['product_id'], $payload['variant_id']);
            $price = $this->resolvePrice($payload['product_id'], $payload['variant_id']);

            $cart->items()->updateOrCreate(
                [
                    'product_id' => $payload['product_id'],
                    'variant_id' => $payload['variant_id'],
                ],
                [
                    'quantity' => $payload['quantity'],
                    'price' => $price,
                ],
            );

            unset($existingKeys[$itemKey]);
        }

        if ($existingKeys !== []) {
            $cart->items()->whereKey(array_map(fn (CartItem $item): int => $item->id, $existingKeys))->delete();
        }
    }

    /**
     * @param  Collection<int, CartItem>  $items
     */
    private function mergeItems(Cart $cart, Collection $items): void
    {
        $payloads = $items->map(fn (CartItem $item): array => [
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'quantity' => $item->quantity,
        ])->all();

        $this->mergeItemPayloads($cart, $payloads);
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int}>  $items
     */
    private function mergeItemPayloads(Cart $cart, array $items): void
    {
        foreach ($this->normalizePayloads($items) as $payload) {
            $existingItem = $cart->items()
                ->where('product_id', $payload['product_id'])
                ->where('variant_id', $payload['variant_id'])
                ->first();

            $quantity = $payload['quantity'] + ($existingItem?->quantity ?? 0);
            $price = $this->resolvePrice($payload['product_id'], $payload['variant_id']);

            $cart->items()->updateOrCreate(
                [
                    'product_id' => $payload['product_id'],
                    'variant_id' => $payload['variant_id'],
                ],
                [
                    'quantity' => $quantity,
                    'price' => $price,
                ],
            );
        }
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int}>  $items
     * @return array<int, array{product_id:int, variant_id:int|null, quantity:int}>
     */
    private function normalizePayloads(array $items): array
    {
        $normalized = [];

        foreach ($items as $payload) {
            $itemKey = $this->itemKey($payload['product_id'], $payload['variant_id']);

            if (! isset($normalized[$itemKey])) {
                $normalized[$itemKey] = $payload;

                continue;
            }

            $normalized[$itemKey]['quantity'] += $payload['quantity'];
        }

        return array_values($normalized);
    }

    private function resolvePrice(int $productId, ?int $variantId): float
    {
        if ($variantId !== null) {
            $variant = ProductVariant::query()->findOrFail($variantId);

            if ($variant->price !== null) {
                return (float) $variant->price;
            }
        }

        $product = Product::query()->findOrFail($productId);

        return (float) $product->price;
    }

    private function itemKey(int $productId, ?int $variantId): string
    {
        return $variantId !== null ? "variant:{$variantId}" : "product:{$productId}";
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2);
    }
}
