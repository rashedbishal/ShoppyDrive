import { AnimatePresence } from 'framer-motion';
import { Plus, Trash2 } from 'lucide-react';
import { AdminReveal } from '@/components/admin/admin-motion';
import { productInputClass } from '@/components/admin/product-form-field';
import { Button } from '@/components/ui/button';
import { CharCount } from '@/components/ui/char-count';
import { useRowKeys } from '@/hooks/use-row-keys';

// Matches ProductRequest's `specifications` cap (app/Http/Requests/Admin/ProductRequest.php).
// Deliberately not exported: a React-Fast-Refresh-eligible module must export
// only components (plus type-only exports, which are erased at compile time
// and don't count) — a real value export here would force Vite to fall back
// to a full page reload on every edit to this file instead of hot-swapping it.
const MAX_SPECIFICATIONS = 30;

export type SpecificationRow = {
    key: string;
    value: string;
};

type Props = {
    specifications: SpecificationRow[];
    onChange: (specifications: SpecificationRow[]) => void;
    errors?: Record<string, string>;
    onClearError?: (field: string) => void;
};

/**
 * Admin-controlled key/value rows shown on the storefront's "Product
 * specifications" card (ProductDetail.tsx) — e.g. SKU, Brand, Unit, or
 * anything else the admin wants to list. Shared between create.tsx and
 * edit.tsx so the two forms can't drift out of sync, the same reasoning
 * ProductVariantManager was already split out for.
 */
export function ProductSpecificationsManager({
    specifications,
    onChange,
    errors = {},
    onClearError,
}: Props): React.JSX.Element {
    const { keys: rowKeys, removeKey } = useRowKeys(specifications.length);

    const addRow = () => {
        if (specifications.length >= MAX_SPECIFICATIONS) {
            return;
        }

        onChange([...specifications, { key: '', value: '' }]);
    };

    const removeRow = (index: number) => {
        removeKey(index);
        onChange(specifications.filter((_, i) => i !== index));
    };

    const updateRow = (
        index: number,
        field: 'key' | 'value',
        value: string,
    ) => {
        const next = [...specifications];
        next[index] = { ...next[index], [field]: value };
        onChange(next);
        onClearError?.(`specifications.${index}.${field}`);
    };

    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <span className="text-sm font-medium text-slate-700">
                    Specifications
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={addRow}
                    disabled={specifications.length >= MAX_SPECIFICATIONS}
                >
                    <Plus className="mr-1 size-3.5" /> Add Row
                </Button>
            </div>

            <div className="space-y-3">
                <AnimatePresence initial={false}>
                    {specifications.map((row, index) => (
                        <AdminReveal key={rowKeys[index]}>
                            <div className="flex items-start gap-2">
                                <div className="flex-1">
                                    <input
                                        className={productInputClass(
                                            errors[
                                                `specifications.${index}.key`
                                            ],
                                        )}
                                        value={row.key}
                                        onChange={(e) =>
                                            updateRow(
                                                index,
                                                'key',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Key (e.g. Brand)"
                                        maxLength={60}
                                    />
                                    <CharCount
                                        value={row.key}
                                        max={60}
                                        className="mt-1 block text-right"
                                    />
                                    {errors[`specifications.${index}.key`] && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {
                                                errors[
                                                    `specifications.${index}.key`
                                                ]
                                            }
                                        </p>
                                    )}
                                </div>
                                <div className="flex-1">
                                    <input
                                        className={productInputClass(
                                            errors[
                                                `specifications.${index}.value`
                                            ],
                                        )}
                                        value={row.value}
                                        onChange={(e) =>
                                            updateRow(
                                                index,
                                                'value',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Value (e.g. Languo)"
                                        maxLength={200}
                                    />
                                    <CharCount
                                        value={row.value}
                                        max={200}
                                        className="mt-1 block text-right"
                                    />
                                    {errors[
                                        `specifications.${index}.value`
                                    ] && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {
                                                errors[
                                                    `specifications.${index}.value`
                                                ]
                                            }
                                        </p>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="mt-0.5 shrink-0 text-red-400"
                                    onClick={() => removeRow(index)}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </AdminReveal>
                    ))}
                </AnimatePresence>
                <AnimatePresence initial={false}>
                    {specifications.length === 0 && (
                        <AdminReveal key="empty">
                            <p className="py-2 text-xs text-slate-600 italic">
                                No specifications added. These show as a
                                "Product specifications" card on the product
                                page.
                            </p>
                        </AdminReveal>
                    )}
                </AnimatePresence>
            </div>
        </div>
    );
}
