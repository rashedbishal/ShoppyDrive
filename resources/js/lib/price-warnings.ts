import { formatCurrency } from '@/lib/utils';

/**
 * A warning, never an error: selling below cost can be deliberate
 * (clearance, a loss-leader flash sale), so the admin can still save. It
 * catches typos like ৳150 for ৳1,500. Silent when either value is missing,
 * since cost price is optional.
 */
export function belowCostWarning(
    price: string | number | null | undefined,
    cost: string | number | null | undefined,
): string | null {
    if (price === '' || price == null || cost === '' || cost == null) {
        return null;
    }

    const priceValue = Number(price);
    const costValue = Number(cost);

    if (
        !Number.isFinite(priceValue) ||
        !Number.isFinite(costValue) ||
        priceValue <= 0 ||
        costValue <= 0
    ) {
        return null;
    }

    return priceValue < costValue
        ? `Below the cost price of ${formatCurrency(costValue)}: this sells at a loss.`
        : null;
}
