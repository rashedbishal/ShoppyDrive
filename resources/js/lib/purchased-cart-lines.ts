/**
 * Which cart lines (and how many of each) a checkout submission is buying,
 * so the order confirmation can remove exactly those from the cart and keep
 * anything added meanwhile in another tab — instead of emptying the whole
 * cart (the server already does the same with its own copy, BIZ-019).
 *
 * Kept in sessionStorage: per tab, so a checkout in one tab can never
 * remove lines because of an order placed in another, and it survives the
 * bKash/Nagad payment page between checkout and confirmation. It is read
 * once (taken) when the confirmation page opens, so refreshing that page
 * never removes anything again.
 */

export type PurchasedCartLine = {
    item_key: string;
    quantity: number;
};

const STORAGE_KEY = 'shoppydrive.checkout.purchased_lines';

/** Long enough for the mobile-banking payment window, short enough that a stale record is never applied to a much later order. */
const MAX_AGE_MS = 3 * 60 * 60 * 1000;

export function rememberPurchasedLines(lines: PurchasedCartLine[]): void {
    try {
        window.sessionStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ savedAt: Date.now(), lines }),
        );
    } catch {
        // Storage unavailable: the confirmation then leaves the cart as it is.
    }
}

export function forgetPurchasedLines(): void {
    try {
        window.sessionStorage.removeItem(STORAGE_KEY);
    } catch {
        // Nothing to forget.
    }
}

/** Returns the remembered lines once, then forgets them. */
export function takePurchasedLines(): PurchasedCartLine[] | null {
    try {
        const raw = window.sessionStorage.getItem(STORAGE_KEY);
        window.sessionStorage.removeItem(STORAGE_KEY);

        if (raw === null) {
            return null;
        }

        const parsed = JSON.parse(raw) as {
            savedAt?: unknown;
            lines?: unknown;
        };

        if (
            typeof parsed.savedAt !== 'number' ||
            Date.now() - parsed.savedAt > MAX_AGE_MS ||
            !Array.isArray(parsed.lines)
        ) {
            return null;
        }

        return parsed.lines.filter(
            (line): line is PurchasedCartLine =>
                typeof line?.item_key === 'string' &&
                typeof line?.quantity === 'number' &&
                line.quantity > 0,
        );
    } catch {
        return null;
    }
}
