<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\BangladeshiPhone;
use App\Support\ProductSearchQuery;

/**
 * The only things the AI assistant is allowed to do. Each method here is
 * the single place its corresponding tool's logic lives — the model never
 * touches the database directly, only ever gets back exactly what one of
 * these methods decides to return, under the same visibility/ownership
 * rules the rest of the storefront already enforces.
 */
class AiAssistantTools
{
    /**
     * @return array<string, mixed>
     */
    public function searchProducts(string $query): array
    {
        $products = Product::query()
            ->visible()
            ->where(fn ($q) => ProductSearchQuery::apply($q, $query))
            ->limit(5)
            ->get(['id', 'name', 'slug', 'price', 'compare_price', 'short_description']);

        if ($products->isEmpty()) {
            return ['found' => false, 'message' => 'No matching products found.'];
        }

        $flashPrices = FlashSaleItem::activePricesForProducts($products->pluck('id')->all());

        return [
            'found' => true,
            'products' => $products->map(function (Product $p) use ($flashPrices) {
                $flash = $flashPrices[$p->id] ?? null;

                return [
                    'name' => $p->name,
                    'url' => route('product.show', $p->slug),
                    'price' => (float) $p->price,
                    'compare_price' => $p->compare_price ? (float) $p->compare_price : null,
                    'on_flash_sale' => $flash !== null,
                    'flash_sale_price' => $flash['price'] ?? null,
                    'short_description' => $p->short_description,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkProductAvailability(string $productName): array
    {
        $product = Product::query()
            ->visible()
            ->where(fn ($q) => ProductSearchQuery::apply($q, $productName, includeShortDescription: false))
            ->with(['variants' => fn ($q) => $q->select(['id', 'product_id', 'price', 'stock', 'attributes'])])
            ->first(['id', 'name', 'slug', 'price', 'stock']);

        if ($product === null) {
            return ['found' => false, 'message' => 'No product found with that name.'];
        }

        if ($product->variants->isEmpty()) {
            return [
                'found' => true,
                'name' => $product->name,
                'url' => route('product.show', $product->slug),
                'price' => (float) $product->price,
                'in_stock' => $product->available_stock > 0,
                'stock_count' => $product->available_stock,
            ];
        }

        return [
            'found' => true,
            'name' => $product->name,
            'url' => route('product.show', $product->slug),
            'has_variants' => true,
            'variants' => $product->variants->map(fn ($v) => [
                'attributes' => $v->attributes,
                'price' => (float) $v->price,
                'in_stock' => $v->stock > 0,
                'stock_count' => $v->stock,
            ])->values()->all(),
        ];
    }

    /**
     * Guests must prove ownership with the order number plus the email or
     * phone on file — the exact same check OrderTrackingController uses.
     * A logged-in customer skips that proof entirely but can only ever
     * reach their own orders, never anyone else's, via $authUser below.
     *
     * @return array<string, mixed>
     */
    public function lookupOrder(?User $authUser, ?string $orderNumber, ?string $email, ?string $phone): array
    {
        if ($authUser !== null) {
            $query = Order::where('user_id', $authUser->id);

            $order = $orderNumber !== null
                ? $query->where('order_number', $orderNumber)->first()
                : $query->latest()->first();

            if ($order === null) {
                return ['found' => false, 'message' => $orderNumber !== null ? 'No order with that number found on this account.' : 'No orders found on this account yet.'];
            }

            return $this->orderPayload($order);
        }

        if ($orderNumber === null || ($email === null && $phone === null)) {
            return ['found' => false, 'message' => 'Ask the customer for their order number plus the email or phone used at checkout.'];
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if ($order === null) {
            return ['found' => false, 'message' => 'No order found with that order number.'];
        }

        if (! $this->matchesContact($order, $email, $phone)) {
            return ['found' => false, 'message' => "The email or phone doesn't match our records for this order number. Never reveal order details without a match."];
        }

        return $this->orderPayload($order);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
    {
        return [
            'found' => true,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'placed_at' => $order->created_at?->toDateString(),
            'total' => (float) $order->total,
            'items' => $order->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
            ])->values()->all(),
        ];
    }

    private function matchesContact(Order $order, ?string $email, ?string $phone): bool
    {
        $onFileEmail = $order->shipping_address['email'] ?? '';
        if ($email && $onFileEmail !== '' && strtolower($onFileEmail) === strtolower($email)) {
            return true;
        }

        $onFilePhone = $order->shipping_address['phone'] ?? '';
        if ($phone && $onFilePhone !== '' && $this->normalizePhone($onFilePhone) === $this->normalizePhone($phone)) {
            return true;
        }

        return false;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) BangladeshiPhone::normalize($phone)) ?? '';

        return substr($digits, -10);
    }
}
