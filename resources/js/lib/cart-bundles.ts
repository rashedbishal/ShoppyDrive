import { formatCurrency } from '@/lib/utils';
import type { BundleRecipe, BundleRecipeItem, CartItem } from '@/types';

export type CartItemGroup = {
    bundleId: number | null;
    bundleName: string | null;
    items: CartItem[];
};

/** Groups bundle-tagged cart items together under their shared bundle; everything else becomes its own one-item group. */
export function groupCartItems(items: CartItem[]): CartItemGroup[] {
    const groups: CartItemGroup[] = [];
    const indexByBundle = new Map<number, number>();

    for (const item of items) {
        if (item.bundle_id == null) {
            groups.push({ bundleId: null, bundleName: null, items: [item] });
            continue;
        }

        const bundleId = Number(item.bundle_id);
        const existingIndex = indexByBundle.get(bundleId);

        if (existingIndex !== undefined) {
            groups[existingIndex].items.push(item);
        } else {
            indexByBundle.set(bundleId, groups.length);
            groups.push({
                bundleId,
                bundleName: item.bundle?.name ?? 'Bundle',
                items: [item],
            });
        }
    }

    return groups;
}

/**
 * The per-unit price a cart item should be charged as part of `bundle` —
 * mirrors `BundleService::unitPriceFor()`'s proration formula so the
 * client's instant guess always agrees with what the server will actually
 * charge. A free-delivery bundle has no price override, so this just
 * returns the component's own base price unchanged.
 */
function bundleComponentUnitPrice(
    bundle: BundleRecipe,
    component: BundleRecipeItem,
): number {
    if (
        bundle.discount_type !== 'fixed_price' ||
        bundle.bundle_price === null
    ) {
        return component.base_price;
    }

    const referenceTotal = bundle.items.reduce(
        (sum, i) => sum + i.base_price * i.quantity,
        0,
    );

    if (referenceTotal <= 0) {
        return component.base_price;
    }

    return (
        Math.round(
            ((bundle.bundle_price * component.base_price) / referenceTotal) *
                100,
        ) / 100
    );
}

function bundleItemKey(bundleId: number, item: CartItem): string {
    const key = item.variant
        ? `variant:${item.variant.id}`
        : `product:${item.product.id}`;

    return `bundle:${bundleId}:${key}`;
}

function createSyntheticItemId(): number {
    return Date.now() + Math.floor(Math.random() * 1000000);
}

export type AutoAssembleResult = {
    items: CartItem[];
    formed: BundleRecipe[];
};

/**
 * Mirrors `BundleService::autoAssemble()` on the backend, purely
 * client-side so the price/grouping update is instant rather than waiting
 * on the next server round trip (which only happens for authenticated
 * users, via `cart.sync`): looks for active bundle recipes whose full
 * component list is already covered by this cart's plain
 * (`bundle_id == null`) items, and retags/splits those items into a proper
 * bundle group at the bundle's price. Recipes are tried lowest-id-first,
 * so when two bundles could both claim the same items, the older bundle
 * wins — matching the backend's tie-break.
 */
export function autoAssembleBundles(
    items: CartItem[],
    recipes: BundleRecipe[],
): AutoAssembleResult {
    const working = [...items];
    const formed: BundleRecipe[] = [];

    if (recipes.length === 0) {
        return { items: working, formed };
    }

    const sortedRecipes = [...recipes].sort(
        (a, b) => Number(a.id) - Number(b.id),
    );

    for (const recipe of sortedRecipes) {
        if (recipe.items.length < 2) {
            continue;
        }

        const matches: {
            component: BundleRecipeItem;
            candidateIndex: number;
        }[] = [];
        let multiplier: number | null = null;

        for (const component of recipe.items) {
            if (component.quantity <= 0) {
                multiplier = 0;
                break;
            }

            const candidateIndex = working.findIndex(
                (item) =>
                    item.bundle_id == null &&
                    item.product.id === component.product_id &&
                    (item.variant?.id ?? null) === component.variant_id &&
                    item.quantity >= component.quantity,
            );

            if (candidateIndex === -1) {
                multiplier = 0;
                break;
            }

            const itemMultiplier = Math.floor(
                working[candidateIndex].quantity / component.quantity,
            );
            multiplier =
                multiplier === null
                    ? itemMultiplier
                    : Math.min(multiplier, itemMultiplier);
            matches.push({ component, candidateIndex });
        }

        if (multiplier === null || multiplier < 1) {
            continue;
        }

        for (const { component, candidateIndex } of matches) {
            const candidate = working[candidateIndex];
            const required = component.quantity * multiplier;
            const price = bundleComponentUnitPrice(recipe, component);

            // A separate row might already represent this exact component
            // for this exact bundle (e.g. added explicitly via "Add bundle
            // to cart" earlier) — merge into it rather than creating a
            // second row for the same bundle/product/variant combination.
            const existingBundleRowIndex = working.findIndex(
                (item, idx) =>
                    idx !== candidateIndex &&
                    item.bundle_id === recipe.id &&
                    item.product.id === component.product_id &&
                    (item.variant?.id ?? null) === component.variant_id,
            );

            let convertedInPlace = false;

            if (existingBundleRowIndex !== -1) {
                const existingRow = working[existingBundleRowIndex];
                const quantity = existingRow.quantity + required;
                working[existingBundleRowIndex] = {
                    ...existingRow,
                    quantity,
                    subtotal: existingRow.price * quantity,
                    formatted_subtotal: formatCurrency(
                        existingRow.price * quantity,
                    ),
                };
            } else if (candidate.quantity === required) {
                working[candidateIndex] = {
                    ...candidate,
                    item_key: bundleItemKey(recipe.id, candidate),
                    bundle_id: recipe.id,
                    bundle_unit_quantity: component.quantity,
                    bundle: {
                        id: recipe.id,
                        name: recipe.name,
                        image: null,
                        discount_type: recipe.discount_type,
                    },
                    price,
                    formatted_price: formatCurrency(price),
                    subtotal: price * candidate.quantity,
                    formatted_subtotal: formatCurrency(
                        price * candidate.quantity,
                    ),
                };
                convertedInPlace = true;
            } else {
                working.push({
                    ...candidate,
                    id: createSyntheticItemId(),
                    item_key: bundleItemKey(recipe.id, candidate),
                    bundle_id: recipe.id,
                    bundle_unit_quantity: component.quantity,
                    bundle: {
                        id: recipe.id,
                        name: recipe.name,
                        image: null,
                        discount_type: recipe.discount_type,
                    },
                    quantity: required,
                    price,
                    formatted_price: formatCurrency(price),
                    subtotal: price * required,
                    formatted_subtotal: formatCurrency(price * required),
                });
            }

            if (!convertedInPlace) {
                const remaining = candidate.quantity - required;

                if (remaining <= 0) {
                    working[candidateIndex] = {
                        ...candidate,
                        quantity: 0,
                    };
                } else {
                    working[candidateIndex] = {
                        ...candidate,
                        quantity: remaining,
                        subtotal: candidate.price * remaining,
                        formatted_subtotal: formatCurrency(
                            candidate.price * remaining,
                        ),
                    };
                }
            }
        }

        formed.push(recipe);
    }

    return { items: working.filter((item) => item.quantity > 0), formed };
}
