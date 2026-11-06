import type { BundleRecipe, CartItem } from '@/types';

type FreeDeliveryRules = {
    subtotal: number;
    threshold: number;
    bundleRecipes: BundleRecipe[];
};

/** Mirrors the free-delivery part of `ShippingCostCalculator`, which sets the real charge. */
export function isDeliveryFree(
    items: CartItem[],
    { subtotal, threshold, bundleRecipes }: FreeDeliveryRules,
): boolean {
    if (items.length === 0) {
        return false;
    }

    if (threshold > 0 && subtotal >= threshold) {
        return true;
    }

    return items.every((item) => {
        if (
            item.bundle?.discount_type === 'free_delivery' ||
            bundleRecipes.some(
                (recipe) =>
                    recipe.id === item.bundle_id &&
                    recipe.discount_type === 'free_delivery',
            )
        ) {
            return true;
        }

        if (!item.product.free_delivery) {
            return false;
        }

        const minQuantity = item.product.free_delivery_min_qty ?? null;

        return minQuantity === null || item.quantity >= minQuantity;
    });
}
