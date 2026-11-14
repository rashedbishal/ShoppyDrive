import type { Order } from '@/types';

/**
 * DISC-38: mirrors App\Support\OrderInvoice::isAvailable() for pages that
 * receive the full order (the server still enforces it): every order has an
 * invoice from the moment it's placed, Cash on Delivery included, except a
 * cancelled or refunded (or trashed) one.
 */
export function orderHasInvoice(
    order: Pick<Order, 'status'> & { deleted_at?: string | null },
): boolean {
    return (
        !order.deleted_at &&
        order.status !== 'cancelled' &&
        order.status !== 'refunded'
    );
}
