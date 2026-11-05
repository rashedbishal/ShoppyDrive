<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Models\OrderItem;
use App\Models\Product;

/**
 * BIZ-011: one phone number may hold at most MAX_UNITS_PER_PRODUCT units of
 * the same product in cash-on-delivery orders that no courier has picked up
 * yet. A COD order costs nothing to place, so without this a single fake
 * order could mark a product out of stock until staff cancel it.
 *
 * "Not yet with a courier" (pending/processing, no Pathao or Steadfast
 * consignment) rather than "not SHIPPED": Steadfast orders stay PROCESSING
 * for the whole delivery (BIZ-007), and a parcel already handed over is
 * past the point of tying up stock with fake orders.
 *
 * Call inside the order-creation transaction, before stock is decremented.
 */
class PendingCodLimit
{
    public const MAX_UNITS_PER_PRODUCT = 10;

    /**
     * @param  array<int, int>  $quantitiesByProduct  product id => units in the new order
     *
     * @throws InsufficientStockException when any product would exceed the limit
     */
    public function assertWithinLimit(string $phone, array $quantitiesByProduct): void
    {
        if ($quantitiesByProduct === []) {
            return;
        }

        // Locked in id order so two concurrent orders for the same phone and
        // product are serialized: the second one counts the first's units.
        $products = Product::query()
            ->whereIn('id', array_keys($quantitiesByProduct))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('name', 'id');

        $heldUnits = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.payment_method', PaymentMethod::COD->value)
            ->whereIn('orders.status', [OrderStatus::PENDING->value, OrderStatus::PROCESSING->value])
            ->whereNull('orders.pathao_consignment_id')
            ->whereNull('orders.steadfast_consignment_id')
            ->where('orders.shipping_address->phone', $phone)
            ->whereIn('order_items.product_id', array_keys($quantitiesByProduct))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units')
            ->pluck('units', 'product_id');

        foreach ($quantitiesByProduct as $productId => $quantity) {
            $alreadyHeld = (int) ($heldUnits[$productId] ?? 0);

            if ($alreadyHeld + $quantity > self::MAX_UNITS_PER_PRODUCT) {
                throw InsufficientStockException::forPendingCodLimit(
                    (string) ($products[$productId] ?? 'this item'),
                    self::MAX_UNITS_PER_PRODUCT,
                    $alreadyHeld,
                );
            }
        }
    }
}
