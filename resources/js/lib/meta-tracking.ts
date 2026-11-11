import { getXsrfToken } from '@/lib/csrf';

/**
 * Meta (Facebook) Pixel + Conversions API tracking — the one place the
 * storefront reports these events. Each customer action gets ONE event id,
 * used for both:
 *
 *  1. the browser Pixel, via a GTM dataLayer push (`event` is the GTM
 *     trigger name, `meta_event_name` the Meta event, `event_id` must be
 *     mapped to the Pixel tag's "Event ID" option), and
 *  2. the server, via POST /tracking/meta (MetaTrackingController), which
 *     looks prices and customer details up itself and sends the event to
 *     Meta's Conversions API with the same id — so Meta counts it once.
 *
 * Purchase is different: its id is the order's own (`order-<number>`,
 * MetaEventRecorder::purchaseEventId) and the server sends it from the
 * order itself, so the browser only pushes it to GTM.
 *
 * Tracking must never break shopping: nothing here throws, and the server
 * call runs in the background after the action has already happened.
 */

export type MetaEventName =
    | 'ViewContent'
    | 'AddToCart'
    | 'RemoveFromCart'
    | 'AddToWishlist'
    | 'InitiateCheckout'
    | 'Search'
    | 'Purchase';

/** GTM Custom Event trigger names, one per Meta event. */
export const META_GTM_EVENTS: Record<MetaEventName, string> = {
    ViewContent: 'meta_view_content',
    AddToCart: 'meta_add_to_cart',
    RemoveFromCart: 'meta_remove_from_cart',
    AddToWishlist: 'meta_add_to_wishlist',
    InitiateCheckout: 'meta_initiate_checkout',
    Search: 'meta_search',
    Purchase: 'meta_purchase',
};

const CURRENCY = 'BDT';

/** Every dataLayer field an event below may set (the GTM guide maps these). */
const TRACKING_FIELDS = [
    'meta_event_name',
    'event_id',
    'product_title',
    'product_id',
    'value',
    'currency',
    'quantity',
    'num_items',
    'content_ids',
    'content_type',
    'contents',
    'items',
    'coupon',
    'transaction_id',
    'search_string',
    'search_keyword',
    'event_source_url',
] as const;

export type MetaTrackedItem = {
    productId: number;
    variantId?: number | null;
    bundleId?: number | null;
    name: string;
    /** Display price for the dataLayer only; the server prices it itself. */
    price: number;
    quantity: number;
};

type DataLayerWindow = Window & { dataLayer?: Record<string, unknown>[] };

