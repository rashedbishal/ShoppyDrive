import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Palette, Plus, Ruler, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

type AttributeValue = {
    id: number;
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

function ValueRow({
    attributeValue,
    isColor,
    onDelete,
}: {
    attributeValue: AttributeValue;
    isColor: boolean;
    onDelete: (id: number) => void;
}) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing } = useForm({
        value: attributeValue.value,
        color_hex: attributeValue.color_hex ?? '#000000',
        sort_order: attributeValue.sort_order,
    });

    const save = () => {
        put(route('admin.attribute-values.update', attributeValue.id), {
            onSuccess: () => setEditing(false),
        });
    };

    const inputClass =
        'h-8 rounded-lg border border-slate-200 px-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

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
                    <input
                        autoFocus
                        className={`${inputClass} flex-1`}
                        value={data.value}
                        onChange={(e) => setData('value', e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && save()}
                    />
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
                    <input
                        type="number"
                        min={0}
                        className={`${inputClass} w-16`}
                        value={data.sort_order}
                        onChange={(e) =>
                            setData('sort_order', Number(e.target.value))
                        }
                        title="Sort order"
                    />
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
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="flex-1 text-left text-sm font-medium text-slate-800"
                    >
                        {attributeValue.value}
                    </button>
                    <span className="shrink-0 text-[11px] text-slate-400">
                        #{attributeValue.sort_order}
                    </span>
                    <button
                        type="button"
                        onClick={() => onDelete(attributeValue.id)}
                        className="shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
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
    const { data, setData, post, processing, reset, errors } = useForm({
        attribute_id: attribute.id,
        value: '',
        color_hex: '#c8102e',
        sort_order: attribute.values.length,
    });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.attribute-values.store'), {
            onSuccess: () => {
                reset('value', 'color_hex');
                setData('sort_order', attribute.values.length + 1);
            },
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
            </div>

            {attribute.values.length === 0 ? (
                <div className="px-4 py-6 text-center text-sm text-slate-400">
                    No {attribute.name.toLowerCase()} values yet.
                </div>
            ) : (
                <div className="divide-y divide-slate-50">
                    {attribute.values.map((value) => (
                        <ValueRow
                            key={value.id}
                            attributeValue={value}
                            isColor={isColor}
                            onDelete={onDelete}
                        />
                    ))}
                </div>
            )}

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
                        onChange={(e) => setData('color_hex', e.target.value)}
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
            {errors.value && (
                <p className="px-4 pb-3 text-xs text-red-500">{errors.value}</p>
            )}
        </Card>
    );
}

export default function AttributesIndex({ attributes }: Props) {
    const [deleteTargetId, setDeleteTargetId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const { props } = usePage<any>();
    const flash = props.flash as
        | { success?: string; error?: string }
        | undefined;

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

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-lg bg-red-50 px-4 py-2.5 text-sm font-medium text-red-600">
                        {flash.error}
                    </div>
                )}

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
