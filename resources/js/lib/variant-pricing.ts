import type { VariantRow } from '@/components/admin/ProductVariantManager';

/** Lowest price among variants that actually have a valid, non-empty price
 *  typed in yet. Returns null if none do (so callers can show a neutral
 *  "still needs pricing" state instead of a stray Infinity). */
export function computeMinVariantPrice(variants: VariantRow[]): number | null {
    const prices = variants
        .map((v) => parseFloat(v.price))
        .filter((price) => Number.isFinite(price));

    return prices.length > 0 ? Math.min(...prices) : null;
}