/** A RFC 4122 v4 UUID; crypto.randomUUID() needs a secure context, so fall back to getRandomValues(). */
export function createMetaEventId(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function reportTrackingProblem(message: string, error?: unknown): void {
    if (import.meta.env.DEV) {
        console.warn(`[Meta tracking] ${message}`, error ?? '');
    }
}

function pushToDataLayer(payload: Record<string, unknown>): void {
    try {
        const target = window as DataLayerWindow;
        target.dataLayer = target.dataLayer || [];
        // GTM merges every push into one data model, so a field from an
        // earlier event (an Add to Cart's value, a list) would leak into
        // this one; clear every tracking field first.
        target.dataLayer.push(
            Object.fromEntries(TRACKING_FIELDS.map((field) => [field, null])),
        );
        target.dataLayer.push(payload);
    } catch (error) {
        reportTrackingProblem('dataLayer push failed', error);
    }
}

/**
 * Sent with `keepalive` so it survives the page navigating away (e.g. Add
 * to Cart followed by opening checkout). Retried once on a network error
 * with the very same body — and so the same event id.
 */
function sendToServer(body: Record<string, unknown>, isRetry = false): void {
    try {
        fetch(route('tracking.meta'), {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        })
            .then((response) => {
                if (!response.ok && response.status !== 429) {
                    reportTrackingProblem(
                        `server refused ${String(body.event_name)} (${response.status})`,
                    );
                }
            })
            .catch((error) => {
                if (!isRetry) {
                    window.setTimeout(() => sendToServer(body, true), 2000);

                    return;
                }

                reportTrackingProblem('server unreachable', error);
            });
    } catch (error) {
        reportTrackingProblem('could not send to server', error);
    }
}

function contentsFor(items: MetaTrackedItem[]) {
    return items.map((item) => ({
        id: String(item.productId),
        quantity: item.quantity,
        item_price: item.price,
    }));
}

function valueOf(items: MetaTrackedItem[]): number {
    return (
        Math.round(
            items.reduce((sum, item) => sum + item.price * item.quantity, 0) *
                100,
        ) / 100
    );
}

function serverItems(items: MetaTrackedItem[]) {
    return items.map((item) => ({
        product_id: item.productId,
        variant_id: item.variantId ?? null,
        bundle_id: item.bundleId ?? null,
        quantity: item.quantity,
    }));
}

/**
 * ViewContent, AddToCart, RemoveFromCart, AddToWishlist: one or more
 * products. Call only after the action really happened.
 */
export function trackMetaProductEvent(
    eventName: 'ViewContent' | 'AddToCart' | 'RemoveFromCart' | 'AddToWishlist',
    items: MetaTrackedItem[],
): void {
    if (items.length === 0) {
        return;
    }

    const eventId = createMetaEventId();
    const eventSourceUrl = window.location.href;
    const contents = contentsFor(items);
    const first = items[0];

    pushToDataLayer({
        event: META_GTM_EVENTS[eventName],
        meta_event_name: eventName,
        event_id: eventId,
        product_title: items.length === 1 ? first.name : undefined,
        product_id: items.length === 1 ? String(first.productId) : undefined,
        value: valueOf(items),
        currency: CURRENCY,
        quantity: items.reduce((sum, item) => sum + item.quantity, 0),
        content_ids: [...new Set(contents.map((line) => line.id))],
        content_type: 'product',
        contents,
        event_source_url: eventSourceUrl,
    });

    sendToServer({
        event_name: eventName,
        event_id: eventId,
        event_source_url: eventSourceUrl,
        items: serverItems(items),
    });
}

export function trackMetaInitiateCheckout(
    items: MetaTrackedItem[],
    coupon: string | null,
): void {
    if (items.length === 0) {
        return;
    }

    const eventId = createMetaEventId();
    const eventSourceUrl = window.location.href;
    const contents = contentsFor(items);

    pushToDataLayer({
        event: META_GTM_EVENTS.InitiateCheckout,
        meta_event_name: 'InitiateCheckout',
        event_id: eventId,
        currency: CURRENCY,
        value: valueOf(items),
        coupon: coupon ?? undefined,
        num_items: items.reduce((sum, item) => sum + item.quantity, 0),
        content_ids: [...new Set(contents.map((line) => line.id))],
        content_type: 'product',
        contents,
        items: items.map((item) => ({
            product_id: String(item.productId),
            product_name: item.name,
            price: item.price,
            quantity: item.quantity,
        })),
        event_source_url: eventSourceUrl,
    });

    sendToServer({
        event_name: 'InitiateCheckout',
        event_id: eventId,
        event_source_url: eventSourceUrl,
        items: serverItems(items),
        coupon,
    });
}

export function trackMetaSearch(
    searchString: string,
    resultProductIds: number[],
): void {
    const keyword = searchString.trim();

    if (keyword === '') {
        return;
    }

    const eventId = createMetaEventId();
    const eventSourceUrl = window.location.href;
    const contentIds = resultProductIds.slice(0, 20).map(String);

    pushToDataLayer({
        event: META_GTM_EVENTS.Search,
        meta_event_name: 'Search',
        event_id: eventId,
        search_string: keyword,
        search_keyword: keyword,
        content_ids: contentIds,
        content_type: 'product',
        contents: contentIds.map((id) => ({ id, quantity: 1 })),
        event_source_url: eventSourceUrl,
    });

    sendToServer({
        event_name: 'Search',
        event_id: eventId,
        event_source_url: eventSourceUrl,
        search_string: keyword.slice(0, 100),
        items: resultProductIds
            .slice(0, 20)
            .map((productId) => ({ product_id: productId })),
    });
}

const PURCHASE_SENT_PREFIX = 'sd_meta_purchase:';

/**
 * Browser half of Purchase only — the server sends its half from the order
 * itself, with the same `order-<number>` id. Remembered per order in this
 * browser, so refreshing the confirmation page doesn't push it again
 * (Meta would deduplicate it, but other GTM tags might not).
 */
export function trackMetaPurchase(order: {
    orderNumber: string;
    value: number;
}): void {
    const storageKey = `${PURCHASE_SENT_PREFIX}${order.orderNumber}`;

    try {
        if (window.localStorage.getItem(storageKey) !== null) {
            return;
        }

        window.localStorage.setItem(storageKey, String(Date.now()));
    } catch {
        // Storage blocked (private mode): still report it once per page load.
    }

    pushToDataLayer({
        event: META_GTM_EVENTS.Purchase,
        meta_event_name: 'Purchase',
        event_id: `order-${order.orderNumber}`,
        transaction_id: order.orderNumber,
        value: order.value,
        currency: CURRENCY,
        event_source_url: window.location.href,
    });
}
