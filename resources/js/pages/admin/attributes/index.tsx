import { Head, router, useForm } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { AnimatePresence, motion, MotionConfig } from 'framer-motion';
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
import { AdminFade } from '@/components/admin/admin-motion';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    dialogItem,
    dialogOverlayMotion,
    dialogPanel,
} from '@/lib/dialog-motion';
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

/** Common apparel colors, grouped by hue family so the palette reads in order. */
const COLOR_PRESETS = [
    { name: 'Black', hex: '#111111' },
    { name: 'Charcoal', hex: '#36454f' },
    { name: 'Gray', hex: '#9ca3af' },
    { name: 'Silver', hex: '#c0c0c0' },
    { name: 'White', hex: '#ffffff' },
    { name: 'Off-White', hex: '#faf9f6' },
    { name: 'Cream', hex: '#fffdd0' },
    { name: 'Beige', hex: '#e1c699' },
    { name: 'Khaki', hex: '#c3b091' },
    { name: 'Brown', hex: '#78350f' },
    { name: 'Maroon', hex: '#800000' },
    { name: 'Red', hex: '#c8102e' },
    { name: 'Coral', hex: '#ff7f50' },
    { name: 'Peach', hex: '#ffcba4' },
    { name: 'Baby Pink', hex: '#f4c2c2' },
    { name: 'Pink', hex: '#db2777' },
    { name: 'Rust', hex: '#b7410e' },
    { name: 'Orange', hex: '#ea580c' },
    { name: 'Mustard', hex: '#e1ad01' },
    { name: 'Gold', hex: '#d4af37' },
    { name: 'Yellow', hex: '#facc15' },
    { name: 'Olive', hex: '#708238' },
    { name: 'Bottle Green', hex: '#006a4e' },
    { name: 'Green', hex: '#16a34a' },
    { name: 'Teal', hex: '#008080' },
    { name: 'Turquoise', hex: '#40e0d0' },
    { name: 'Sky Blue', hex: '#87ceeb' },
    { name: 'Blue', hex: '#2563eb' },
    { name: 'Navy', hex: '#1f2a44' },
    { name: 'Lavender', hex: '#b497d6' },
    { name: 'Purple', hex: '#7c3aed' },
];

/**
 * Accepts 3- or 6-digit hex (with or without "#") and returns it as
 * lowercase "#rrggbb" — the format the native color picker emits and the
 * server stores — or null for anything else rather than guessing.
 */
function normalizeHex(hex: string): string | null {
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

    return `#${full.toLowerCase()}`;
}

