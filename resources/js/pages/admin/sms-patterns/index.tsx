import { Head, router, useForm } from '@inertiajs/react';
import { Edit2, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { BulkActionBar } from '@/components/admin/bulk-action-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Select } from '@/components/ui/select';
import { useBulkSelect } from '@/hooks/use-bulk-select';
import { maxLength, required, validate } from '@/lib/validate';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/sms-patterns';

type Pattern = {
    id: number;
    provider: 'bkash' | 'nagad';
    name: string;
    identifier: string;
    txn_id_regex: string;
    amount_regex: string;
    is_active: boolean;
    sort_order: number;
};

type Props = { patterns: Pattern[] };

const emptyForm = {
    provider: 'bkash' as 'bkash' | 'nagad',
    name: '',
    identifier: '',
    txn_id_regex: '',
    amount_regex: '',
    is_active: true,
    sort_order: 99,
};

const inputClass =
    'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';
const providerColor = {
    bkash: 'bg-pink-100 text-pink-700',
    nagad: 'bg-orange-100 text-orange-700',
};

export default function SmsPatternsIndex({ patterns }: Props) {
    const [editingId, setEditingId] = useState<number | 'new' | null>(null);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

    const bulk = useBulkSelect(patterns.map((p) => p.id));

    const confirmBulkDelete = () => {
        setBulkDeleting(true);
        router.delete(bulkDestroyRoute.url(), {
            data: { ids: bulk.selected },
            onFinish: () => {
                setBulkDeleting(false);
                setBulkConfirmOpen(false);
                bulk.clear();
            },
        });
    };

    const {
        data,
        setData,
        post,
        patch,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm(emptyForm);

    const openNew = () => {
        reset();
        setData(emptyForm);
        setEditingId('new');
    };

    const openEdit = (p: Pattern) => {
        setData({
            provider: p.provider,
            name: p.name,
            identifier: p.identifier,
            txn_id_regex: p.txn_id_regex,
            amount_regex: p.amount_regex,
            is_active: p.is_active,
            sort_order: p.sort_order,
        });
        setEditingId(p.id);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors: Record<string, string> = validate(data, {
            name: [required(), maxLength(100)],
            identifier: [required(), maxLength(255)],
            txn_id_regex: [required(), maxLength(255)],
            amount_regex: [required(), maxLength(255)],
        });

        for (const field of ['txn_id_regex', 'amount_regex'] as const) {
            if (!clientErrors[field]) {
                try {
                    new RegExp(data[field]);
                } catch {
                    clientErrors[field] =
                        'This is not a valid regular expression.';
                }
            }
        }

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        if (editingId === 'new') {
            post(route('admin.sms-patterns.store'), {
                onSuccess: () => setEditingId(null),
            });
        } else if (typeof editingId === 'number') {
            patch(route('admin.sms-patterns.update', editingId), {
                onSuccess: () => setEditingId(null),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (!confirm('Delete this pattern?')) {
            return;
        }

        router.delete(route('admin.sms-patterns.destroy', id));
    };

    return (
        <>
            <Head title="SMS Patterns — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            SMS Patterns
                        </h1>
                        <p className="text-sm text-slate-500">
                            Define how to extract TxnID and amount from Bkash /
                            Nagad SMS
                        </p>
                    </div>
                    {editingId === null && (
                        <Button
                            size="sm"
                            onClick={openNew}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            <Plus className="mr-1.5 size-4" />
                            Add Pattern
                        </Button>
                    )}
                </div>

                {editingId !== null && (
                    <Card className="border-[#c8102e]/20 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                {editingId === 'new'
                                    ? 'Add New Pattern'
                                    : 'Edit Pattern'}
                            </CardTitle>
                            <button
                                type="button"
                                onClick={() => setEditingId(null)}
                                className="text-slate-400 hover:text-slate-700"
                            >
                                <X className="size-4" />
                            </button>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <form onSubmit={handleSubmit} className="space-y-3">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-slate-600">
                                            Provider *
                                        </label>
                                        <Select
                                            selectClassName={inputClass}
                                            value={data.provider}
                                            onChange={(e) =>
                                                setData(
                                                    'provider',
                                                    e.target.value as
                                                        | 'bkash'
                                                        | 'nagad',
                                                )
                                            }
                                            required
                                        >
                                            <option value="bkash">Bkash</option>
                                            <option value="nagad">Nagad</option>
                                        </Select>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-slate-600">
                                            Pattern Name *
                                        </label>
                                        <input
                                            className={inputClass}
                                            value={data.name}
                                            onChange={(e) =>
                                                setData('name', e.target.value)
                                            }
                                            placeholder="e.g. Bkash Money Received"
                                            required
                                        />
                                        {errors.name && (
                                            <p className="mt-0.5 text-xs text-red-500">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium text-slate-600">
                                        Identifier *
                                        <span className="ml-1 font-normal text-slate-400">
                                            — unique text that appears in this
                                            SMS format
                                        </span>
                                    </label>
                                    <input
                                        className={inputClass}
                                        value={data.identifier}
                                        onChange={(e) =>
                                            setData(
                                                'identifier',
                                                e.target.value,
                                            )
                                        }
                                        placeholder='e.g. "TrxID" or "Cash In Received."'
                                        required
                                    />
                                    {errors.identifier && (
                                        <p className="mt-0.5 text-xs text-red-500">
                                            {errors.identifier}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-slate-600">
                                            TxnID Regex *
                                            <span className="ml-1 font-normal text-slate-400">
                                                — 1 capture group
                                            </span>
                                        </label>
                                        <input
                                            className={`${inputClass} font-mono text-xs`}
                                            value={data.txn_id_regex}
                                            onChange={(e) =>
                                                setData(
                                                    'txn_id_regex',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="TrxID\s+([A-Z0-9]+)"
                                            required
                                        />
                                        {errors.txn_id_regex && (
                                            <p className="mt-0.5 text-xs text-red-500">
                                                {errors.txn_id_regex}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-slate-600">
                                            Amount Regex *
                                            <span className="ml-1 font-normal text-slate-400">
                                                — 1 capture group
                                            </span>
                                        </label>
                                        <input
                                            className={`${inputClass} font-mono text-xs`}
                                            value={data.amount_regex}
                                            onChange={(e) =>
                                                setData(
                                                    'amount_regex',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="received Tk\s+([\d,]+(?:\.\d+)?)"
                                            required
                                        />
                                        {errors.amount_regex && (
                                            <p className="mt-0.5 text-xs text-red-500">
                                                {errors.amount_regex}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-slate-600">
                                            Sort Order
                                        </label>
                                        <input
                                            type="number"
                                            min={0}
                                            className={inputClass}
                                            value={data.sort_order}
                                            onChange={(e) =>
                                                setData(
                                                    'sort_order',
                                                    parseInt(e.target.value) ||
                                                        0,
                                                )
                                            }
                                        />
                                    </div>
                                    {editingId !== 'new' && (
                                        <div className="flex items-end gap-2 pb-0.5">
                                            <label className="flex cursor-pointer items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={data.is_active}
                                                    onChange={(e) =>
                                                        setData(
                                                            'is_active',
                                                            e.target.checked,
                                                        )
                                                    }
                                                    className="rounded border-slate-300"
                                                />
                                                <span className="text-sm text-slate-700">
                                                    Active
                                                </span>
                                            </label>
                                        </div>
                                    )}
                                </div>

                                <div className="flex justify-end gap-2 pt-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEditingId(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                    >
                                        {processing
                                            ? 'Saving…'
                                            : editingId === 'new'
                                              ? 'Add Pattern'
                                              : 'Save Changes'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    <BulkActionBar
                        count={bulk.selected.length}
                        statusUrl={bulkStatusRoute.url()}
                        selectedIds={bulk.selected}
                        onClear={bulk.clear}
                        onDelete={() => setBulkConfirmOpen(true)}
                        deleting={bulkDeleting}
                        label="pattern(s) selected"
                    />

                    {patterns.length > 0 && (
                        <div className="flex items-center gap-3 border-b border-slate-100 px-4 py-2">
                            <Checkbox
                                checked={bulk.allSelected}
                                onCheckedChange={bulk.toggleAll}
                            />
                            <span className="text-xs font-medium text-slate-500">
                                Select all
                            </span>
                        </div>
                    )}

                    {patterns.length === 0 ? (
                        <p className="py-12 text-center text-sm text-slate-400">
                            No patterns yet. Add one above.
                        </p>
                    ) : (
                        <div className="divide-y divide-slate-50">
                            {patterns.map((p) => (
                                <div
                                    key={p.id}
                                    className={`px-4 py-3 hover:bg-slate-50/60 ${!p.is_active ? 'opacity-50' : ''}`}
                                >
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            checked={bulk.isSelected(p.id)}
                                            onCheckedChange={() =>
                                                bulk.toggle(p.id)
                                            }
                                            className="mt-0.5"
                                        />
                                        <div className="min-w-0 flex-1 space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span
                                                    className={`rounded-full px-2.5 py-0.5 text-xs font-bold ${providerColor[p.provider]}`}
                                                >
                                                    {p.provider === 'bkash'
                                                        ? 'Bkash'
                                                        : 'Nagad'}
                                                </span>
                                                <span className="text-sm font-semibold text-slate-800">
                                                    {p.name}
                                                </span>
                                                {!p.is_active && (
                                                    <Badge
                                                        variant="default"
                                                        size="sm"
                                                    >
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-slate-500">
                                                <span className="font-medium text-slate-600">
                                                    Identifier:
                                                </span>{' '}
                                                <code className="rounded bg-slate-100 px-1 py-0.5 text-[11px]">
                                                    {p.identifier}
                                                </code>
                                            </p>
                                            <div className="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-slate-500">
                                                <span>
                                                    <span className="font-medium text-slate-600">
                                                        TxnID:
                                                    </span>{' '}
                                                    <code className="rounded bg-slate-100 px-1 py-0.5 text-[11px]">
                                                        {p.txn_id_regex}
                                                    </code>
                                                </span>
                                                <span>
                                                    <span className="font-medium text-slate-600">
                                                        Amount:
                                                    </span>{' '}
                                                    <code className="rounded bg-slate-100 px-1 py-0.5 text-[11px]">
                                                        {p.amount_regex}
                                                    </code>
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            <button
                                                type="button"
                                                onClick={() => openEdit(p)}
                                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                            >
                                                <Edit2 className="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleDelete(p.id)
                                                }
                                                className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            <ConfirmDialog
                open={bulkConfirmOpen}
                onOpenChange={setBulkConfirmOpen}
                title="Delete selected patterns?"
                description={`This will permanently delete ${bulk.selected.length} pattern(s). This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
