import { Head, Link, router } from '@inertiajs/react';
import { Edit, ExternalLink, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BulkActionBar } from '@/components/admin/bulk-action-bar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useBulkSelect } from '@/hooks/use-bulk-select';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/landing-pages';

type LandingPage = {
    id: number;
    title: string;
    slug: string;
    is_active: boolean;
    product: { id: number; name: string } | null;
    created_at: string;
};

type Props = { pages: LandingPage[] };

export default function LandingPagesIndex({ pages }: Props) {
    const [deleteTarget, setDeleteTarget] = useState<LandingPage | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

    const bulk = useBulkSelect(pages.map((p) => p.id));

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

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.landing-pages.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <>
            <Head title="Landing Pages — Admin" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Landing Pages
                        </h1>
                        <p className="text-sm text-slate-500">
                            {pages.length} total
                        </p>
                    </div>
                    <Link
                        href={route('admin.landing-pages.create')}
                        className="w-full sm:w-auto"
                    >
                        <Button className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25] sm:w-auto">
                            <Plus className="size-4" /> New Landing Page
                        </Button>
                    </Link>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <BulkActionBar
                        count={bulk.selected.length}
                        statusUrl={bulkStatusRoute.url()}
                        selectedIds={bulk.selected}
                        onClear={bulk.clear}
                        onDelete={() => setBulkConfirmOpen(true)}
                        deleting={bulkDeleting}
                        label="landing page(s) selected"
                    />

                    {pages.length > 0 && (
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

                    {pages.length === 0 ? (
                        <div className="py-16 text-center text-sm text-slate-400">
                            No landing pages yet. Create one to get started.
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {pages.map((page) => (
                                <div
                                    key={page.id}
                                    className="flex items-center gap-4 px-4 py-3"
                                >
                                    <Checkbox
                                        checked={bulk.isSelected(page.id)}
                                        onCheckedChange={() =>
                                            bulk.toggle(page.id)
                                        }
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span className="text-sm font-semibold text-slate-800">
                                                {page.title}
                                            </span>
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${page.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                                            >
                                                {page.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        </div>
                                        <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-400">
                                            <span className="font-mono">
                                                /lp/{page.slug}
                                            </span>
                                            {page.product && (
                                                <span>
                                                    · {page.product.name}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <a
                                            href={`/lp/${page.slug}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            title="Preview"
                                            className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <ExternalLink className="size-4" />
                                        </a>
                                        <Link
                                            href={route(
                                                'admin.landing-pages.edit',
                                                page.id,
                                            )}
                                        >
                                            <button
                                                type="button"
                                                className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                            >
                                                <Edit className="size-4" />
                                            </button>
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget(page)
                                            }
                                            className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Delete landing page?"
                description={
                    deleteTarget
                        ? `This will permanently delete "${deleteTarget.title}". This cannot be undone.`
                        : undefined
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />

            <ConfirmDialog
                open={bulkConfirmOpen}
                onOpenChange={setBulkConfirmOpen}
                title="Delete selected landing pages?"
                description={`This will permanently delete ${bulk.selected.length} landing page(s). This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
