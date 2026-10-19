import { Star, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { cn } from '@/lib/utils';

export type VariantRow = {
    color_value_id: number | null;
    size_value_id: number | null;
    sku: string;
    price: string;
    compare_price: string;
    cost_price: string;
    stock_quantity: string;
    low_stock_threshold: string;
    is_default: boolean;
};

type AttributeValueOption = {
    id: number;
    value: string;
    color_hex: string | null;
};

type Props = {
    colorValues: AttributeValueOption[];
    sizeValues: AttributeValueOption[];
    hasColorVariant: boolean;
    hasSizeVariant: boolean;
    onToggleColor: (enabled: boolean) => void;
    onToggleSize: (enabled: boolean) => void;
    variants: VariantRow[];
    onVariantsChange: (variants: VariantRow[]) => void;
    errors?: Record<string, string>;
};

function blankVariant(
    colorId: number | null,
    sizeId: number | null,
): VariantRow {
    return {
        color_value_id: colorId,
        size_value_id: sizeId,
        sku: '',
        price: '',
        compare_price: '',
        cost_price: '',
        stock_quantity: '0',
        low_stock_threshold: '5',
        is_default: false,
    };
}

/** Rebuilds the variant row set for a target list of (color, size) keys, reusing
 *  already-typed values for keys that still exist and dropping the rest. */
function reconcile(
    previous: VariantRow[],
    colorIds: (number | null)[],
    sizeIds: (number | null)[],
): VariantRow[] {
    const keys: [number | null, number | null][] = [];

    for (const colorId of colorIds) {
        for (const sizeId of sizeIds) {
            keys.push([colorId, sizeId]);
        }
    }

    const next = keys.map(([colorId, sizeId]) => {
        const existing = previous.find(
            (row) =>
                row.color_value_id === colorId && row.size_value_id === sizeId,
        );

        return existing ?? blankVariant(colorId, sizeId);
    });

    if (next.length > 0 && !next.some((row) => row.is_default)) {
        next[0].is_default = true;
    }

    return next;
}

export function ProductVariantManager({
    colorValues,
    sizeValues,
    hasColorVariant,
    hasSizeVariant,
    onToggleColor,
    onToggleSize,
    variants,
    onVariantsChange,
    errors,
}: Props): React.JSX.Element {
    const selectedColorIds = useMemo(
        () =>
            Array.from(
                new Set(
                    variants
                        .map((v) => v.color_value_id)
                        .filter((id): id is number => id !== null),
                ),
            ),
        [variants],
    );
    const selectedSizeIds = useMemo(
        () =>
            Array.from(
                new Set(
                    variants
                        .map((v) => v.size_value_id)
                        .filter((id): id is number => id !== null),
                ),
            ),
        [variants],
    );

    // useColor/useSize are explicit rather than read from the hasColorVariant/
    // hasSizeVariant props, because the toggle handlers call this in the same
    // tick as flipping those props — the props are still stale (pre-toggle)
    // until the next render, so relying on them here would silently ignore
    // the dimension that was just turned on/off.
    const rebuild = (
        nextColorIds: number[],
        nextSizeIds: number[],
        useColor: boolean,
        useSize: boolean,
    ) => {
        const colorKeys: (number | null)[] = useColor ? nextColorIds : [null];
        const sizeKeys: (number | null)[] = useSize ? nextSizeIds : [null];

        if (colorKeys.length === 0 || sizeKeys.length === 0) {
            onVariantsChange([]);

            return;
        }

        onVariantsChange(reconcile(variants, colorKeys, sizeKeys));
    };

    const toggleColorValue = (id: number) => {
        const next = selectedColorIds.includes(id)
            ? selectedColorIds.filter((existingId) => existingId !== id)
            : [...selectedColorIds, id];
        rebuild(
            next,
            hasSizeVariant ? selectedSizeIds : [],
            hasColorVariant,
            hasSizeVariant,
        );
    };

    const toggleSizeValue = (id: number) => {
        const next = selectedSizeIds.includes(id)
            ? selectedSizeIds.filter((existingId) => existingId !== id)
            : [...selectedSizeIds, id];
        rebuild(
            hasColorVariant ? selectedColorIds : [],
            next,
            hasColorVariant,
            hasSizeVariant,
        );
    };

    const handleToggleColor = (enabled: boolean) => {
        onToggleColor(enabled);

        if (!enabled) {
            rebuild([], selectedSizeIds, false, hasSizeVariant);
        } else if (selectedColorIds.length === 0 && colorValues.length > 0) {
            rebuild(
                [colorValues[0].id],
                hasSizeVariant ? selectedSizeIds : [],
                true,
                hasSizeVariant,
            );
        }
    };

    const handleToggleSize = (enabled: boolean) => {
        onToggleSize(enabled);

        if (!enabled) {
            rebuild(selectedColorIds, [], hasColorVariant, false);
        } else if (selectedSizeIds.length === 0 && sizeValues.length > 0) {
            rebuild(
                hasColorVariant ? selectedColorIds : [],
                [sizeValues[0].id],
                hasColorVariant,
                true,
            );
        }
    };

    const updateRow = (index: number, patch: Partial<VariantRow>) => {
        const next = variants.map((row, i) =>
            i === index ? { ...row, ...patch } : row,
        );
        onVariantsChange(next);
    };

    const setDefault = (index: number) => {
        onVariantsChange(
            variants.map((row, i) => ({ ...row, is_default: i === index })),
        );
    };

    const removeRow = (index: number) => {
        const next = variants.filter((_, i) => i !== index);

        if (next.length > 0 && !next.some((row) => row.is_default)) {
            next[0].is_default = true;
        }

        onVariantsChange(next);
    };

    const valueLabel = (id: number | null, options: AttributeValueOption[]) =>
        options.find((opt) => opt.id === id)?.value ?? '—';

    const inputClass =
        'h-8 w-full rounded-lg border border-slate-200 px-2 text-xs focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-6">
                <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input
                        type="checkbox"
                        checked={hasColorVariant}
                        onChange={(e) => handleToggleColor(e.target.checked)}
                        className="size-4 rounded border-slate-300 text-[#c8102e] focus:ring-[#c8102e]"
                    />
                    Enable Color
                </label>
                <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input
                        type="checkbox"
                        checked={hasSizeVariant}
                        onChange={(e) => handleToggleSize(e.target.checked)}
                        className="size-4 rounded border-slate-300 text-[#c8102e] focus:ring-[#c8102e]"
                    />
                    Enable Size
                </label>
            </div>

            {hasColorVariant && (
                <div>
                    <p className="mb-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        Colors
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {colorValues.map((color) => {
                            const active = selectedColorIds.includes(color.id);

                            return (
                                <button
                                    key={color.id}
                                    type="button"
                                    onClick={() => toggleColorValue(color.id)}
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition',
                                        active
                                            ? 'border-[#c8102e] bg-[#c8102e]/10 text-[#c8102e]'
                                            : 'border-slate-200 text-slate-600 hover:border-slate-300',
                                    )}
                                >
                                    <span
                                        className="size-3 rounded-full border border-black/10"
                                        style={{
                                            backgroundColor:
                                                color.color_hex || '#ccc',
                                        }}
                                    />
                                    {color.value}
                                </button>
                            );
                        })}
                        {colorValues.length === 0 && (
                            <p className="text-xs text-slate-400">
                                No color values yet — add some under Admin &gt;
                                Attributes.
                            </p>
                        )}
                    </div>
                </div>
            )}

            {hasSizeVariant && (
                <div>
                    <p className="mb-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        Sizes
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {sizeValues.map((size) => {
                            const active = selectedSizeIds.includes(size.id);

                            return (
                                <button
                                    key={size.id}
                                    type="button"
                                    onClick={() => toggleSizeValue(size.id)}
                                    className={cn(
                                        'rounded-full border px-3 py-1.5 text-xs font-medium transition',
                                        active
                                            ? 'border-[#c8102e] bg-[#c8102e]/10 text-[#c8102e]'
                                            : 'border-slate-200 text-slate-600 hover:border-slate-300',
                                    )}
                                >
                                    {size.value}
                                </button>
                            );
                        })}
                        {sizeValues.length === 0 && (
                            <p className="text-xs text-slate-400">
                                No size values yet — add some under Admin &gt;
                                Attributes.
                            </p>
                        )}
                    </div>
                </div>
            )}

            {variants.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200">
                    <table className="w-full text-xs">
                        <thead className="bg-slate-50 text-slate-500">
                            <tr>
                                <th className="px-2 py-2 text-left font-medium">
                                    Variant
                                </th>
                                <th className="px-2 py-2 text-left font-medium">
                                    SKU
                                </th>
                                <th className="px-2 py-2 text-left font-medium">
                                    Price
                                </th>
                                <th className="px-2 py-2 text-left font-medium">
                                    Compare
                                </th>
                                <th className="px-2 py-2 text-left font-medium">
                                    Cost
                                </th>
                                <th className="px-2 py-2 text-left font-medium">
                                    Stock
                                </th>
                                <th className="px-2 py-2 text-left font-medium">
                                    Low stock
                                </th>
                                <th className="px-2 py-2 text-center font-medium">
                                    Default
                                </th>
                                <th className="px-2 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {variants.map((row, index) => (
                                <tr
                                    key={`${row.color_value_id}-${row.size_value_id}`}
                                >
                                    <td className="px-2 py-1.5 font-medium whitespace-nowrap text-slate-700">
                                        {[
                                            hasColorVariant &&
                                                valueLabel(
                                                    row.color_value_id,
                                                    colorValues,
                                                ),
                                            hasSizeVariant &&
                                                valueLabel(
                                                    row.size_value_id,
                                                    sizeValues,
                                                ),
                                        ]
                                            .filter(Boolean)
                                            .join(' / ')}
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <input
                                            className={inputClass}
                                            value={row.sku}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    sku: e.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className={inputClass}
                                            value={row.price}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    price: e.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className={inputClass}
                                            value={row.compare_price}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    compare_price:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className={inputClass}
                                            value={row.cost_price}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    cost_price: e.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <input
                                            type="number"
                                            min="0"
                                            className={inputClass}
                                            value={row.stock_quantity}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    stock_quantity:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <input
                                            type="number"
                                            min="0"
                                            className={inputClass}
                                            value={row.low_stock_threshold}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    low_stock_threshold:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className="px-2 py-1.5 text-center">
                                        <button
                                            type="button"
                                            onClick={() => setDefault(index)}
                                            title="Set as default"
                                        >
                                            <Star
                                                className={cn(
                                                    'size-4',
                                                    row.is_default
                                                        ? 'fill-amber-400 text-amber-400'
                                                        : 'text-slate-300',
                                                )}
                                            />
                                        </button>
                                    </td>
                                    <td className="px-2 py-1.5">
                                        <button
                                            type="button"
                                            onClick={() => removeRow(index)}
                                            className="text-slate-400 hover:text-red-500"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {errors?.variants && (
                <p className="text-xs text-red-500">{errors.variants}</p>
            )}
        </div>
    );
}
