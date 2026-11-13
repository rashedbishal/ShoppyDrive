import { Head, Link, router } from '@inertiajs/react';
import { Mail, Search, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Select } from '@/components/ui/select';
import { useLiveSearch } from '@/hooks/use-live-search';

type Subscriber = {
    id: number;
    email: string;
    is_active: boolean;
    subscribed_at: string;
    unsubscribed_at: string | null;
    created_at: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    subscribers: Paginated<Subscriber>;
    filters: { search?: string; status?: string };
    total_active: number;
};

export default function NewsletterIndex({
    subscribers,
    filters,
    total_active,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [deleteTarget, setDeleteTarget] = useState<Subscriber | null>(null);
    const [deleting, setDeleting] = useState(false);

    const applyFilters = (overrides: Record<string, string> = {}) => {
        const params: Record<string, string> = {};

        if (search) {
            params.search = search;
        }

        if (status) {
            params.status = status;
        }

        Object.assign(params, overrides);

        if (
            (params.search ?? '') === (filters.search ?? '') &&
            (params.status ?? '') === (filters.status ?? '')
        ) {
            return;
        }

        router.get(route('admin.newsletter.index'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        router.get(
            route('admin.newsletter.index'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.newsletter.destroy', deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    // Results follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(search, filters.search ?? '', () => applyFilters());

    return (
        <>
            <Head title="Newsletter — Admin" />
            <div className="space-y-5">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Newsletter
                        </h1>
                        <p className="text-sm text-slate-600">
                            {total_active} active subscribers
                        </p>
                    </div>
                    <Link
                        href={route('admin.newsletter.compose')}
                        className="flex w-full items-center justify-center gap-1.5 rounded-lg bg-[#c8102e] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#a50d25] sm:w-auto"
                    >
                        <Mail className="size-4" />
                        Send Email
                    </Link>
                </div>

                {/* Flash messages */}

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <Card className="p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-emerald-50">
                                <Users className="size-4 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-lg font-bold text-slate-900">
                                    {total_active}
                                </p>
                                <p className="text-xs text-slate-600">
                                    Active subscribers
                                </p>
                            </div>
                        </div>
                    </Card>
                    <Card className="p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-blue-50">
                                <Mail className="size-4 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-lg font-bold text-slate-900">
                                    {subscribers.total}
                                </p>
                                <p className="text-xs text-slate-600">
                                    Total (all time)
                                </p>
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Filters */}
                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search by email…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters()
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                        <Select
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                applyFilters({ status: e.target.value });
                            }}
                            containerClassName="min-w-40"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm text-slate-700 focus:border-[#c8102e] focus:outline-none"
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Unsubscribed</option>
                        </Select>
                        <button
                            type="button"
                            onClick={() => applyFilters()}
                            className="h-9 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700"
                        >
                            Search
                        </button>
                        <ClearFiltersButton
                            active={Boolean(search || status)}
                            onClear={clearFilters}
                        />
                    </div>

                    {/* Table */}
                    <div className="divide-y divide-slate-50">
                        {subscribers.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-600">
                                No subscribers found.
                            </p>
                        ) : (
                            subscribers.data.map((subscriber) => (
                                <div
                                    key={subscriber.id}
                                    className="admin-row-card flex flex-wrap items-center gap-x-3 gap-y-1.5 px-4 py-3 hover:bg-slate-50/60 sm:flex-nowrap sm:gap-4"
                                >
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                                        {subscriber.email
                                            .slice(0, 2)
                                            .toUpperCase()}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className="truncate text-sm font-medium text-slate-800"
                                            title={subscriber.email}
                                        >
                                            {subscriber.email}
                                        </p>
                                        <p className="text-xs text-slate-600">
                                            Subscribed{' '}
                                            {new Date(
                                                subscriber.subscribed_at,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="order-last w-full pl-12 sm:order-none sm:w-auto sm:pl-0">
                                        <span
                                            className={`inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                subscriber.is_active
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-600'
                                            }`}
                                        >
                                            {subscriber.is_active
                                                ? 'Active'
                                                : 'Unsubscribed'}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setDeleteTarget(subscriber)
                                        }
                                        className="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                        aria-label={`Delete ${subscriber.email}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            ))
                        )}
                    </div>

                    {/* Pagination */}
                    {subscribers.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-600">
                                Page {subscribers.current_page} of{' '}
                                {subscribers.last_page} &middot;{' '}
                                {subscribers.total} total
                            </p>
                            <div className="flex gap-1">
                                {subscribers.links[0]?.url && (
                                    <Link
                                        href={subscribers.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {subscribers.links[subscribers.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            subscribers.links[
                                                subscribers.links.length - 1
                                            ].url!
                                        }
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Next
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Remove subscriber?"
                description={
                    deleteTarget
                        ? `Remove ${deleteTarget.email} from the subscriber list?`
                        : undefined
                }
                confirmLabel="Remove"
                processingLabel="Removing…"
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </>
    );
}
