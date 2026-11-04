import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { GripVertical, Plus, Save, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminFade } from '@/components/admin/admin-motion';
import { BulkActionBar } from '@/components/admin/bulk-action-bar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useBulkSelect } from '@/hooks/use-bulk-select';
import { maxLength, required, validate } from '@/lib/validate';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/popular-keywords';

type Keyword = {
    id: number;
    keyword: string;
    sort_order: number;
    is_active: boolean;
};
type Props = { keywords: Keyword[] };

function KeywordRow({
    keyword,
    onDelete,
    selected,
    onToggleSelect,
}: {
    keyword: Keyword;
    onDelete: (id: number) => void;
    selected: boolean;
    onToggleSelect: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            keyword: keyword.keyword,
            sort_order: keyword.sort_order,
            is_active: keyword.is_active,
        });

    const save = () => {
        clearErrors();
        const clientErrors = validate(data, {
            keyword: [required(), maxLength(100)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        put(route('admin.popular-keywords.update', keyword.id), {
            onSuccess: () => setEditing(false),
        });
    };

    const inputClass =
        'h-8 rounded-lg border border-slate-200 px-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    return (
        <div className="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50/60">
            <Checkbox checked={selected} onCheckedChange={onToggleSelect} />
            <GripVertical className="size-4 shrink-0 text-slate-300" />

            <AnimatePresence initial={false} mode="wait">
                {editing ? (
                    <AdminFade
                        key="row-a"
                        className="flex min-w-0 flex-1 items-center gap-3"
                    >
                        <>
                            <div className="flex-1">
                                <input
                                    autoFocus
                                    className={`${inputClass} w-full`}
                                    value={data.keyword}
                                    onChange={(e) =>
                                        setData('keyword', e.target.value)
                                    }
                                    onKeyDown={(e) =>
                                        e.key === 'Enter' && save()
                                    }
                                />
                                {errors.keyword && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.keyword}
                                    </p>
                                )}
                            </div>
                            <input
                                type="number"
                                min={0}
                                className={`${inputClass} w-16`}
                                value={data.sort_order}
                                onChange={(e) =>
                                    setData(
                                        'sort_order',
                                        Number(e.target.value),
                                    )
                                }
                                title="Sort order"
                            />
                            <label className="flex shrink-0 cursor-pointer items-center gap-1.5 text-xs text-slate-600">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) =>
                                        setData('is_active', e.target.checked)
                                    }
                                    className="rounded border-slate-300"
                                />
                                Active
                            </label>
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
                    </AdminFade>
                ) : (
                    <AdminFade
                        key="row-b"
                        className="flex min-w-0 flex-1 items-center gap-3"
                    >
                        <>
                            <button
                                type="button"
                                onClick={() => setEditing(true)}
                                className="flex-1 text-left"
                            >
                                <span
                                    className={`text-sm font-medium ${data.is_active ? 'text-slate-800' : 'text-slate-400 line-through'}`}
                                >
                                    {keyword.keyword}
                                </span>
                            </button>
                            <span className="shrink-0 text-[11px] text-slate-400">
                                #{keyword.sort_order}
                            </span>
                            {!keyword.is_active && (
                                <span className="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-400">
                                    hidden
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={() => onDelete(keyword.id)}
                                className="shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </>
                    </AdminFade>
                )}
            </AnimatePresence>
        </div>
    );
}

export default function PopularKeywordsIndex({ keywords }: Props) {
    const [deleteTargetId, setDeleteTargetId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

    const bulk = useBulkSelect(keywords.map((k) => k.id));

    const confirmBulkDelete = () => {
        setBulkDeleting(true);
        router.delete(bulkDestroyRoute.url(), {
            data: { ids: bulk.selected },
            preserveScroll: true,
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
        processing,
        reset,
        errors,
        setError,
        clearErrors,
    } = useForm({ keyword: '' });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = validate(data, {
            keyword: [required(), maxLength(100)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.popular-keywords.store'), {
            onSuccess: () => reset(),
        });
    };

    const confirmDelete = () => {
        if (deleteTargetId === null) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.popular-keywords.destroy', deleteTargetId), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setDeleteTargetId(null);
            },
        });
    };

    return (
        <>
            <Head title="Popular Search Keywords — Admin" />
            <div className="max-w-xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold text-slate-900">
                                Popular Search Keywords
                            </h1>
                            <Search className="size-5 text-slate-400" />
                        </div>
                        <p className="text-sm text-slate-500">
                            These keywords appear in the search bar when
                            customers click on it.
                        </p>
                    </div>
                </div>

                {/* Add keyword */}
                <Card className="p-4 shadow-sm">
                    <form onSubmit={handleAdd} className="flex gap-2">
                        <div className="flex-1">
                            <input
                                className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                value={data.keyword}
                                onChange={(e) =>
                                    setData('keyword', e.target.value)
                                }
                                placeholder="New keyword…"
                            />
                            {errors.keyword && (
                                <p className="mt-1 text-xs text-red-500">
                                    {errors.keyword}
                                </p>
                            )}
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="shrink-0 bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            <Plus className="size-4" />
                            Add
                        </Button>
                    </form>
                </Card>

                {/* Keywords list */}
                <Card className="overflow-hidden p-0 shadow-sm">
                    <BulkActionBar
                        count={bulk.selected.length}
                        statusUrl={bulkStatusRoute.url()}
                        selectedIds={bulk.selected}
                        onClear={bulk.clear}
                        onDelete={() => setBulkConfirmOpen(true)}
                        deleting={bulkDeleting}
                        label="keyword(s) selected"
                    />

                    {keywords.length > 0 && (
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

                    {keywords.length === 0 ? (
                        <div className="py-10 text-center text-sm text-slate-400">
                            No keywords yet. Add one above.
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-50">
                            {keywords.map((kw) => (
                                <KeywordRow
                                    key={kw.id}
                                    keyword={kw}
                                    onDelete={setDeleteTargetId}
                                    selected={bulk.isSelected(kw.id)}
                                    onToggleSelect={() => bulk.toggle(kw.id)}
                                />
                            ))}
                        </div>
                    )}
                </Card>

                <p className="text-xs text-slate-400">
                    Click any keyword to edit it inline. Sort order controls the
                    display sequence (lower = first). Uncheck "Active" to hide a
                    keyword without deleting it.
                </p>
            </div>

            <ConfirmDialog
                open={deleteTargetId !== null}
                onOpenChange={(open) => !open && setDeleteTargetId(null)}
                title="Delete keyword?"
                description="This will permanently delete this search keyword. This cannot be undone."
                onConfirm={confirmDelete}
                processing={deleting}
            />

            <ConfirmDialog
                open={bulkConfirmOpen}
                onOpenChange={setBulkConfirmOpen}
                title="Delete selected keywords?"
                description={`This will permanently delete ${bulk.selected.length} keyword(s). This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
