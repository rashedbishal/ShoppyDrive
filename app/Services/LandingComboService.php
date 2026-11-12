<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LandingPage;
use App\Models\Product;

/**
 * Landing-page combos: a named set of the promoted product's own variants
 * (e.g. "Couple set" = Bubu + Dudu), saved in the page's `sections.combos`.
 *
 * A combo is never a stock-keeping unit of its own — ordering one is expanded
 * into ordinary order lines, one per component variant, so stock is always
 * deducted from (and restored to) the real variants and can't be double-sold.
 * Its price is either the live sum of its parts or an admin-set fixed price,
 * which is then split across those lines so the order total matches exactly.
 */
class LandingComboService
{
    /**
     * Saved combos that can still be sold: every component must still be a
     * variant of this product. Keyed by combo id.
     *
     * @return array<string, array{id: string, name: string, description: string, badge: string, price: float|null, items: list<array{variant_id: int, quantity: int}>}>
     */
    public function definitions(LandingPage $page, Product $product): array
    {
        $variantIds = $product->variants->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $definitions = [];

        foreach ($page->getSections()['combos'] ?? [] as $combo) {
            $items = collect($combo['items'] ?? [])
                ->map(fn ($item): array => ['variant_id' => (int) ($item['variant_id'] ?? 0), 'quantity' => max(1, (int) ($item['quantity'] ?? 1))])
                ->values()
                ->all();

            $id = (string) ($combo['id'] ?? '');

            if ($id === '' || $items === [] || ! empty(array_diff(array_column($items, 'variant_id'), $variantIds))) {
                continue;
            }

            $definitions[$id] = [
                'id' => $id,
                'name' => (string) ($combo['name'] ?? ''),
                'description' => (string) ($combo['description'] ?? ''),
                'badge' => (string) ($combo['badge'] ?? ''),
                'price' => isset($combo['price']) && $combo['price'] !== '' && $combo['price'] !== null ? round((float) $combo['price'], 2) : null,
                'items' => $items,
            ];
        }

        return $definitions;
    }

    /**
     * What the storefront shows for each combo: its live price, the price of
     * buying the parts separately, and how many sets are in stock.
     *
     * @param  array<int, float>  $variantPrices  variant id => price charged today
     * @return list<array<string, mixed>>
     */
    public function forDisplay(LandingPage $page, Product $product, array $variantPrices): array
    {
        $variants = $product->variants->keyBy('id');

        return collect($this->definitions($page, $product))->map(function (array $combo) use ($variants, $variantPrices): array {
            $partsTotal = 0.0;
            $stock = PHP_INT_MAX;
            $parts = [];

            foreach ($combo['items'] as $item) {
                $variant = $variants[$item['variant_id']];
                $partsTotal += ($variantPrices[$variant->id] ?? 0.0) * $item['quantity'];
                $stock = min($stock, intdiv(max(0, (int) $variant->stock_quantity), $item['quantity']));
                $parts[] = [
                    'variant_id' => (int) $variant->id,
                    'quantity' => $item['quantity'],
                    'attributes' => $variant->attributes,
                    'image' => $variant->image,
                ];
            }

            $partsTotal = round($partsTotal, 2);
            $price = $combo['price'] ?? $partsTotal;

            return [
                'id' => $combo['id'],
                'name' => $combo['name'],
                'description' => $combo['description'],
                'badge' => $combo['badge'],
                'price' => $price,
                'compare_price' => $partsTotal > $price ? $partsTotal : null,
                'stock_quantity' => $stock === PHP_INT_MAX ? 0 : $stock,
                'items' => $parts,
                'image' => $parts[0]['image'] ?? null,
            ];
        })->values()->all();
    }

    /**
     * Splits what `$comboQuantity` of a combo costs across its component
     * lines, in proportion to what each would cost on its own. The last line
     * takes the rounding remainder, so the lines always add up to exactly
     * price x quantity. A combo without a fixed price keeps each line's own
     * price untouched.
     *
     * @param  list<array{base_price: float, quantity: int}>  $lines  each component's live unit price and total units ordered
     * @return list<array{price: float, subtotal: float}>
     */
    public function allocate(?float $comboPrice, int $comboQuantity, array $lines): array
    {
        if ($comboPrice === null) {
            return array_map(fn (array $line): array => [
                'price' => round($line['base_price'], 2),
                'subtotal' => round($line['base_price'] * $line['quantity'], 2),
            ], $lines);
        }

        $total = round($comboPrice * $comboQuantity, 2);
        $baseTotal = array_sum(array_map(fn (array $line): float => $line['base_price'] * $line['quantity'], $lines));
        $allocated = [];
        $remaining = $total;
        $lastIndex = array_key_last($lines);

        foreach ($lines as $index => $line) {
            $share = $index === $lastIndex
                ? $remaining
                : ($baseTotal > 0
                    ? round($total * ($line['base_price'] * $line['quantity']) / $baseTotal, 2)
                    : round($total / count($lines), 2));
            $remaining = round($remaining - $share, 2);

            $allocated[] = [
                'price' => round($share / max(1, $line['quantity']), 2),
                'subtotal' => $share,
            ];
        }

        return $allocated;
    }
}
