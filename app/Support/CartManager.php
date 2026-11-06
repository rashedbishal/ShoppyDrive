<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\CartChangedException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BundleService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * MED-08: every write to a cart's rows runs through `writeUserCart()` or
 * `writeSessionCart()`. `updateOrCreate()` reads and then inserts, and
 * `cart_items` cannot have a unique index because `variant_id` and `bundle_id`
 * are usually NULL and NULLs never collide, so the locks are what keep one row
 * per product, variant and bundle. Lock order is always user, then cart.
 */
class CartManager
{
    // items.product.category/subCategory/brand: isPubliclyVisible() reads
    // all three inside validItems()'s per-item filter — without this,
    // each cart item lazy-loads its own three relations separately.
    private const CART_RELATIONS = [
        'coupon', 'items.product.images', 'items.product.category', 'items.product.subCategory', 'items.product.brand',
        'items.variant.media', 'items.bundle',
    ];

    public function __construct(private readonly BundleService $bundles) {}

    public function getCurrentCart(Request $request): ?Cart
    {
        $query = Cart::query()->with(self::CART_RELATIONS);

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
        return $this->writeUserCart($user, function (Cart $userCart) use ($sessionId): void {
            $sessionCart = Cart::with(['items.product.images', 'items.variant.media', 'items.bundle'])
                ->where('session_id', $sessionId)
                ->whereNull('user_id')
                ->lockForUpdate()
                ->first();

            if ($sessionCart === null || $sessionCart->is($userCart)) {
                return;
            }

            $this->mergeItems($userCart, $sessionCart->items);

            $sessionCart->items()->delete();
            $sessionCart->delete();
        });
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:int|null, quantity:int, bundle_id?:int|null, bundle_unit_quantity?:int|null}>  $items
     */
    public function syncUserCart(User $user, array $items, bool $merge = false): Cart
    {
        return $this->writeUserCart($user, fn (Cart $cart) => $merge
            ? $this->mergeItemPayloads($cart, $items)
            : $this->replaceItems($cart, $items));
    }

    public function syncSessionCart(string $sessionId, array $items, bool $merge = false): Cart
    {
        return $this->writeSessionCart($sessionId, fn (Cart $cart) => $merge
            ? $this->mergeItemPayloads($cart, $items)
            : $this->replaceItems($cart, $items));
    }

    /**
     * The user row is the lock because the cart row may not exist yet, and
     * `carts.user_id` isn't unique. It must be the transaction's first
     * statement, so the plain reads after it see every earlier commit (see
     * LoyaltyPointsService::availablePoints()).
     *
     * @param  callable(Cart): mixed  $write
     */
    private function writeUserCart(User $user, callable $write): Cart
    {
        return DB::transaction(function () use ($user, $write): Cart {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $cart = $this->getOrCreateUserCart($user);
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $this->mergeDuplicateRows($cart);
            $write($cart);

            return $cart->fresh(self::CART_RELATIONS);
        }, 3);
    }

    /**
     * A guest has no user row to lock, so a guest's first two writes can each
     * create a cart. That leaves an unused cart, not a duplicated line. Once a
     * cart exists its row serializes writers. It may have been deleted by a
     * checkout finishing while this waited, hence the second attempt.
     *
     * @param  callable(Cart): mixed  $write
     */
    private function writeSessionCart(string $sessionId, callable $write): Cart
    {
        return DB::transaction(function () use ($sessionId, $write): Cart {
            $cart = $this->getOrCreateSessionCart($sessionId);

            if (Cart::query()->whereKey($cart->id)->lockForUpdate()->first() === null) {
                $cart = $this->getOrCreateSessionCart($sessionId);
            }

            $this->mergeDuplicateRows($cart);
            $write($cart);

            return $cart->fresh(self::CART_RELATIONS);
        }, 3);
    }

    /**
     * Collapses rows for the same product, variant and bundle into the oldest
     * one. Quantities are added so nothing is lost, capped at available stock,
     * but never below the largest single row: a customer keeps what they
     * could already see. Call with the cart locked.
     */
    private function mergeDuplicateRows(Cart $cart): void
    {
        $groups = $cart->items()->orderBy('id')->get()
            ->groupBy(fn (CartItem $item): string => $this->itemKey($item->product_id, $item->variant_id, $item->bundle_id))
            ->filter(fn (Collection $rows): bool => $rows->count() > 1);

        foreach ($groups as $rows) {
            $kept = $rows->first();
            $stock = $kept->variant_id !== null
                ? ProductVariant::query()->whereKey($kept->variant_id)->value('stock_quantity')
                : Product::query()->whereKey($kept->product_id)->value('stock_quantity');

            $cart->items()->whereKey($rows->slice(1)->pluck('id'))->delete();
            $kept->update(['quantity' => max((int) $rows->max('quantity'), min((int) $rows->sum('quantity'), (int) $stock))]);
        }
    }

