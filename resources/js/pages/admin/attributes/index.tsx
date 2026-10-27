import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    Edit,
    GripVertical,
    Palette,
    Plus,
    Ruler,
    Tags,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Modal } from '@/components/ui/modal';
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

type DragHandlers = {
    draggable: true;
    onDragStart: () => void;
    onDragOver: (e: React.DragEvent) => void;
    onDrop: () => void;
    onDragEnd: () => void;
};

function ValueChip({
    attributeValue,
    isColor,
    reorderMode,
    isDragging,
    dragHandlers,
    onEdit,
    onDelete,
}: {
    attributeValue: AttributeValue;
    isColor: boolean;
    reorderMode: boolean;
    isDragging: boolean;
    dragHandlers: DragHandlers;
    onEdit: () => void;
    onDelete: () => void;
}) {
    if (reorderMode) {
        return (
            <div
                {...dragHandlers}
                className={cn(
                    'flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1.5 pr-3.5 pl-2 shadow-sm',
                    isDragging && 'opacity-40',
                )}
            >
                <GripVertical className="size-3.5 shrink-0 cursor-grab text-slate-300 active:cursor-grabbing" />
                {isColor && (
                    <span
                        className="size-5 shrink-0 rounded-full border border-slate-200 shadow-inner"
                        style={{
                            backgroundColor: attributeValue.color_hex || '#ccc',
                        }}
                    />
                )}
                <span className="text-sm font-medium text-slate-700">
                    {attributeValue.value}
                </span>
            </div>
        );
    }

    return (
        <div className="group flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1.5 pr-1.5 pl-2.5 shadow-sm transition-shadow hover:shadow-md">
            {isColor && (
                <span
                    className="size-5 shrink-0 rounded-full border border-slate-200 shadow-inner"
                    style={{
                        backgroundColor: attributeValue.color_hex || '#ccc',
                    }}
                />
            )}
            <span className="text-sm font-medium text-slate-700">
                {attributeValue.value}
            </span>
            {isColor && attributeValue.color_hex && (
                <span className="font-mono text-[10px] text-slate-400">
                    {attributeValue.color_hex.toUpperCase()}
                </span>
            )}
            <div className="ml-0.5 flex items-center gap-0.5 border-l border-slate-100 pl-1">
                <button
                    type="button"
                    onClick={onEdit}
                    className="rounded-full p-1 text-slate-300 transition-colors hover:bg-slate-100 hover:text-slate-600"
                    title="Edit"
                >
                    <Edit className="size-3.5" />
                </button>
                <button
                    type="button"
                    onClick={onDelete}
                    className="rounded-full p-1 text-slate-300 transition-colors hover:bg-red-50 hover:text-red-500"
                    title="Delete"
                >
                    <Trash2 className="size-3.5" />
                </button>
            </div>
        </div>
    );
}

