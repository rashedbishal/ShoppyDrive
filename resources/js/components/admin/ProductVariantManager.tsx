import { Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

function VariantField({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}): React.JSX.Element {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-500">
                {label}
            </span>
            {children}
        </label>
    );
}

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

function uniqueIds(
    variants: VariantRow[],
    key: 'color_value_id' | 'size_value_id',
): number[] {
    return Array.from(
        new Set(
            variants
                .map((v) => v[key])
                .filter((id): id is number => id !== null),
        ),
    );
}

/** Ensures exactly one row is marked default whenever the set is non-empty,
 *  defaulting to the first row if the previous default got removed. */
function ensureDefault(rows: VariantRow[]): VariantRow[] {
    if (rows.length === 0 || rows.some((row) => row.is_default)) {
        return rows;
    }

    return rows.map((row, i) => (i === 0 ? { ...row, is_default: true } : row));
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
    // Candidate colors/sizes are which values are "in play" for building
    // combinations — tracked independently of `variants` (rather than derived
    // from it) so that, in dual mode, picking a color and a size doesn't
    // force-generate every pairing. Initialized from any variants already
    // present (e.g. loading the edit page) so pills/matrix start in sync.
    const [candidateColorIds, setCandidateColorIds] = useState<number[]>(() =>
        uniqueIds(variants, 'color_value_id'),
    );
    const [candidateSizeIds, setCandidateSizeIds] = useState<number[]>(() =>
        uniqueIds(variants, 'size_value_id'),
    );

    const dualMode = hasColorVariant && hasSizeVariant;

    const toggleColorValue = (id: number) => {
        const isActive = candidateColorIds.includes(id);
        setCandidateColorIds(
            isActive
                ? candidateColorIds.filter((existingId) => existingId !== id)
                : [...candidateColorIds, id],
        );

        if (!dualMode) {
            // Single-dimension: the pill directly is the variant.
            if (isActive) {
                if (variants.length <= 1) {
                    return; // can't deselect the only remaining variant this way
                }

                onVariantsChange(
                    ensureDefault(
                        variants.filter((v) => v.color_value_id !== id),
                    ),
                );
            } else {
                onVariantsChange(
                    ensureDefault([...variants, blankVariant(id, null)]),
                );
            }
        } else if (isActive) {
            // Dropping a candidate color drops whatever pairs used it.
            const remaining = variants.filter((v) => v.color_value_id !== id);

            if (remaining.length === 0 && variants.length > 0) {
                return; // would empty the last variant(s) — block
            }

            onVariantsChange(ensureDefault(remaining));
        }
        // Adding a candidate color in dual mode creates no variant by
        // itself — the admin checks specific pairs in the matrix below.
    };

    const toggleSizeValue = (id: number) => {
        const isActive = candidateSizeIds.includes(id);
        setCandidateSizeIds(
            isActive
                ? candidateSizeIds.filter((existingId) => existingId !== id)
                : [...candidateSizeIds, id],
        );

        if (!dualMode) {
            if (isActive) {
                if (variants.length <= 1) {
                    return;
                }

                onVariantsChange(
                    ensureDefault(
                        variants.filter((v) => v.size_value_id !== id),
                    ),
                );
            } else {
                onVariantsChange(
                    ensureDefault([...variants, blankVariant(null, id)]),
                );
            }
        } else if (isActive) {
            const remaining = variants.filter((v) => v.size_value_id !== id);

            if (remaining.length === 0 && variants.length > 0) {
                return;
            }

            onVariantsChange(ensureDefault(remaining));
        }
    };

    const togglePair = (colorId: number, sizeId: number) => {
        const exists = variants.some(
            (v) => v.color_value_id === colorId && v.size_value_id === sizeId,
        );

        if (exists) {
            if (variants.length <= 1) {
                return; // can't uncheck the only remaining pair this way
            }

            onVariantsChange(
                ensureDefault(
                    variants.filter(
                        (v) =>
                            !(
                                v.color_value_id === colorId &&
                                v.size_value_id === sizeId
                            ),
                    ),
                ),
            );
        } else {
            onVariantsChange(
                ensureDefault([...variants, blankVariant(colorId, sizeId)]),
            );
        }
    };

    const handleToggleColor = (enabled: boolean) => {
        onToggleColor(enabled);

        if (!enabled) {
            setCandidateColorIds([]);
            onVariantsChange(
                ensureDefault(
                    candidateSizeIds.map((id) => blankVariant(null, id)),
                ),
            );

            return;
        }

        if (candidateColorIds.length === 0 && colorValues.length > 0) {
            const firstId = colorValues[0].id;
            setCandidateColorIds([firstId]);
            onVariantsChange(
                hasSizeVariant
                    ? [] // entering dual mode — admin picks pairs in the matrix
                    : ensureDefault([blankVariant(firstId, null)]),
            );
        }
    };

    const handleToggleSize = (enabled: boolean) => {
        onToggleSize(enabled);

        if (!enabled) {
            setCandidateSizeIds([]);
            onVariantsChange(
                ensureDefault(
                    candidateColorIds.map((id) => blankVariant(id, null)),
                ),
            );

            return;
        }

        if (candidateSizeIds.length === 0 && sizeValues.length > 0) {
            const firstId = sizeValues[0].id;
            setCandidateSizeIds([firstId]);
            onVariantsChange(
                hasColorVariant
                    ? []
                    : ensureDefault([blankVariant(null, firstId)]),
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
        onVariantsChange(ensureDefault(variants.filter((_, i) => i !== index)));
    };

    const valueLabel = (id: number | null, options: AttributeValueOption[]) =>
        options.find((opt) => opt.id === id)?.value ?? '—';

    const colorSwatch = (id: number | null) =>
        colorValues.find((opt) => opt.id === id)?.color_hex;

    const fieldInputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

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
                            const active = candidateColorIds.includes(color.id);

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
                            const active = candidateSizeIds.includes(size.id);

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

            {dualMode &&
                candidateColorIds.length > 0 &&
                candidateSizeIds.length > 0 && (
                    <div>
                        <p className="mb-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            Combinations
                        </p>
                        <p className="mb-2 text-xs text-slate-400">
                            Check the color/size pairs you actually stock.
                        </p>
                        <div className="overflow-x-auto rounded-xl border border-slate-200">
                            <table className="w-full text-xs">
                                <thead className="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Color \ Size
                                        </th>
                                        {candidateSizeIds.map((sizeId) => (
                                            <th
                                                key={sizeId}
                                                className="px-3 py-2 text-center font-medium"
                                            >
                                                {valueLabel(sizeId, sizeValues)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {candidateColorIds.map((colorId) => (
                                        <tr key={colorId}>
                                            <td className="flex items-center gap-1.5 px-3 py-2 font-medium whitespace-nowrap text-slate-700">
                                                <span
                                                    className="size-3 shrink-0 rounded-full border border-black/10"
                                                    style={{
                                                        backgroundColor:
                                                            colorSwatch(
                                                                colorId,
                                                            ) || '#ccc',
                                                    }}
                                                />
                                                {valueLabel(
                                                    colorId,
                                                    colorValues,
                                                )}
                                            </td>
                                            {candidateSizeIds.map((sizeId) => {
                                                const checked = variants.some(
                                                    (v) =>
                                                        v.color_value_id ===
                                                            colorId &&
                                                        v.size_value_id ===
                                                            sizeId,
                                                );
                                                const locked =
                                                    checked &&
                                                    variants.length <= 1;

                                                return (
                                                    <td
                                                        key={sizeId}
                                                        className="px-3 py-2 text-center"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={checked}
                                                            disabled={locked}
                                                            onChange={() =>
                                                                togglePair(
                                                                    colorId,
                                                                    sizeId,
                                                                )
                                                            }
                                                            title={
                                                                locked
                                                                    ? "Can't remove the only remaining pair here"
                                                                    : undefined
                                                            }
                                                            className="size-4 rounded border-slate-300 text-[#c8102e] focus:ring-[#c8102e] disabled:opacity-40"
                                                        />
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

            {variants.length > 0 && (
                <div className="space-y-3">
                    {variants.map((row, index) => {
                        const label = [
                            hasColorVariant &&
                                valueLabel(row.color_value_id, colorValues),
                            hasSizeVariant &&
                                valueLabel(row.size_value_id, sizeValues),
                        ]
                            .filter(Boolean)
                            .join(' / ');
                        const swatch = colorSwatch(row.color_value_id);

                        return (
                            <div
                                key={`${row.color_value_id}-${row.size_value_id}`}
                                className={cn(
                                    'rounded-xl border p-4 transition',
                                    row.is_default
                                        ? 'border-[#c8102e]/40 bg-[#c8102e]/[0.03]'
                                        : 'border-slate-200',
                                )}
                            >
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        {swatch && (
                                            <span
                                                className="size-3.5 shrink-0 rounded-full border border-black/10"
                                                style={{
                                                    backgroundColor: swatch,
                                                }}
                                            />
                                        )}
                                        <span className="text-sm font-semibold text-slate-800">
                                            {label}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <button
                                            type="button"
                                            onClick={() => setDefault(index)}
                                            className={cn(
                                                'flex items-center gap-1 text-xs font-medium',
                                                row.is_default
                                                    ? 'text-amber-600'
                                                    : 'text-slate-400 hover:text-slate-600',
                                            )}
                                        >
                                            <Star
                                                className={cn(
                                                    'size-3.5',
                                                    row.is_default &&
                                                        'fill-amber-400 text-amber-400',
                                                )}
                                            />
                                            {row.is_default
                                                ? 'Default'
                                                : 'Set default'}
                                        </button>
                                        {variants.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeRow(index)}
                                                title="Remove variant"
                                                className="text-slate-400 hover:text-red-500"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <VariantField label="SKU">
                                        <input
                                            className={fieldInputClass}
                                            value={row.sku}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    sku: e.target.value,
                                                })
                                            }
                                        />
                                    </VariantField>
                                    <VariantField label="Price (৳)">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className={fieldInputClass}
                                            value={row.price}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    price: e.target.value,
                                                })
                                            }
                                        />
                                    </VariantField>
                                    <VariantField label="Compare price (৳)">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className={fieldInputClass}
                                            value={row.compare_price}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    compare_price:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </VariantField>
                                    <VariantField label="Cost price (৳)">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className={fieldInputClass}
                                            value={row.cost_price}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    cost_price: e.target.value,
                                                })
                                            }
                                        />
                                    </VariantField>
                                    <VariantField label="Stock qty">
                                        <input
                                            type="number"
                                            min="0"
                                            className={fieldInputClass}
                                            value={row.stock_quantity}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    stock_quantity:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </VariantField>
                                    <VariantField label="Low stock alert">
                                        <input
                                            type="number"
                                            min="0"
                                            className={fieldInputClass}
                                            value={row.low_stock_threshold}
                                            onChange={(e) =>
                                                updateRow(index, {
                                                    low_stock_threshold:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </VariantField>
                                </div>
                            </div>
                        );
                    })}
                    {variants.length === 1 && (
                        <p className="text-xs text-slate-400">
                            This is the only variant, so it can't be removed
                            directly — deselect its color/size above, or uncheck
                            "Enable Color"/"Enable Size", to turn variants off.
                        </p>
                    )}
                </div>
            )}

            {errors?.variants && (
                <p className="text-xs text-red-500">{errors.variants}</p>
            )}
        </div>
    );
}