    /**
     * Returns the cart with any duplicate rows collapsed, for a checkout that
     * didn't just write it, so one logical line can't become two order lines.
     */
    public function withoutDuplicateRows(Cart $cart): Cart
    {
        $hasDuplicates = $cart->items
            ->map(fn (CartItem $item): string => $this->itemKey($item->product_id, $item->variant_id, $item->bundle_id))
            ->duplicates()
            ->isNotEmpty();

        if (! $hasDuplicates) {
            return $cart;
        }

        return DB::transaction(function () use ($cart): Cart {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();
            $this->mergeDuplicateRows($cart);

            return $cart->fresh(self::CART_RELATIONS);
        }, 3);
    }

    /**
     * Checkout priced `$cart` before taking any lock. Call inside its order
     * transaction, right after locking the purchaser, to prove those rows are
     * still what would be charged. A guest has no purchaser lock, so the
     * cart's own row serializes them, and it comes first for the same reason.
     *
     * @throws CartChangedException
     */
    public function lockForCheckout(Cart $cart): void
    {
        $locked = Cart::query()->whereKey($cart->id)->lockForUpdate()->first();
        $snapshot = fn (Collection $items): array => $items
            ->mapWithKeys(fn (CartItem $item): array => [$item->id => [
                $item->product_id, $item->variant_id, $item->bundle_id, $item->quantity, round((float) $item->price, 2),
            ]])
            ->sortKeys()
            ->all();

        if ($locked === null) {
            throw new CartChangedException('Your cart changed while placing this order. Please review it and try again.');
        }

        // Same filter checkout priced with (validItems), so a row it
        // deliberately dropped — deleted, hidden (FSA-05) or a foreign
        // variant (FSA-01) — isn't mistaken for a concurrent change.
        $lockedItems = $this->validItems($locked->setRelation('items', $locked->items()->with(['product.category', 'product.subCategory', 'product.brand', 'variant'])->get()));

        if ($snapshot($cart->items) !== $snapshot($lockedItems)) {
            throw new CartChangedException('Your cart changed while placing this order. Please review it and try again.');
        }
    }

    /**
     * Cart items whose product still exists. A product can be soft-deleted
     * (or otherwise go missing) after being added to a cart; relying on
     * this everywhere cart items are read keeps a stale item from 500ing
     * checkout or the `cart` prop shared on every single page load.
     */
    public function validItems(Cart $cart): Collection
    {
        // FSA-01: a row pairing a product with another product's variant
        // (stored before the request-level check existed) is dropped like a
        // deleted product, so it can never be priced or checked out.
        // FSA-05: so is a product that's since been deactivated or
        // unpublished — checkout reads only these rows.
        return $cart->items
            ->filter(fn (CartItem $item): bool => $item->product !== null
                && $item->product->isPubliclyVisible()
                && ($item->variant === null || (int) $item->variant->product_id === (int) $item->product_id))
            ->values();
    }

    public function serialize(?Cart $cart): ?array
    {
        if ($cart === null) {
            return null;
        }

        $this->bundles->reconcile($cart);
        $this->bundles->autoAssemble($cart);

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
                'product' => ProductCardData::make($item->product),
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

        // FSA-01: resolved before the flash-sale lookup, which matches a
        // product-wide sale against any variant id — a variant of another
        // product must never be priced here at all.
        $variant = $variantId !== null
            ? ProductVariant::query()->whereKey($variantId)->where('product_id', $productId)->firstOrFail()
            : null;

        $flashItem = FlashSaleItem::findActiveFor($productId, $variantId);

        if ($flashItem !== null) {
            return (float) $flashItem->sale_price;
        }

        if ($variant !== null) {
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
        $changed = $this->bundles->autoAssemble($cart);

        foreach ($this->validItems($cart) as $item) {
            $current = round($this->resolvePrice($item->product_id, $item->variant_id, $item->bundle_id), 2);

            // MED-09: compared in integer poisha, not as floats, so
            // representation noise between two independently-computed
            // prices never reads as a real change (see ManualOrderPricing's
            // differs() for the same convention).
            if ((int) round((float) $item->price * 100) !== (int) round($current * 100)) {
                $item->update(['price' => $current]);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * BIZ-001: `replaceItems()` (called by `syncUserCart()`/`syncSessionCart()`
     * at the start of checkout submission, before this) always overwrites
     * every item's stored price to the current one — so by the time
     * `revalidatePrices()` runs afterward, it's comparing the current price
     * against itself and can never detect a change. This must run first,
     * against what the checkout page actually displayed, to catch a price
     * that changed (admin edit, flash sale starting/ending/selling out)
     * between page load and submission. Only items that include a `price`
     * are checked — every other order-creation path never submits one, so
     * this is a no-op for them; nothing here ever uses the submitted price
     * to decide what's charged, only whether to refuse before anything is
     * written.
     *
     * @param  array<int, array{product_id:int, variant_id?:int|null, bundle_id?:int|null, price?:float|int|string|null}>  $items
     */
    public function submittedPricesStale(array $items): bool
    {
        foreach ($items as $item) {
            if (! isset($item['price'])) {
                continue;
            }

            $current = round($this->resolvePrice(
                (int) $item['product_id'],
                isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                isset($item['bundle_id']) ? (int) $item['bundle_id'] : null,
            ), 2);

            // Same integer-poisha comparison convention as revalidatePrices().
            if ((int) round((float) $item['price'] * 100) !== (int) round($current * 100)) {
                return true;
            }
        }

        return false;
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
