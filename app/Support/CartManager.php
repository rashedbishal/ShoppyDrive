<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BundleService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CartManager
{
    public function __construct(private readonly BundleService $bundles) {}

    public function getCurrentCart(Request $request): ?Cart
    {
        $query = Cart::query()->with([
            'coupon',
            'items.product.images',
            'items.variant.media',
            'items.bundle',
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
        $sessionCart = Cart::with(['items.product.images', 'items.variant.media', 'items.bundle'])
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->first();

        if ($sessionCart === null || $sessionCart->is($userCart)) {
            return $userCart->loadMissing(['coupon', 'items.product.images', 'items.variant.media', 'items.bundle']);
        }

        $this->mergeItems($userCart, $sessionCart->items);

        $sessionCart->items()->delete();
        $sessionCart->delete();

        return $userCart->fresh(['coupon', 'items.product.images', 'items.variant.media', 'items.bundle']);
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int, bundle_id?:int|null, bundle_unit_quantity?:int|null}>  $items
     */
    public function syncUserCart(User $user, array $items, bool $merge = false): Cart
    {
        $cart = $this->getOrCreateUserCart($user);

        if ($merge) {
            $this->mergeItemPayloads($cart, $items);
        } else {
            $this->replaceItems($cart, $items);
        }

        return $cart->fresh(['coupon', 'items.product.images', 'items.variant.media', 'items.bundle']);
    }

    public function syncSessionCart(string $sessionId, array $items, bool $merge = false): Cart
    {
        $cart = $this->getOrCreateSessionCart($sessionId);

        if ($merge) {
            $this->mergeItemPayloads($cart, $items);
        } else {
            $this->replaceItems($cart, $items);
        }

        return $cart->fresh(['coupon', 'items.product.images', 'items.variant.media', 'items.bundle']);
    }

    /**
     * Cart items whose product still exists. A product can be soft-deleted
     * (or otherwise go missing) after being added to a cart; relying on
     * this everywhere cart items are read keeps a stale item from 500ing
     * checkout or the `cart` prop shared on every single page load.
     */
    public function validItems(Cart $cart): Collection
    {
        return $cart->items->filter(fn (CartItem $item): bool => $item->product !== null)->values();
    }

    public function serialize(?Cart $cart): ?array
    {
        if ($cart === null) {
            return null;
        }

        $this->bundles->reconcile($cart);

        $items = $this->validItems($cart);

        $subtotal = (float) $items->sum(fn (CartItem $item): float => (float) $item->price * $item->quantity);
        $discount = 0.0;
        $shippingEstimate = 0.0;
        $tax = 0.0;
        $total = max(0, $subtotal - $discount + $shippingEstimate + $tax);

        return [
            'items' => $items->map(fn (CartItem $item): array => [
                'id' => $item->id,
                'item_key' => $this->itemKey($item->product_id, $item->variant_id, $item->bundle_id),
                'product' => $item->product->toArray(),
                'variant' => $item->variant?->toArray(),
                'bundle_id' => $item->bundle_id,
                'bundle' => $item->bundle === null ? null : [
                    'id' => $item->bundle->id,
                    'name' => $item->bundle->name,
                    'image' => $item->bundle->image,
                    'discount_type' => $item->bundle->discount_type->value,
                ],
                'bundle_unit_quantity' => $item->bundle_unit_quantity,
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
            'item_count' => (int) $items->sum('quantity'),
            'formatted_subtotal' => $this->formatMoney($subtotal),
            'formatted_total' => $this->formatMoney($total),
        ];
    }

    public function serializeForRequest(Request $request): ?array
    {
        return $this->serialize($this->getCurrentCart($request));
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int, bundle_id?:int|null, bundle_unit_quantity?:int|null}>  $items
     */
    private function replaceItems(Cart $cart, array $items): void
    {
        $normalized = $this->normalizePayloads($items);
        $existingKeys = $cart->items()
            ->get(['id', 'product_id', 'variant_id', 'bundle_id'])
            ->mapWithKeys(fn (CartItem $item): array => [$this->itemKey($item->product_id, $item->variant_id, $item->bundle_id) => $item])
            ->all();

        foreach ($normalized as $payload) {
            $bundleId = $payload['bundle_id'] ?? null;
            $itemKey = $this->itemKey($payload['product_id'], $payload['variant_id'], $bundleId);
            $price = $this->resolvePrice($payload['product_id'], $payload['variant_id'], $bundleId);

            $cart->items()->updateOrCreate(
                [
                    'product_id' => $payload['product_id'],
                    'variant_id' => $payload['variant_id'],
                    'bundle_id' => $bundleId,
                ],
                [
                    'quantity' => $payload['quantity'],
                    'price' => $price,
                    'bundle_unit_quantity' => $payload['bundle_unit_quantity'] ?? null,
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
            'bundle_id' => $item->bundle_id,
            'bundle_unit_quantity' => $item->bundle_unit_quantity,
        ])->all();

        $this->mergeItemPayloads($cart, $payloads);
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int, bundle_id?:int|null, bundle_unit_quantity?:int|null}>  $items
     */
    private function mergeItemPayloads(Cart $cart, array $items): void
    {
        foreach ($this->normalizePayloads($items) as $payload) {
            $bundleId = $payload['bundle_id'] ?? null;

            $existingItem = $cart->items()
                ->where('product_id', $payload['product_id'])
                ->where('variant_id', $payload['variant_id'])
                ->where('bundle_id', $bundleId)
                ->first();

            $quantity = $payload['quantity'] + ($existingItem?->quantity ?? 0);
            $price = $this->resolvePrice($payload['product_id'], $payload['variant_id'], $bundleId);

            $cart->items()->updateOrCreate(
                [
                    'product_id' => $payload['product_id'],
                    'variant_id' => $payload['variant_id'],
                    'bundle_id' => $bundleId,
                ],
                [
                    'quantity' => $quantity,
                    'price' => $price,
                    'bundle_unit_quantity' => $payload['bundle_unit_quantity'] ?? null,
                ],
            );
        }
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int, bundle_id?:int|null, bundle_unit_quantity?:int|null}>  $items
     * @return array<int, array{product_id:int, variant_id:int|null, quantity:int, bundle_id?:int|null, bundle_unit_quantity?:int|null}>
     */
    private function normalizePayloads(array $items): array
    {
        $normalized = [];

        foreach ($items as $payload) {
            $itemKey = $this->itemKey($payload['product_id'], $payload['variant_id'], $payload['bundle_id'] ?? null);

            if (! isset($normalized[$itemKey])) {
                $normalized[$itemKey] = $payload;

                continue;
            }

            $normalized[$itemKey]['quantity'] += $payload['quantity'];
        }

        return array_values($normalized);
    }

    /**
     * The single authoritative price for a product/variant — this is the
     * only place in the app that decides what a customer pays, and every
     * cart write, checkout revalidation, and order-item snapshot goes
     * through it. A currently-active, non-exhausted flash sale always
     * wins over the ordinary price (never a client-submitted one — no
     * request anywhere accepts a price field).
     *
     * When `$bundleId` is given, an active fixed-price bundle's prorated
     * price wins over everything else — bundle pricing is not meant to
     * stack with a flash sale on the same product. A free-delivery bundle
     * has no price override, so this falls through to the normal lookup
     * below (including flash sale) exactly as if `$bundleId` were null.
     */
    public function resolvePrice(int $productId, ?int $variantId, ?int $bundleId = null): float
    {
        if ($bundleId !== null) {
            $bundlePrice = $this->bundles->unitPriceFor($bundleId, $productId, $variantId);

            if ($bundlePrice !== null) {
                return $bundlePrice;
            }
        }

        $flashItem = FlashSaleItem::findActiveFor($productId, $variantId);

        if ($flashItem !== null) {
            return (float) $flashItem->sale_price;
        }

        if ($variantId !== null) {
            $variant = ProductVariant::query()->findOrFail($variantId);

            if ($variant->price !== null) {
                return (float) $variant->price;
            }
        }

        $product = Product::query()->findOrFail($productId);

        return (float) $product->price;
    }

    /**
     * Re-resolves every item's price against current flash-sale/catalog
     * state and persists any change. Must run at the start of checkout
     * submission, before totals are computed — a cart item's price is
     * otherwise a snapshot frozen at the last add/sync, which can go stale
     * (a sale starts, ends, or exhausts its cap) between then and when the
     * customer actually submits the order. Returns true if any item's
     * price changed, so the caller can refuse to silently charge a
     * different amount than what the checkout page showed.
     */
    public function revalidatePrices(Cart $cart): bool
    {
        $this->bundles->reconcile($cart);

        $changed = false;

        foreach ($this->validItems($cart) as $item) {
            $current = round($this->resolvePrice($item->product_id, $item->variant_id, $item->bundle_id), 2);

            if (round((float) $item->price, 2) !== $current) {
                $item->update(['price' => $current]);
                $changed = true;
            }
        }

        return $changed;
    }

    private function itemKey(int $productId, ?int $variantId, ?int $bundleId = null): string
    {
        $key = $variantId !== null ? "variant:{$variantId}" : "product:{$productId}";

        return $bundleId !== null ? "bundle:{$bundleId}:{$key}" : $key;
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2);
    }
}
