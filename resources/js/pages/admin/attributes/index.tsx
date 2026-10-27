import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    Edit,
    GripVertical,
    Palette,
    Plus,
    Ruler,
    Save,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { cn } from '@/lib/utils';
import { maxLength, required, validate } from '@/lib/validate';

type AttributeValue = {
    id: number;
    attribute_id: number;
    value: string;
    color_hex: string | null;
    sort_order: number;
};

type Attribute = {
    id: number;
    name: string;
    type: 'select' | 'color' | 'text';
    values: AttributeValue[];
};

type Props = { attributes: Attribute[] };

/**
 * Accepts 3- or 6-digit hex (with or without "#"); returns null for
 * anything else rather than guessing, since this only ever feeds a
 * read-only label next to the swatch.
 */
function hexToRgb(hex: string): string | null {
    const normalized = hex.replace('#', '').trim();
    const full =
        normalized.length === 3
            ? normalized
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : normalized;

    if (!/^[0-9a-fA-F]{6}$/.test(full)) {
        return null;
    }

    const r = parseInt(full.slice(0, 2), 16);
    const g = parseInt(full.slice(2, 4), 16);
    const b = parseInt(full.slice(4, 6), 16);

    return `rgb(${r}, ${g}, ${b})`;
}

/** Shows the first validation error (client or server) as a toast. */
function toastFirstError(errors: Record<string, string | string[]>) {
    const first = Object.values(errors)[0];

    if (!first) {
        return;
    }

    toast.error(Array.isArray(first) ? first[0] : first);
}

