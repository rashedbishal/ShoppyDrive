import { KeyRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { productInputClass } from '@/components/admin/product-form-field';
import { Button } from '@/components/ui/button';
import { CharCount } from '@/components/ui/char-count';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Owner decisions 2026-11-14 for the product edit form:
 * - stock changed elsewhere (Stock Management, orders, cancellations) is
 *   re-read every few seconds. A stock field the admin hasn't touched takes
 *   the new value; one they typed in is kept and flagged. The stock token is
 *   refreshed only when nothing typed was overtaken, so the server's
 *   stale-stock guard (INV-STALE-STOCK-001) still protects a typed value.
 * - saving a changed stock value needs the admin's password (the server
 *   enforces it; this only asks first).
 */

type LiveStock = {
    token: string;
    stock_quantity: number;
    variants: { key: string; stock_quantity: number }[];
};

type StockRow = { value_ids: number[]; stock_quantity: string };

type StockFormData = {
    stock_token: string;
    stock_quantity: string;
    variant_attribute_ids: number[];
    variants: StockRow[];
};

type Baseline = { product: number; variants: Record<string, number> };

/** How often the form re-reads stock (owner decision: polling, every 5 seconds). */
const POLL_MS = 5000;

/** The same key the server builds from a variant's value ids. */
export function stockKey(valueIds: number[]): string {
    return [...valueIds]
        .map(Number)
        .sort((a, b) => a - b)
        .join('-');
}

export function useLiveProductStock<T extends StockFormData>({
    url,
    initialProductStock,
    initialVariants,
    data,
    setData,
    paused,
}: {
    url: string;
    initialProductStock: number;
    initialVariants: { valueIds: number[]; stock_quantity: number }[];
    data: T;
    setData: (updater: (previous: T) => T) => void;
    paused: boolean;
}) {
    const baseline = useRef<Baseline>({
        product: initialProductStock,
        variants: Object.fromEntries(
            initialVariants.map((v) => [
                stockKey(v.valueIds),
                v.stock_quantity,
            ]),
        ),
    });
    const dataRef = useRef(data);
    const pausedRef = useRef(paused);
    const setDataRef = useRef(setData);
    const [conflicts, setConflicts] = useState<string[]>([]);

    useEffect(() => {
        dataRef.current = data;
        pausedRef.current = paused;
        setDataRef.current = setData;
    });

    useEffect(() => {
        let cancelled = false;

        const poll = async () => {
            if (document.hidden || pausedRef.current) {
                return;
            }

            let live: LiveStock;

            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                live = (await response.json()) as LiveStock;
            } catch {
                return;
            }

            if (cancelled) {
                return;
            }

            const current = dataRef.current;
            const base = baseline.current;
            const overtaken: string[] = [];
            const hasVariants = current.variant_attribute_ids.length > 0;
            let newProductStock: string | null = null;
            const variantUpdates: Record<string, string> = {};

            if (!hasVariants && live.stock_quantity !== base.product) {
                if (current.stock_quantity === String(base.product)) {
                    newProductStock = String(live.stock_quantity);
                    base.product = live.stock_quantity;
                } else {
                    overtaken.push('Stock quantity');
                }
            }

            const liveByKey = Object.fromEntries(
                live.variants.map((v) => [v.key, v.stock_quantity]),
            );

            current.variants.forEach((row, index) => {
                const key = stockKey(row.value_ids);

                if (!(key in liveByKey) || !(key in base.variants)) {
                    return;
                }

                if (liveByKey[key] === base.variants[key]) {
                    return;
                }

                if (row.stock_quantity === String(base.variants[key])) {
                    variantUpdates[key] = String(liveByKey[key]);
                    base.variants[key] = liveByKey[key];
                } else {
                    overtaken.push(`Variant ${index + 1} stock`);
                }
            });

            const anyUpdate =
                newProductStock !== null ||
                Object.keys(variantUpdates).length > 0;

            if (anyUpdate || overtaken.length === 0) {
                setDataRef.current((previous) => ({
                    ...previous,
                    // Only refresh the token when no typed value was overtaken.
                    stock_token:
                        overtaken.length === 0
                            ? live.token
                            : previous.stock_token,
                    stock_quantity:
                        newProductStock !== null &&
                        previous.stock_quantity === current.stock_quantity
                            ? newProductStock
                            : previous.stock_quantity,
                    variants: previous.variants.map((row) => {
                        const next = variantUpdates[stockKey(row.value_ids)];
                        const before = current.variants.find(
                            (r) =>
                                stockKey(r.value_ids) ===
                                stockKey(row.value_ids),
                        );

                        return next !== undefined &&
                            before !== undefined &&
                            row.stock_quantity === before.stock_quantity
                            ? { ...row, stock_quantity: next }
                            : row;
                    }),
                }));
            }

            setConflicts(overtaken);
        };

        const timer = window.setInterval(poll, POLL_MS);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [url]);

    /**
     * Whether saving would change any stock (mirrors the server rule):
     * the product's own stock (no variants), an existing variant's stock, a
     * new variant's starting stock, a removed variant's stock, or switching
     * between "no variants" and "with variants" while stock is involved.
     */
    const stockChanged = (): boolean => {
        const current = dataRef.current;
        const base = baseline.current;
        const hadVariants = Object.keys(base.variants).length > 0;
        const hasVariants = current.variant_attribute_ids.length > 0;
        const productBefore = hadVariants ? 0 : base.product;
        const productAfter = hasVariants
            ? 0
            : Number(current.stock_quantity || 0);

        if (productBefore !== productAfter) {
            return true;
        }

        const rows = hasVariants ? current.variants : [];
        const currentKeys = new Set(rows.map((row) => stockKey(row.value_ids)));

        const rowChanged = rows.some((row) => {
            const key = stockKey(row.value_ids);
            const before = base.variants[key] ?? 0;

            return Number(row.stock_quantity || 0) !== before;
        });

        const removedWithStock = Object.entries(base.variants).some(
            ([key, quantity]) => !currentKeys.has(key) && quantity > 0,
        );

        return rowChanged || removedWithStock;
    };

    return { conflicts, stockChanged };
}

