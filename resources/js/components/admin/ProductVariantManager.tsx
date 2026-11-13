import { AnimatePresence } from 'framer-motion';
import { ChevronLeft, ChevronRight, Star, Trash2, Wand2 } from 'lucide-react';
import { useState } from 'react';
import { AdminFade, AdminReveal } from '@/components/admin/admin-motion';
import { CharCount } from '@/components/ui/char-count';
import { belowCostWarning } from '@/lib/price-warnings';
import { cn } from '@/lib/utils';

type BulkFields = {
    price: string;
    compare_price: string;
    cost_price: string;
    stock_quantity: string;
    low_stock_threshold: string;
};

const BLANK_BULK_FIELDS: BulkFields = {
    price: '',
    compare_price: '',
    cost_price: '',
    stock_quantity: '',
    low_stock_threshold: '',
};

/** Above this, the combination checklist asks the admin to narrow the values. */
const MAX_LISTED_COMBINATIONS = 200;

export type VariantRow = {
    /** One value per variant attribute (e.g. Red, 12pcs), in any order. */
    value_ids: number[];
    sku: string;
    price: string;
    compare_price: string;
    cost_price: string;
    stock_quantity: string;
    low_stock_threshold: string;
    is_default: boolean;
};

export type VariantAttributeValueOption = {
    id: number;
    value: string;
    color_hex: string | null;
};

/** An attribute products can build variants from (Color, Size, Set, …). */
export type VariantAttributeOption = {
    id: number;
    name: string;
    type: 'select' | 'color' | 'text';
    has_images: boolean;
    values: VariantAttributeValueOption[];
};

export type VariantSetup = {
    variant_attribute_ids: number[];
    variants: VariantRow[];
};

type Props = {
    attributes: VariantAttributeOption[];
    selectedAttributeIds: number[];
    variants: VariantRow[];
    /** Attribute list and variants always change together. */
    onChange: (setup: VariantSetup) => void;
    errors?: Record<string, string>;
};

function VariantField({
    label,
    warning,
    children,
}: {
    label: string;
    /** Advisory only (amber), e.g. a price below this variant's cost. */
    warning?: string | null;
    children: React.ReactNode;
}): React.JSX.Element {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-600">
                {label}
            </span>
            {children}
            {warning && (
                <span className="mt-1 block text-xs text-amber-600">
                    {warning}
                </span>
            )}
        </label>
    );
}

function blankVariant(valueIds: number[]): VariantRow {
    return {
        value_ids: valueIds,
        sku: '',
        price: '',
        compare_price: '',
        cost_price: '',
        stock_quantity: '0',
        low_stock_threshold: '5',
        is_default: false,
    };
}

/** The same combination gives the same key whatever order its values are in. */
export function combinationKey(valueIds: number[]): string {
    return [...valueIds].sort((a, b) => a - b).join('-');
}

/** Ensures exactly one row is marked default whenever the set is non-empty,
 *  defaulting to the first row if the previous default got removed. */
function ensureDefault(rows: VariantRow[]): VariantRow[] {
    if (rows.length === 0 || rows.some((row) => row.is_default)) {
        return rows;
    }

    return rows.map((row, i) => (i === 0 ? { ...row, is_default: true } : row));
}