function AttributeCard({
    attribute,
    onAdd,
    onEdit,
    onDelete,
}: {
    attribute: Attribute;
    onAdd: (attribute: Attribute) => void;
    onEdit: (value: AttributeValue) => void;
    onDelete: (id: number) => void;
}) {
    const isColor = attribute.type === 'color';
    const Icon = isColor ? Palette : Ruler;
    const theme = isColor
        ? { bg: 'bg-violet-50', text: 'text-violet-600' }
        : { bg: 'bg-blue-50', text: 'text-blue-600' };

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

    const values = reorderMode ? reorderValues : attribute.values;

    return (
        <Card className="overflow-hidden p-0 shadow-sm">
            <div className="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-xl',
                        theme.bg,
                        theme.text,
                    )}
                >
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="text-sm font-bold text-slate-800">
                        {attribute.name}
                    </h2>
                    <p className="text-xs text-slate-400">
                        {attribute.values.length} value
                        {attribute.values.length === 1 ? '' : 's'}
                    </p>
                </div>
                <div className="flex items-center gap-1.5">
                    {reorderMode ? (
                        <>
                            <Button
                                size="sm"
                                disabled={reordering}
                                onClick={confirmReorder}
                                className="h-8 bg-[#c8102e] px-3 text-xs text-white hover:bg-[#a50d25]"
                            >
                                <Check className="size-3.5" />
                                Save order
                            </Button>
                            <button
                                type="button"
                                disabled={reordering}
                                onClick={cancelReorder}
                                className="flex h-8 items-center gap-1 rounded-lg px-2 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                            >
                                <X className="size-3.5" />
                                Cancel
                            </button>
                        </>
                    ) : (
                        <>
                            {attribute.values.length > 1 && (
                                <button
                                    type="button"
                                    onClick={startReorder}
                                    className="flex h-8 items-center gap-1 rounded-lg px-2 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                    title="Reorder values"
                                >
                                    <GripVertical className="size-3.5" />
                                    Reorder
                                </button>
                            )}
                            <Button
                                size="sm"
                                onClick={() => onAdd(attribute)}
                                className="h-8 bg-[#c8102e] px-3 text-xs text-white hover:bg-[#a50d25]"
                            >
                                <Plus className="size-3.5" />
                                Add
                            </Button>
                        </>
                    )}
                </div>
            </div>

            <div className="p-5">
                {attribute.values.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 py-8 text-center">
                        <Icon className="size-6 text-slate-300" />
                        <p className="text-sm text-slate-400">
                            No {attribute.name.toLowerCase()} values yet.
                        </p>
                        <button
                            type="button"
                            onClick={() => onAdd(attribute)}
                            className="text-xs font-semibold text-[#c8102e] hover:underline"
                        >
                            Add the first one
                        </button>
                    </div>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {values.map((value, index) => (
                            <ValueChip
                                key={value.id}
                                attributeValue={value}
                                isColor={isColor}
                                reorderMode={reorderMode}
                                isDragging={draggingId === value.id}
                                onEdit={() => onEdit(value)}
                                onDelete={() => onDelete(value.id)}
                                dragHandlers={{
                                    draggable: true,
                                    onDragStart: () => {
                                        dragIndexRef.current = index;
                                        setDraggingId(value.id);
                                    },
                                    onDragOver: (e) => e.preventDefault(),
                                    onDrop: () => handleDrop(index),
                                    onDragEnd: () => {
                                        dragIndexRef.current = null;
                                        setDraggingId(null);
                                    },
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </Card>
    );
}

export default function AttributesIndex({ attributes }: Props) {
    const [deleteTargetId, setDeleteTargetId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);

    const [isFormOpen, setIsFormOpen] = useState(false);
    const [formAttribute, setFormAttribute] = useState<Attribute | null>(null);
    const [editingValue, setEditingValue] = useState<AttributeValue | null>(
        null,
    );

    const form = useForm({
        attribute_id: 0,
        value: '',
        color_hex: '#c8102e',
        sort_order: 0,
    });

    const openAdd = (attribute: Attribute) => {
        setFormAttribute(attribute);
        setEditingValue(null);
        form.clearErrors();
        form.setData({
            attribute_id: attribute.id,
            value: '',
            color_hex: '#c8102e',
            sort_order: attribute.values.length,
        });
        setIsFormOpen(true);
    };

    const openEdit = (attribute: Attribute, value: AttributeValue) => {
        setFormAttribute(attribute);
        setEditingValue(value);
        form.clearErrors();
        form.setData({
            attribute_id: attribute.id,
            value: value.value,
            color_hex: value.color_hex ?? '#c8102e',
            sort_order: value.sort_order,
        });
        setIsFormOpen(true);
    };

    const closeForm = () => setIsFormOpen(false);

    const submitForm = () => {
        const clientErrors = validate(form.data, {
            value: [required(), maxLength(100)],
        });

        if (Object.keys(clientErrors).length > 0) {
            toastFirstError(clientErrors);

            return;
        }

        if (editingValue) {
            form.put(route('admin.attribute-values.update', editingValue.id), {
                preserveScroll: true,
                onSuccess: closeForm,
                onError: toastFirstError,
            });
        } else {
            form.post(route('admin.attribute-values.store'), {
                preserveScroll: true,
                onSuccess: closeForm,
                onError: toastFirstError,
            });
        }
    };

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

    const isColor = formAttribute?.type === 'color';

    return (
        <>
            <Head title="Color & Size Attributes — Admin" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#c8102e]/10 text-[#c8102e]">
                            <Tags className="size-5" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900">
                                Attributes
                            </h1>
                            <p className="max-w-md text-sm text-slate-500">
                                Manage the colors and sizes available for
                                product variants. Deleting a value that's
                                already used by a product is blocked.
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {attributes.map((attribute) => (
                            <div
                                key={attribute.id}
                                className="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-center shadow-sm"
                            >
                                <p className="text-lg font-bold text-slate-800">
                                    {attribute.values.length}
                                </p>
                                <p className="text-[11px] font-medium tracking-wide text-slate-400 uppercase">
                                    {attribute.name}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    {attributes.map((attribute) => (
                        <AttributeCard
                            key={attribute.id}
                            attribute={attribute}
                            onAdd={openAdd}
                            onEdit={(value) => openEdit(attribute, value)}
                            onDelete={setDeleteTargetId}
                        />
                    ))}
                </div>
            </div>

            <Modal
                isOpen={isFormOpen}
                onClose={closeForm}
                title={
                    editingValue
                        ? `Edit ${formAttribute?.name ?? ''} value`
                        : `Add ${formAttribute?.name ?? ''} value`
                }
                size="sm"
                footer={
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeForm}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={submitForm}
                            loading={form.processing}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {editingValue ? 'Save changes' : 'Add value'}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-4">
                    <div>
                        <label className="mb-1.5 block text-xs font-semibold text-slate-600">
                            {isColor ? 'Color name' : 'Size label'}
                        </label>
                        <input
                            autoFocus
                            value={form.data.value}
                            onChange={(e) =>
                                form.setData('value', e.target.value)
                            }
                            onKeyDown={(e) => e.key === 'Enter' && submitForm()}
                            placeholder={isColor ? 'e.g. Red' : 'e.g. XL'}
                            className="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm focus:border-[#c8102e] focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                        />
                    </div>
                    {isColor && (
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold text-slate-600">
                                Swatch color
                            </label>
                            <div className="flex items-center gap-3">
                                <input
                                    type="color"
                                    value={form.data.color_hex}
                                    onChange={(e) =>
                                        form.setData(
                                            'color_hex',
                                            e.target.value,
                                        )
                                    }
                                    className="size-10 shrink-0 cursor-pointer rounded-lg border border-slate-200"
                                />
                                <div className="rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs text-slate-500">
                                    {form.data.color_hex.toUpperCase()}
                                    {hexToRgb(form.data.color_hex) && (
                                        <span className="ml-1.5 text-slate-400">
                                            · {hexToRgb(form.data.color_hex)}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </Modal>

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