export function StockPasswordDialog({
    open,
    processing,
    error,
    onConfirm,
    onClose,
    description = 'You changed a stock quantity. Enter your password to save it. The change is recorded in the Audit Trail.',
    confirmLabel = 'Save product',
}: {
    open: boolean;
    processing: boolean;
    error?: string;
    onConfirm: (password: string, reason: string) => void;
    onClose: () => void;
    description?: string;
    confirmLabel?: string;
}) {
    const [password, setPassword] = useState('');
    const [reason, setReason] = useState('');
    const [wasOpen, setWasOpen] = useState(open);

    // Cleared when the dialog opens, not when it closes, so the fields don't
    // visibly empty while it fades out.
    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setPassword('');
            setReason('');
        }
    }

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent appearance="premium" className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <KeyRound className="size-4" /> Confirm stock change
                    </DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        onConfirm(password, reason);
                        setPassword('');
                    }}
                >
                    <div>
                        <label
                            htmlFor="stock-change-reason"
                            className="mb-1 block text-sm font-medium text-slate-700"
                        >
                            Reason{' '}
                            <span className="font-normal text-slate-500">
                                (optional)
                            </span>
                        </label>
                        <input
                            className={productInputClass()}
                            id="stock-change-reason"
                            maxLength={255}
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                        />
                        <CharCount
                            value={reason}
                            max={255}
                            className="mt-1 block text-right"
                        />
                    </div>
                    <div>
                        <label
                            htmlFor="stock-change-password"
                            className="mb-1 block text-sm font-medium text-slate-700"
                        >
                            Your password
                        </label>
                        <input
                            className={productInputClass(error)}
                            id="stock-change-password"
                            type="password"
                            maxLength={255}
                            autoComplete="current-password"
                            autoFocus
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                        />
                        {error && (
                            <p className="mt-1 text-xs text-red-500">{error}</p>
                        )}
                    </div>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || password === ''}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : confirmLabel}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