/** Keeps the first row of each combination (e.g. after removing an attribute). */
function dedupe(rows: VariantRow[]): VariantRow[] {
    const seen = new Set<string>();

    return rows.filter((row) => {
        const key = combinationKey(row.value_ids);

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}

/** Every combination of one value from each list, in list order. */
function cartesian(lists: number[][]): number[][] {
    return lists.reduce<number[][]>(
        (combos, list) =>
            combos.flatMap((combo) => list.map((id) => [...combo, id])),
        [[]],
    );
}

export function ProductVariantManager({
    attributes,
    selectedAttributeIds,
    variants,
    onChange,
    errors,
}: Props): React.JSX.Element {
    const attributeById = new Map(attributes.map((a) => [a.id, a]));
    const selectedAttributes = selectedAttributeIds
        .map((id) => attributeById.get(id))
        .filter((a): a is VariantAttributeOption => a !== undefined);
    const attributeOfValue = new Map<number, VariantAttributeOption>(
        attributes.flatMap((a) => a.values.map((v) => [v.id, a] as const)),
    );
    const valueById = new Map(
        attributes.flatMap((a) => a.values.map((v) => [v.id, v] as const)),
    );

    // Candidate values are which values are "in play" per attribute. With
    // two or more attributes, picking values creates no variant by itself:
    // the admin ticks which combinations exist in the checklist below.
    const [candidates, setCandidates] = useState<Record<number, number[]>>(() =>
        Object.fromEntries(
            selectedAttributeIds.map((attributeId) => [
                attributeId,
                Array.from(
                    new Set(
                        variants
                            .map((row) =>
                                row.value_ids.find(
                                    (id) =>
                                        attributeOfValue.get(id)?.id ===
                                        attributeId,
                                ),
                            )
                            .filter((id): id is number => id !== undefined),
                    ),
                ),
            ]),
        ),
    );
    const [bulkFields, setBulkFields] = useState<BulkFields>(BLANK_BULK_FIELDS);

    const multiMode = selectedAttributes.length >= 2;
    const candidatesOf = (attributeId: number): number[] =>
        candidates[attributeId] ?? [];

    const emit = (attributeIds: number[], rows: VariantRow[]) =>
        onChange({
            variant_attribute_ids: attributeIds,
            variants: ensureDefault(rows),
        });

    /** With a single attribute every candidate value is a variant. */
    const withEveryCandidate = (
        attributeIds: number[],
        rows: VariantRow[],
        nextCandidates: Record<number, number[]>,
    ): VariantRow[] => {
        if (attributeIds.length !== 1) {
            return rows;
        }

        const existing = new Set(
            rows.map((row) => combinationKey(row.value_ids)),
        );

        return [
            ...rows,
            ...(nextCandidates[attributeIds[0]] ?? [])
                .filter((id) => !existing.has(combinationKey([id])))
                .map((id) => blankVariant([id])),
        ];
    };

    const toggleAttribute = (attribute: VariantAttributeOption) => {
        if (selectedAttributeIds.includes(attribute.id)) {
            // Removing an attribute strips its value from every variant;
            // variants that become identical are merged (first one kept).
            const nextIds = selectedAttributeIds.filter(
                (id) => id !== attribute.id,
            );
            const nextCandidates = { ...candidates };
            delete nextCandidates[attribute.id];
            setCandidates(nextCandidates);

            const stripped =
                nextIds.length === 0
                    ? []
                    : dedupe(
                          variants.map((row) => ({
                              ...row,
                              value_ids: row.value_ids.filter(
                                  (id) =>
                                      attributeOfValue.get(id)?.id !==
                                      attribute.id,
                              ),
                          })),
                      );

            emit(
                nextIds,
                withEveryCandidate(nextIds, stripped, nextCandidates),
            );

            return;
        }

        // Adding an attribute gives every existing variant its first value,
        // so prices and stock already entered are kept.
        const firstValue = attribute.values[0]?.id;
        const nextIds = [...selectedAttributeIds, attribute.id];
        const nextCandidates = {
            ...candidates,
            [attribute.id]: firstValue === undefined ? [] : [firstValue],
        };
        setCandidates(nextCandidates);

        const extended =
            firstValue === undefined
                ? variants
                : variants.map((row) => ({
                      ...row,
                      value_ids: [...row.value_ids, firstValue],
                  }));

        emit(nextIds, withEveryCandidate(nextIds, extended, nextCandidates));
    };

    const moveAttribute = (attributeId: number, direction: -1 | 1) => {
        const index = selectedAttributeIds.indexOf(attributeId);
        const target = index + direction;

        if (
            index === -1 ||
            target < 0 ||
            target >= selectedAttributeIds.length
        ) {
            return;
        }

        const nextIds = [...selectedAttributeIds];
        [nextIds[index], nextIds[target]] = [nextIds[target], nextIds[index]];
        emit(nextIds, variants);
    };

    const toggleValue = (attributeId: number, valueId: number) => {
        const current = candidatesOf(attributeId);
        const isActive = current.includes(valueId);
        const nextCandidates = {
            ...candidates,
            [attributeId]: isActive
                ? current.filter((id) => id !== valueId)
                : [...current, valueId],
        };
        setCandidates(nextCandidates);

        if (isActive) {
            // Dropping a value drops every variant using it.
            emit(
                selectedAttributeIds,
                variants.filter((row) => !row.value_ids.includes(valueId)),
            );

            return;
        }

        emit(
            selectedAttributeIds,
            withEveryCandidate(selectedAttributeIds, variants, nextCandidates),
        );
    };

    const combinations = multiMode
        ? cartesian(selectedAttributes.map((a) => candidatesOf(a.id)))
        : [];
    const existingKeys = new Set(
        variants.map((row) => combinationKey(row.value_ids)),
    );

    const toggleCombination = (valueIds: number[]) => {
        const key = combinationKey(valueIds);

        emit(
            selectedAttributeIds,
            existingKeys.has(key)
                ? variants.filter(
                      (row) => combinationKey(row.value_ids) !== key,
                  )
                : [...variants, blankVariant(valueIds)],
        );
    };

    const selectAllCombinations = () =>
        emit(selectedAttributeIds, [
            ...variants,
            ...combinations
                .filter((combo) => !existingKeys.has(combinationKey(combo)))
                .map((combo) => blankVariant(combo)),
        ]);

    const updateRow = (index: number, patch: Partial<VariantRow>) => {
        emit(
            selectedAttributeIds,
            variants.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );
    };

    const setDefault = (index: number) => {
        onChange({
            variant_attribute_ids: selectedAttributeIds,
            variants: variants.map((row, i) => ({
                ...row,
                is_default: i === index,
            })),
        });
    };

    const removeRow = (index: number) => {
        emit(
            selectedAttributeIds,
            variants.filter((_, i) => i !== index),
        );
    };

    /** Only fields actually filled in the bulk panel are applied — a blank
     *  bulk field leaves every variant's existing value for it untouched.
     *  Filled ones overwrite every variant unconditionally, including ones
     *  already customized — predictable over "smart" partial merging. */
    const applyBulkFields = () => {
        const patch = Object.fromEntries(
            Object.entries(bulkFields).filter(([, value]) => value !== ''),
        ) as Partial<VariantRow>;

        if (Object.keys(patch).length === 0) {
            return;
        }

        emit(
            selectedAttributeIds,
            variants.map((row) => ({ ...row, ...patch })),
        );
    };

    /** "Red / 12pcs" in the product's attribute order. */
    const comboLabel = (valueIds: number[]): string =>
        selectedAttributes
            .map((attribute) => {
                const valueId = valueIds.find(
                    (id) => attributeOfValue.get(id)?.id === attribute.id,
                );

                return valueId === undefined
                    ? '—'
                    : (valueById.get(valueId)?.value ?? '—');
            })
            .join(' / ');

    /** The swatch of a colour-type value in the combination, if any. */
    const comboSwatch = (valueIds: number[]): string | null => {
        for (const id of valueIds) {
            if (attributeOfValue.get(id)?.type === 'color') {
                return valueById.get(id)?.color_hex ?? null;
            }
        }

        return null;
    };

    const fieldInputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    return (
        <div className="space-y-4">
            <div>
                <p className="mb-1.5 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                    Variant attributes
                </p>
                <p className="mb-2 text-xs text-slate-600">
                    Choose what this product comes in (e.g. Color, Size, Set).
                    Manage attributes and their values under Catalog ›
                    Attributes.
                </p>
                <div className="flex flex-wrap gap-2">
                    {attributes.map((attribute) => {
                        const active = selectedAttributeIds.includes(
                            attribute.id,
                        );

                        return (
                            <label
                                key={attribute.id}
                                className={cn(
                                    'flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition',
                                    active
                                        ? 'border-[#c8102e] bg-[#c8102e]/5 text-[#c8102e]'
                                        : 'border-slate-200 text-slate-700 hover:border-slate-300',
                                    attribute.values.length === 0 &&
                                        !active &&
                                        'opacity-50',
                                )}
                                title={
                                    attribute.values.length === 0
                                        ? 'Add values to this attribute first'
                                        : undefined
                                }
                            >
                                <input
                                    type="checkbox"
                                    checked={active}
                                    disabled={
                                        !active && attribute.values.length === 0
                                    }
                                    onChange={() => toggleAttribute(attribute)}
                                    className="size-4 rounded border-slate-300 text-[#c8102e] focus:ring-[#c8102e]"
                                />
                                {attribute.name}
                            </label>
                        );
                    })}
                    {attributes.length === 0 && (
                        <p className="text-xs text-slate-600">
                            No attributes yet — add some under Catalog ›
                            Attributes.
                        </p>
                    )}
                </div>
            </div>

            <AnimatePresence initial={false}>
                {selectedAttributes.map((attribute, position) => (
                    <AdminReveal key={`attribute-${attribute.id}`}>
                        <div>
                            <div className="mb-1.5 flex items-center gap-2">
                                <p className="text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                    {attribute.name}
                                </p>
                                {selectedAttributes.length > 1 && (
                                    <span className="flex items-center gap-0.5">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                moveAttribute(attribute.id, -1)
                                            }
                                            disabled={position === 0}
                                            title="Show earlier"
                                            className="rounded p-0.5 text-slate-400 hover:text-slate-700 disabled:opacity-30"
                                        >
                                            <ChevronLeft className="size-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                moveAttribute(attribute.id, 1)
                                            }
                                            disabled={
                                                position ===
                                                selectedAttributes.length - 1
                                            }
                                            title="Show later"
                                            className="rounded p-0.5 text-slate-400 hover:text-slate-700 disabled:opacity-30"
                                        >
                                            <ChevronRight className="size-3.5" />
                                        </button>
                                    </span>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {attribute.values.map((value) => {
                                    const active = candidatesOf(
                                        attribute.id,
                                    ).includes(value.id);

                                    return (
                                        <button
                                            key={value.id}
                                            type="button"
                                            onClick={() =>
                                                toggleValue(
                                                    attribute.id,
                                                    value.id,
                                                )
                                            }
                                            className={cn(
                                                'flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
                                                active
                                                    ? 'border-[#c8102e] bg-[#c8102e]/5 text-[#c8102e]'
                                                    : 'border-slate-200 text-slate-600 hover:border-slate-300',
                                            )}
                                        >
                                            {attribute.type === 'color' && (
                                                <span
                                                    className="size-3 rounded-full border border-black/10"
                                                    style={{
                                                        backgroundColor:
                                                            value.color_hex ||
                                                            '#ccc',
                                                    }}
                                                />
                                            )}
                                            {value.value}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </AdminReveal>
                ))}
            </AnimatePresence>

            <AnimatePresence initial={false}>
                {multiMode && combinations.length > 0 && (
                    <AdminReveal key="combinations">
                        <div>
                            <div className="mb-1 flex items-center justify-between gap-3">
                                <p className="text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                    Combinations
                                </p>
                                {combinations.length <=
                                    MAX_LISTED_COMBINATIONS && (
                                    <button
                                        type="button"
                                        onClick={selectAllCombinations}
                                        className="text-xs font-medium text-[#c8102e] hover:underline"
                                    >
                                        Select all {combinations.length}
                                    </button>
                                )}
                            </div>
                            <p className="mb-2 text-xs text-slate-600">
                                Tick the combinations you actually stock.
                            </p>
                            {combinations.length > MAX_LISTED_COMBINATIONS ? (
                                <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                    {combinations.length} combinations — pick
                                    fewer values above to list them.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2 rounded-xl border border-slate-200 p-3">
                                    {combinations.map((combo) => {
                                        const key = combinationKey(combo);
                                        const checked = existingKeys.has(key);
                                        const locked =
                                            checked && variants.length <= 1;
                                        const swatch = comboSwatch(combo);

                                        return (
                                            <label
                                                key={key}
                                                title={
                                                    locked
                                                        ? "Can't remove the only remaining combination here"
                                                        : undefined
                                                }
                                                className={cn(
                                                    'flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs transition',
                                                    checked
                                                        ? 'border-[#c8102e]/40 bg-[#c8102e]/5 text-slate-800'
                                                        : 'border-slate-200 text-slate-600',
                                                )}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    disabled={locked}
                                                    onChange={() =>
                                                        toggleCombination(combo)
                                                    }
                                                    className="size-3.5 rounded border-slate-300 text-[#c8102e] focus:ring-[#c8102e] disabled:opacity-40"
                                                />
                                                {swatch && (
                                                    <span
                                                        className="size-3 shrink-0 rounded-full border border-black/10"
                                                        style={{
                                                            backgroundColor:
                                                                swatch,
                                                        }}
                                                    />
                                                )}
                                                {comboLabel(combo)}
                                            </label>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </AdminReveal>
                )}
            </AnimatePresence>

            <AnimatePresence initial={false}>
                {variants.length > 1 && (
                    <AdminReveal key="bulk-fill">
                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <div className="mb-3 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                <Wand2 className="size-3.5" />
                                Bulk-fill variants
                            </div>
                            <p className="mb-3 text-xs text-slate-600">
                                Fill in whichever fields are the same across
                                every variant, then apply — fields left blank
                                here won't change anything. SKU is excluded
                                since it needs to stay unique per variant.
                            </p>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {(
                                    [
                                        ['price', 'Price (৳)', '0.01'],
                                        [
                                            'compare_price',
                                            'Compare price (৳)',
                                            '0.01',
                                        ],
                                        [
                                            'cost_price',
                                            'Cost price (৳)',
                                            '0.01',
                                        ],
                                        ['stock_quantity', 'Stock qty', '1'],
                                        [
                                            'low_stock_threshold',
                                            'Low stock alert',
                                            '1',
                                        ],
                                    ] as const
                                ).map(([field, label, step]) => (
                                    <VariantField key={field} label={label}>
                                        <input
                                            max={
                                                field === 'stock_quantity' ||
                                                field === 'low_stock_threshold'
                                                    ? 9999999
                                                    : 99999999.99
                                            }
                                            type="number"
                                            min="0"
                                            step={step}
                                            placeholder="Unchanged"
                                            className={fieldInputClass}
                                            value={bulkFields[field]}
                                            onChange={(e) =>
                                                setBulkFields({
                                                    ...bulkFields,
                                                    [field]: e.target.value,
                                                })
                                            }
                                        />
                                    </VariantField>
                                ))}
                            </div>
                            <button
                                type="button"
                                onClick={applyBulkFields}
                                className="mt-3 rounded-lg bg-[#c8102e] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#a50d25]"
                            >
                                Apply to all {variants.length} variants
                            </button>
                        </div>
                    </AdminReveal>
                )}
            </AnimatePresence>

            <AnimatePresence initial={false}>
                {variants.length > 0 && (
                    <AdminReveal key="variant-rows">
                        <div className="space-y-3">
                            <AnimatePresence initial={false}>
                                {variants.map((row, index) => {
                                    const swatch = comboSwatch(row.value_ids);

                                    return (
                                        <AdminReveal
                                            key={combinationKey(row.value_ids)}
                                        >
                                            <div
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
                                                                    backgroundColor:
                                                                        swatch,
                                                                }}
                                                            />
                                                        )}
                                                        <span className="text-sm font-semibold text-slate-800">
                                                            {comboLabel(
                                                                row.value_ids,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-3">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setDefault(
                                                                    index,
                                                                )
                                                            }
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
                                                        <AnimatePresence
                                                            initial={false}
                                                        >
                                                            {variants.length >
                                                                1 && (
                                                                <AdminFade key="remove-variant">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            removeRow(
                                                                                index,
                                                                            )
                                                                        }
                                                                        title="Remove variant"
                                                                        className="text-slate-400 hover:text-red-500"
                                                                    >
                                                                        <Trash2 className="size-4" />
                                                                    </button>
                                                                </AdminFade>
                                                            )}
                                                        </AnimatePresence>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                    <VariantField label="SKU">
                                                        <input
                                                            maxLength={50}
                                                            className={
                                                                fieldInputClass
                                                            }
                                                            value={row.sku}
                                                            onChange={(e) =>
                                                                updateRow(
                                                                    index,
                                                                    {
                                                                        sku: e
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <CharCount
                                                            value={row.sku}
                                                            max={50}
                                                            className="mt-1 block text-right"
                                                        />
                                                    </VariantField>
                                                    <VariantField
                                                        label="Price (৳)"
                                                        warning={belowCostWarning(
                                                            row.price,
                                                            row.cost_price,
                                                        )}
                                                    >
                                                        <input
                                                            max={99999999.99}
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                            value={row.price}
                                                            onChange={(e) =>
                                                                updateRow(
                                                                    index,
                                                                    {
                                                                        price: e
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </VariantField>
                                                    <VariantField label="Compare price (৳)">
                                                        <input
                                                            max={99999999.99}
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                            value={
                                                                row.compare_price
                                                            }
                                                            onChange={(e) =>
                                                                updateRow(
                                                                    index,
                                                                    {
                                                                        compare_price:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </VariantField>
                                                    <VariantField label="Cost price (৳)">
                                                        <input
                                                            max={99999999.99}
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                            value={
                                                                row.cost_price
                                                            }
                                                            onChange={(e) =>
                                                                updateRow(
                                                                    index,
                                                                    {
                                                                        cost_price:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </VariantField>
                                                    <VariantField label="Stock qty">
                                                        <input
                                                            max={9999999}
                                                            type="number"
                                                            min="0"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                            value={
                                                                row.stock_quantity
                                                            }
                                                            onChange={(e) =>
                                                                updateRow(
                                                                    index,
                                                                    {
                                                                        stock_quantity:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </VariantField>
                                                    <VariantField label="Low stock alert">
                                                        <input
                                                            max={9999999}
                                                            type="number"
                                                            min="0"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                            value={
                                                                row.low_stock_threshold
                                                            }
                                                            onChange={(e) =>
                                                                updateRow(
                                                                    index,
                                                                    {
                                                                        low_stock_threshold:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </VariantField>
                                                </div>
                                            </div>
                                        </AdminReveal>
                                    );
                                })}
                            </AnimatePresence>
                            <AnimatePresence initial={false}>
                                {variants.length === 1 && (
                                    <AdminReveal key="only-variant">
                                        <p className="text-xs text-slate-600">
                                            This is the only variant, so it
                                            can't be removed directly — deselect
                                            its values above, or untick the
                                            attributes, to turn variants off.
                                        </p>
                                    </AdminReveal>
                                )}
                            </AnimatePresence>
                        </div>
                    </AdminReveal>
                )}
            </AnimatePresence>

            {errors?.variants && (
                <p className="text-xs text-red-500">{errors.variants}</p>
            )}
            {errors &&
                Object.entries(errors)
                    .filter(([key]) => /^variants\.\d+$/.test(key))
                    .map(([key, message]) => (
                        <p key={key} className="text-xs text-red-500">
                            {message}
                        </p>
                    ))}
        </div>
    );
}