function ValueRow({
    attributeValue,
    isColor,
    onDelete,
    reorderMode,
    isDragging,
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
}: {
    attributeValue: AttributeValue;
    isColor: boolean;
    onDelete: (id: number) => void;
    reorderMode: boolean;
    isDragging: boolean;
    onDragStart: () => void;
    onDragOver: (e: React.DragEvent) => void;
    onDrop: () => void;
    onDragEnd: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing } = useForm({
        attribute_id: attributeValue.attribute_id,
        value: attributeValue.value,
        color_hex: attributeValue.color_hex ?? '#000000',
    });

    const save = () => {
        const clientErrors = validate(data, {
            value: [required(), maxLength(100)],
        });

        if (Object.keys(clientErrors).length > 0) {
            toastFirstError(clientErrors);

            return;
        }

        put(route('admin.attribute-values.update', attributeValue.id), {
            onSuccess: () => setEditing(false),
            onError: toastFirstError,
        });
    };

    const inputClass =
        'h-8 rounded-lg border border-slate-200 px-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    if (reorderMode) {
        return (
            <div
                draggable
                onDragStart={onDragStart}
                onDragOver={onDragOver}
                onDrop={onDrop}
                onDragEnd={onDragEnd}
                className={cn(
                    'flex items-center gap-3 px-4 py-2.5',
                    isDragging && 'opacity-40',
                )}
            >
                <GripVertical className="size-4 shrink-0 cursor-grab text-slate-300 active:cursor-grabbing" />
                {isColor && (
                    <span
                        className="size-5 shrink-0 rounded-full border border-slate-200 shadow-inner"
                        style={{
                            backgroundColor: attributeValue.color_hex || '#ccc',
                        }}
                    />
                )}
                <span className="flex-1 text-sm font-medium text-slate-800">
                    {attributeValue.value}
                </span>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50/60">
            {isColor && (
                <span
                    className="size-5 shrink-0 rounded-full border border-slate-200 shadow-inner"
                    style={{
                        backgroundColor: editing
                            ? data.color_hex
                            : attributeValue.color_hex || '#ccc',
                    }}
                />
            )}

            {editing ? (
                <>
                    <div className="flex-1">
                        <input
                            autoFocus
                            className={`${inputClass} w-full`}
                            value={data.value}
                            onChange={(e) => setData('value', e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && save()}
                        />
                    </div>
                    {isColor && (
                        <input
                            type="color"
                            className="size-8 shrink-0 cursor-pointer rounded border border-slate-200"
                            value={data.color_hex}
                            onChange={(e) =>
                                setData('color_hex', e.target.value)
                            }
                            title="Swatch color"
                        />
                    )}
                    <Button
                        size="sm"
                        disabled={processing}
                        onClick={save}
                        className="shrink-0 bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        <Save className="size-3.5" />
                    </Button>
                    <button
                        type="button"
                        onClick={() => setEditing(false)}
                        className="shrink-0 text-xs text-slate-400 hover:text-slate-600"
                    >
                        Cancel
                    </button>
                </>
            ) : (
                <>
                    <div className="flex-1 text-left">
                        <span className="text-sm font-medium text-slate-800">
                            {attributeValue.value}
                        </span>
                        {isColor && attributeValue.color_hex && (
                            <p className="font-mono text-xs text-slate-400">
                                {attributeValue.color_hex.toUpperCase()}
                                {hexToRgb(attributeValue.color_hex) &&
                                    ` · ${hexToRgb(attributeValue.color_hex)}`}
                            </p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        title="Edit"
                    >
                        <Edit className="size-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => onDelete(attributeValue.id)}
                        className="shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                        title="Delete"
                    >
                        <Trash2 className="size-4" />
                    </button>
                </>
            )}
        </div>
    );
}

function AttributeSection({
    attribute,
    onDelete,
}: {
    attribute: Attribute;
    onDelete: (id: number) => void;
}) {
    const isColor = attribute.type === 'color';
    const { data, setData, post, processing, reset } = useForm({
        attribute_id: attribute.id,
        value: '',
        color_hex: '#c8102e',
        sort_order: attribute.values.length,
    });

    const [reorderMode, setReorderMode] = useState(false);
    const [reorderValues, setReorderValues] = useState(attribute.values);
    const [reordering, setReordering] = useState(false);
    const [draggingId, setDraggingId] = useState<number | null>(null);
    const dragIndexRef = useRef<number | null>(null);

    const startReorder = () => {
        setReorderValues(attribute.values);
        setReorderMode(true);
    };

    const cancelReorder = () => {
        setReorderMode(false);
        setReorderValues(attribute.values);
    };

    const confirmReorder = () => {
        setReordering(true);
        router.post(
            route('admin.attribute-values.reorder'),
            { ids: reorderValues.map((v) => v.id) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setReordering(false);
                    setReorderMode(false);
                },
            },
        );
    };

    const handleDrop = (dropIndex: number) => {
        const dragIndex = dragIndexRef.current;
        dragIndexRef.current = null;
        setDraggingId(null);

        if (dragIndex === null || dragIndex === dropIndex) {
            return;
        }

        const reordered = [...reorderValues];
        const [moved] = reordered.splice(dragIndex, 1);
        reordered.splice(dropIndex, 0, moved);
        setReorderValues(reordered);
    };

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();

        const clientErrors = validate(data, {
            value: [required(), maxLength(100)],
        });

        if (Object.keys(clientErrors).length > 0) {
            toastFirstError(clientErrors);

            return;
        }

        post(route('admin.attribute-values.store'), {
            onSuccess: () => {
                reset('value', 'color_hex');
                setData('sort_order', attribute.values.length + 1);
            },
            onError: toastFirstError,
        });
    };

    return (
        <Card className="overflow-hidden p-0 shadow-sm">
            <div className="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                {isColor ? (
                    <Palette className="size-4 text-slate-400" />
                ) : (
                    <Ruler className="size-4 text-slate-400" />
                )}
                <h2 className="text-sm font-semibold text-slate-700">
                    {attribute.name}
                </h2>
                <span className="text-xs text-slate-400">
                    {attribute.values.length} value
                    {attribute.values.length === 1 ? '' : 's'}
                </span>
                <div className="ml-auto flex items-center gap-1.5">
                    {reorderMode ? (
                        <>
                            <Button
                                size="sm"
                                disabled={reordering}
                                onClick={confirmReorder}
                                className="h-7 bg-[#c8102e] px-2.5 text-white hover:bg-[#a50d25]"
                            >
                                <Check className="size-3.5" />
                                Confirm
                            </Button>
                            <button
                                type="button"
                                disabled={reordering}
                                onClick={cancelReorder}
                                className="flex h-7 items-center gap-1 rounded-lg px-2 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                            >
                                <X className="size-3.5" />
                                Cancel
                            </button>
                        </>
                    ) : (
                        attribute.values.length > 1 && (
                            <button
                                type="button"
                                onClick={startReorder}
                                className="flex h-7 items-center gap-1 rounded-lg px-2 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                            >
                                <GripVertical className="size-3.5" />
                                Reorder
                            </button>
                        )
                    )}
                </div>
            </div>

            {attribute.values.length === 0 ? (
                <div className="px-4 py-6 text-center text-sm text-slate-400">
                    No {attribute.name.toLowerCase()} values yet.
                </div>
            ) : (
                <div className="divide-y divide-slate-50">
                    {(reorderMode ? reorderValues : attribute.values).map(
                        (value, index) => (
                            <ValueRow
                                key={value.id}
                                attributeValue={value}
                                isColor={isColor}
                                onDelete={onDelete}
                                reorderMode={reorderMode}
                                isDragging={draggingId === value.id}
                                onDragStart={() => {
                                    dragIndexRef.current = index;
                                    setDraggingId(value.id);
                                }}
                                onDragOver={(e) => e.preventDefault()}
                                onDrop={() => handleDrop(index)}
                                onDragEnd={() => {
                                    dragIndexRef.current = null;
                                    setDraggingId(null);
                                }}
                            />
                        ),
                    )}
                </div>
            )}

            {!reorderMode && (
                <>
                    <form
                        onSubmit={handleAdd}
                        className="flex items-center gap-2 border-t border-slate-100 bg-slate-50/50 px-4 py-3"
                    >
                        <input
                            className="h-9 flex-1 rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                            value={data.value}
                            onChange={(e) => setData('value', e.target.value)}
                            placeholder={isColor ? 'e.g. Red' : 'e.g. M'}
                        />
                        {isColor && (
                            <input
                                type="color"
                                className="h-9 w-10 shrink-0 cursor-pointer rounded-lg border border-slate-200"
                                value={data.color_hex}
                                onChange={(e) =>
                                    setData('color_hex', e.target.value)
                                }
                                title="Swatch color"
                            />
                        )}
                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing || !data.value.trim()}
                            className="shrink-0 bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            <Plus className="size-4" />
                            Add
                        </Button>
                    </form>
                </>
            )}
        </Card>
    );
}

export default function AttributesIndex({ attributes }: Props) {
    const [deleteTargetId, setDeleteTargetId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (deleteTargetId === null) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.attribute-values.destroy', deleteTargetId), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setDeleteTargetId(null);
            },
        });
    };

    return (
        <>
            <Head title="Color & Size Attributes — Admin" />
            <div className="max-w-xl space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Color & Size Attributes
                    </h1>
                    <p className="text-sm text-slate-500">
                        Manage the colors and sizes available for product
                        variants. Deleting a value that's already used by a
                        product is blocked.
                    </p>
                </div>

                {attributes.map((attribute) => (
                    <AttributeSection
                        key={attribute.id}
                        attribute={attribute}
                        onDelete={setDeleteTargetId}
                    />
                ))}
            </div>

            <ConfirmDialog
                open={deleteTargetId !== null}
                onOpenChange={(open) => !open && setDeleteTargetId(null)}
                title="Delete value?"
                description="This will permanently delete this value. This cannot be undone."
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </>
    );
}
