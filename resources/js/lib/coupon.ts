import type { CartItem, Coupon } from '@/types';

type CouponScope = Pick<Coupon, 'eligible_keys'>;

/** Matches the keys the coupon preview returns for the lines it covers. */
function lineKey(item: CartItem): string {
    return `${item.product.id}:${item.variant?.id ?? ''}`;
}

/** What a coupon's discount is measured against. A coupon with no list covers everything. */
export function getCouponEligibleSubtotal(
    coupon: CouponScope,
    items: CartItem[],
): number {
    const covered = coupon.eligible_keys
        ? items.filter((item) => coupon.eligible_keys?.includes(lineKey(item)))
        : items;

    return covered.reduce((total, item) => total + item.subtotal, 0);
}

/** The lines the coupon preview needs in order to judge a selected-items coupon. */
export function toCouponPreviewItems(items: CartItem[]): {
    product_id: CartItem['product']['id'];
    variant_id: CartItem['product']['id'] | null;
    subtotal: number;
}[] {
    return items.map((item) => ({
        product_id: item.product.id,
        variant_id: item.variant?.id ?? null,
        subtotal: item.subtotal,
    }));
}
