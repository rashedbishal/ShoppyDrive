<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\StaleProductStockException;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * INV-STALE-STOCK-001: the admin product form submits absolute stock values,
 * while InventoryService changes stock underneath it for orders, cancellations
 * and returns. The edit page carries this signature of the stock it showed. It
 * holds no values and can't be forged, so a save proves it was based on the
 * stock that is still current, with no stored version to maintain. Stock that
 * changes and returns to the same values passes, because then nothing would be
 * overwritten.
 */
class ProductStockToken
{
    public static function for(Product $product): string
    {
        $stock = Product::query()->whereKey($product->id)->value('stock_quantity');
        $variantStock = ProductVariant::query()->where('product_id', $product->id)->orderBy('id')->pluck('stock_quantity', 'id')->all();

        return self::sign($product->id, (int) $stock, $variantStock);
    }

    /**
     * Locks the product and its variants, so no order can change stock
     * between this check and the save. Call inside the save's transaction.
     *
     * @throws StaleProductStockException
     */
    public static function lockAndVerify(Product $product, string $token): void
    {
        $stock = Product::query()->whereKey($product->id)->lockForUpdate()->value('stock_quantity');
        $variantStock = ProductVariant::query()->where('product_id', $product->id)->orderBy('id')->lockForUpdate()->pluck('stock_quantity', 'id')->all();

        if (! hash_equals(self::sign($product->id, (int) $stock, $variantStock), $token)) {
            throw new StaleProductStockException('Stock for this product changed after you opened this page, so nothing was saved. Reload the page and make your changes again.');
        }
    }

    /**
     * @param  array<int, int>  $variantStock
     */
    private static function sign(int $productId, int $stock, array $variantStock): string
    {
        return hash_hmac('sha256', json_encode(['product-stock', $productId, $stock, $variantStock]), (string) config('app.key'));
    }
}
