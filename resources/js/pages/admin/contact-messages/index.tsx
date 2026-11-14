import { Head, Link, router } from '@inertiajs/react';
import { Mail, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useLiveSearch } from '@/hooks/use-live-search';

type ContactMessageRow = {
    id: number;
    name: string;
    email: string;
    subject: string;
    message: string;
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
    messages: Paginated<ContactMessageRow>;
    filters: { search?: string };
};

export default function ContactMessagesIndex({ messages, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<ContactMessageRow | null>(
        null,
    );
    const [clearAllOpen, setClearAllOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const applyFilters = () => {
        if (search === (filters.search ?? '')) {
            return;
        }

        router.get(
            route('admin.contact-messages.index'),
            search ? { search } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.contact-messages.index'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(
            route('admin.contact-messages.destroy', deleteTarget.id),
            {
                preserveScroll: true,
                onFinish: () => {
                    setDeleting(false);
                    setDeleteTarget(null);
                },
            },
        );
    };

    const confirmClearAll = () => {
        setDeleting(true);
        router.delete(route('admin.contact-messages.clear'), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setClearAllOpen(false);
            },
        });
    };

    // Results follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(search, filters.search ?? '', () => applyFilters());

    return (
        <>
            <Head title="Contact Messages — Admin" />
            <div className="space-y-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Contact Messages
                        </h1>
                        <p className="text-sm text-slate-600">
                            {messages.total} message
                            {messages.total === 1 ? '' : 's'} received via the
                            contact form.
                        </p>
                    </div>
                    {messages.total > 0 && (
                        <button
                            type="button"
                            onClick={() => setClearAllOpen(true)}
                            className="flex w-full items-center justify-center gap-1.5 rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 sm:w-auto"
                        >
                            <Trash2 className="size-4" />
                            Erase All
                        </button>
                    )}
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search by name, email, or subject…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters()
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-brand focus:outline-none"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={applyFilters}
                            className="h-9 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700"
                        >
                            Search
                        </button>
                        <ClearFiltersButton
                            active={Boolean(search || filters.search)}
                            onClear={clearFilters}
                        />
                    </div>

                    <div className="divide-y divide-slate-50">
                        {messages.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-16 text-slate-600">
                                <Mail className="size-8" />
                                <p className="text-sm">
                                    No contact messages found.
                                </p>
                            </div>
                        ) : (
                            messages.data.map((message) => (
                                <div
                                    key={message.id}
                                    className="flex items-start gap-4 px-4 py-4 hover:bg-slate-50/60"
                                >
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                                        {message.name.slice(0, 2).toUpperCase()}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-baseline gap-2">
                                            <p className="text-sm font-semibold break-words text-slate-800">
                                                {message.name}
                                            </p>
                                            <a
                                                href={`mailto:${message.email}`}
                                                className="text-xs break-all text-slate-600 hover:underline"
                                            >
                                                {message.email}
                                            </a>
                                            <span className="text-xs text-slate-300">
                                                ·
                                            </span>
                                            <p className="text-xs text-slate-600">
                                                {new Date(
                                                    message.created_at,
                                                ).toLocaleString()}
                                            </p>
                                        </div>
                                        <p className="mt-1 text-sm font-medium break-words text-slate-700">
                                            {message.subject}
                                        </p>
                                        <p className="mt-1 text-sm break-words whitespace-pre-line text-slate-600">
                                            {message.message}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setDeleteTarget(message)}
                                        className="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                        aria-label={`Delete message from ${message.name}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            ))
                        )}
                    </div>

                    {messages.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-600">
                                Page {messages.current_page} of{' '}
                                {messages.last_page} &middot; {messages.total}{' '}
                                total
                            </p>
                            <div className="flex gap-1">
                                {messages.links[0]?.url && (
                                    <Link
                                        href={messages.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {messages.links[messages.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            messages.links[
                                                messages.links.length - 1
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
                title="Delete contact message?"
                description={
                    deleteTarget
                        ? `Delete the message “${deleteTarget.subject}”?`
                        : undefined
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />
            <ConfirmDialog
                open={clearAllOpen}
                onOpenChange={setClearAllOpen}
                title="Erase all contact messages?"
                description={`This permanently removes all ${messages.total} contact messages and cannot be undone.`}
                confirmLabel="Erase all"
                processingLabel="Erasing…"
                onConfirm={confirmClearAll}
                processing={deleting}
            />
        </>
    );
}