function hexToRgb(hex: string): string | null {
    const normalized = normalizeHex(hex);

    if (!normalized) {
        return null;
    }

    const full = normalized.slice(1);
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
                <span className="font-mono text-[10px] text-slate-600">
                    {attributeValue.color_hex.toUpperCase()}
                </span>
            )}
            <div className="ml-0.5 flex items-center gap-0.5 border-l border-slate-100 pl-1">
                <button
                    type="button"
                    onClick={onEdit}
                    className="rounded-full p-1 text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    title="Edit"
                >
                    <Edit className="size-3.5" />
                </button>
                <button
                    type="button"
                    onClick={onDelete}
                    className="rounded-full p-1 text-red-500 transition-colors hover:bg-red-50 hover:text-red-600"
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
                    <AnimatePresence initial={false} mode="wait">
                        {reorderMode ? (
                            <AdminFade
                                key="toolbar-a"
                                className="flex items-center gap-1.5"
                            >
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
                            </AdminFade>
                        ) : (
                            <AdminFade
                                key="toolbar-b"
                                className="flex items-center gap-1.5"
                            >
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
                            </AdminFade>
                        )}
                    </AnimatePresence>
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
    // What's typed in the hex field; only pushed into form.color_hex once it
    // parses, so a half-typed value never desyncs the picker or gets saved.
    const [hexDraft, setHexDraft] = useState('#C8102E');
    const valueInputRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        attribute_id: 0,
        value: '',
        color_hex: '#c8102e',
        sort_order: 0,
    });

    const setColorHex = (hex: string) => {
        form.setData('color_hex', hex);
        setHexDraft(hex.toUpperCase());
    };

    /**
     * Also names the color, but only when the name is blank or still a
     * preset name — clicking through presets keeps the name in step, while
     * a name the admin typed themselves is never overwritten.
     */
    const applyPreset = (preset: (typeof COLOR_PRESETS)[number]) => {
        const currentName = form.data.value.trim();
        const isNameReplaceable =
            currentName === '' ||
            COLOR_PRESETS.some((p) => p.name === currentName);

        form.setData((data) => ({
            ...data,
            color_hex: preset.hex,
            value: isNameReplaceable ? preset.name : data.value,
        }));
        setHexDraft(preset.hex.toUpperCase());
    };

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
        setHexDraft('#C8102E');
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
        setHexDraft((value.color_hex ?? '#c8102e').toUpperCase());
        setIsFormOpen(true);
    };

    const closeForm = () => setIsFormOpen(false);

    const submitForm = () => {
        const clientErrors = validate(form.data, {
            value: [required(), maxLength(20)],
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

            <DialogPrimitive.Root
                open={isFormOpen}
                onOpenChange={(open) => !open && closeForm()}
            >
                <AnimatePresence>
                    {isFormOpen && (
                        <DialogPrimitive.Portal forceMount>
                            <MotionConfig reducedMotion="user">
                                <DialogPrimitive.Overlay asChild forceMount>
                                    <motion.div
                                        className="fixed inset-0 z-50 bg-slate-950/40 backdrop-blur-[3px]"
                                        {...dialogOverlayMotion}
                                    />
                                </DialogPrimitive.Overlay>
                                <div className="pointer-events-none fixed inset-0 z-50 flex items-center justify-center p-4">
                                    <DialogPrimitive.Content
                                        asChild
                                        forceMount
                                        onOpenAutoFocus={(e) => {
                                            e.preventDefault();
                                            valueInputRef.current?.focus();
                                        }}
                                    >
                                        <motion.div
                                            variants={dialogPanel}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="pointer-events-auto w-full max-w-[22rem] overflow-hidden rounded-2xl bg-white shadow-[0_24px_64px_-16px_rgba(15,23,42,0.35)] ring-1 ring-slate-900/5"
                                        >
                                            <motion.div
                                                variants={dialogItem}
                                                className="flex items-center gap-3 px-5 pt-5"
                                            >
                                                {isColor ? (
                                                    <motion.span
                                                        className="size-10 shrink-0 rounded-xl shadow-inner ring-1 ring-slate-900/10"
                                                        initial={false}
                                                        animate={{
                                                            backgroundColor:
                                                                form.data
                                                                    .color_hex,
                                                        }}
                                                        transition={{
                                                            duration: 0.25,
                                                        }}
                                                    />
                                                ) : (
                                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#c8102e]/10 text-[#c8102e]">
                                                        <Ruler className="size-5" />
                                                    </span>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <DialogPrimitive.Title className="text-[15px] leading-tight font-semibold text-slate-900">
                                                        {editingValue
                                                            ? 'Edit'
                                                            : 'Add'}{' '}
                                                        {isColor
                                                            ? 'color'
                                                            : 'size'}
                                                    </DialogPrimitive.Title>
                                                    <DialogPrimitive.Description className="mt-0.5 truncate text-xs text-slate-500">
                                                        {isColor
                                                            ? 'Shown as a swatch on product pages.'
                                                            : 'Shown as a size option on product pages.'}
                                                    </DialogPrimitive.Description>
                                                </div>
                                                <DialogPrimitive.Close
                                                    className="flex size-8 shrink-0 items-center justify-center self-start rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-[#c8102e] focus-visible:outline-none"
                                                    aria-label="Close"
                                                >
                                                    <X className="size-4" />
                                                </DialogPrimitive.Close>
                                            </motion.div>

                                            <div className="space-y-3.5 px-5 pt-4 pb-5">
                                                <motion.div
                                                    variants={dialogItem}
                                                >
                                                    <label
                                                        htmlFor="attribute-value-name"
                                                        className="mb-1.5 block text-[11px] font-semibold tracking-wider text-slate-500 uppercase"
                                                    >
                                                        {isColor
                                                            ? 'Color name'
                                                            : 'Size label'}
                                                    </label>
                                                    <input
                                                        id="attribute-value-name"
                                                        ref={valueInputRef}
                                                        value={form.data.value}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'value',
                                                                e.target.value,
                                                            )
                                                        }
                                                        onKeyDown={(e) =>
                                                            e.key === 'Enter' &&
                                                            submitForm()
                                                        }
                                                        placeholder={
                                                            isColor
                                                                ? 'e.g. Red'
                                                                : 'e.g. XL'
                                                        }
                                                        maxLength={20}
                                                        className="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 transition-colors placeholder:text-slate-400 focus:border-[#c8102e] focus:bg-white focus:ring-2 focus:ring-[#c8102e]/15 focus:outline-none"
                                                    />
                                                </motion.div>

                                                {isColor && (
                                                    <motion.div
                                                        variants={dialogItem}
                                                    >
                                                        <label
                                                            htmlFor="attribute-value-hex"
                                                            className="mb-1.5 block text-[11px] font-semibold tracking-wider text-slate-500 uppercase"
                                                        >
                                                            Color
                                                        </label>
                                                        <div
                                                            className={cn(
                                                                'flex h-9 items-center gap-2 rounded-lg border bg-slate-50 pr-3 pl-1 transition-colors focus-within:bg-white focus-within:ring-2',
                                                                normalizeHex(
                                                                    hexDraft,
                                                                )
                                                                    ? 'border-slate-200 focus-within:border-[#c8102e] focus-within:ring-[#c8102e]/15'
                                                                    : 'border-red-400 focus-within:border-red-500 focus-within:ring-red-500/15',
                                                            )}
                                                        >
                                                            <label
                                                                className="relative size-7 shrink-0 cursor-pointer overflow-hidden rounded-md shadow-inner ring-1 ring-slate-900/10"
                                                                style={{
                                                                    backgroundColor:
                                                                        form
                                                                            .data
                                                                            .color_hex,
                                                                }}
                                                                title="Open color picker"
                                                            >
                                                                <input
                                                                    type="color"
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .color_hex
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setColorHex(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    aria-label="Open color picker"
                                                                    className="absolute inset-0 size-full cursor-pointer opacity-0"
                                                                />
                                                            </label>
                                                            <input
                                                                id="attribute-value-hex"
                                                                value={hexDraft}
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    setHexDraft(
                                                                        e.target
                                                                            .value,
                                                                    );
                                                                    const parsed =
                                                                        normalizeHex(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        );

                                                                    if (
                                                                        parsed
                                                                    ) {
                                                                        form.setData(
                                                                            'color_hex',
                                                                            parsed,
                                                                        );
                                                                    }
                                                                }}
                                                                onBlur={() =>
                                                                    setHexDraft(
                                                                        form.data.color_hex.toUpperCase(),
                                                                    )
                                                                }
                                                                onKeyDown={(
                                                                    e,
                                                                ) =>
                                                                    e.key ===
                                                                        'Enter' &&
                                                                    submitForm()
                                                                }
                                                                maxLength={7}
                                                                spellCheck={
                                                                    false
                                                                }
                                                                placeholder="#C8102E"
                                                                className="h-full min-w-0 flex-1 bg-transparent font-mono text-sm text-slate-900 uppercase focus:outline-none"
                                                            />
                                                            {hexToRgb(
                                                                form.data
                                                                    .color_hex,
                                                            ) && (
                                                                <span className="shrink-0 font-mono text-[11px] text-slate-500">
                                                                    {hexToRgb(
                                                                        form
                                                                            .data
                                                                            .color_hex,
                                                                    )?.replace(
                                                                        /^rgb\((.*)\)$/,
                                                                        '$1',
                                                                    )}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="mt-2.5 flex flex-wrap gap-1.5">
                                                            {COLOR_PRESETS.map(
                                                                (preset) => (
                                                                    <motion.button
                                                                        key={
                                                                            preset.hex
                                                                        }
                                                                        type="button"
                                                                        title={`${preset.name} (${preset.hex.toUpperCase()})`}
                                                                        aria-label={`Use ${preset.name}`}
                                                                        onClick={() =>
                                                                            applyPreset(
                                                                                preset,
                                                                            )
                                                                        }
                                                                        whileHover={{
                                                                            scale: 1.15,
                                                                        }}
                                                                        whileTap={{
                                                                            scale: 0.9,
                                                                        }}
                                                                        className={cn(
                                                                            'size-5 rounded-full ring-1 ring-slate-900/10 transition-shadow',
                                                                            form
                                                                                .data
                                                                                .color_hex ===
                                                                                preset.hex &&
                                                                                'ring-2 ring-slate-900 ring-offset-2',
                                                                        )}
                                                                        style={{
                                                                            backgroundColor:
                                                                                preset.hex,
                                                                        }}
                                                                    />
                                                                ),
                                                            )}
                                                        </div>
                                                    </motion.div>
                                                )}
                                            </div>

                                            <motion.div
                                                variants={dialogItem}
                                                className="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-5 py-3"
                                            >
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={closeForm}
                                                >
                                                    Cancel
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={submitForm}
                                                    loading={form.processing}
                                                    loadingText="Saving…"
                                                    className="bg-[#c8102e] text-white shadow-sm shadow-[#c8102e]/25 hover:bg-[#a50d25]"
                                                >
                                                    {editingValue
                                                        ? 'Save changes'
                                                        : isColor
                                                          ? 'Add color'
                                                          : 'Add size'}
                                                </Button>
                                            </motion.div>
                                        </motion.div>
                                    </DialogPrimitive.Content>
                                </div>
                            </MotionConfig>
                        </DialogPrimitive.Portal>
                    )}
                </AnimatePresence>
            </DialogPrimitive.Root>

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
