import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { DeleteCustomerDialog } from '@/components/admin/delete-customer-dialog';
import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { useLiveSearch } from '@/hooks/use-live-search';

type Customer = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    orders_count: number;
    created_at: string;
    anonymized_at: string | null;
    deleted_at?: string | null;
    email_verified_at?: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    customers: Paginated<Customer>;
    filters: {
        search?: string;
        status?: string;
        verified?: string;
        orders?: string;
    };
};

export default function CustomersIndex({ customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<Customer | null>(null);

    // Dropdown filters apply at once; the search box applies on the Search button or Enter.
    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('admin.customers.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.customers.index'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasActiveFilters = Boolean(
        search ||
        filters.search ||
        filters.status ||
        filters.verified ||
        filters.orders,
    );

    // Results follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(search, filters.search ?? '', () => applyFilters());

    return (
        <>
            <Head title="Customers — Admin" />
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Customers
                    </h1>
                    <p className="text-sm text-slate-500">
                        {customers.total} total
                    </p>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="space-y-2 border-b border-slate-100 p-4">
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    maxLength={100}
                                    type="text"
                                    placeholder="Search by name, email or phone…"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) =>
                                        e.key === 'Enter' && applyFilters()
                                    }
                                    className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                                />
                            </div>
                            <button
                                type="button"
                                onClick={() => applyFilters()}
                                className="h-9 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700"
                            >
                                Search
                            </button>
                        </div>
                        <div className="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center">
                            <Select
                                value={filters.status ?? ''}
                                onChange={(e) =>
                                    applyFilters({ status: e.target.value })
                                }
                                containerClassName="min-w-0 sm:min-w-44"
                                selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                            >
                                <option value="">All customers</option>
                                <option value="active">
                                    Active (ordered in 90 days / new)
                                </option>
                                <option value="inactive">
                                    Inactive (no order in 90 days)
                                </option>
                                <option value="deleted">Deleted</option>
                            </Select>
                            <Select
                                value={filters.verified ?? ''}
                                onChange={(e) =>
                                    applyFilters({ verified: e.target.value })
                                }
                                containerClassName="min-w-0 sm:min-w-40"
                                selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                            >
                                <option value="">Any email status</option>
                                <option value="yes">Email verified</option>
                                <option value="no">Email not verified</option>
                            </Select>
                            <Select
                                value={filters.orders ?? ''}
                                onChange={(e) =>
                                    applyFilters({ orders: e.target.value })
                                }
                                containerClassName="min-w-0 sm:min-w-40"
                                selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                            >
                                <option value="">Any orders</option>
                                <option value="with">Has placed orders</option>
                                <option value="none">No orders yet</option>
                            </Select>
                            <ClearFiltersButton
                                active={hasActiveFilters}
                                onClear={clearFilters}
                            />
                        </div>
                    </div>

                    <div className="divide-y divide-slate-50">
                        {customers.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No customers found.
                            </p>
                        ) : (
                            customers.data.map((customer) => (
                                <div
                                    key={customer.id}
                                    role="link"
                                    tabIndex={0}
                                    onClick={() =>
                                        router.visit(
                                            route(
                                                'admin.customers.show',
                                                customer.id,
                                            ),
                                        )
                                    }
                                    onKeyDown={(event) => {
                                        if (
                                            event.target ===
                                                event.currentTarget &&
                                            event.key === 'Enter'
                                        ) {
                                            router.visit(
                                                route(
                                                    'admin.customers.show',
                                                    customer.id,
                                                ),
                                            );
                                        }
                                    }}
                                    className="admin-row-card flex cursor-pointer flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 hover:bg-slate-50/60 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#c8102e] sm:flex-nowrap sm:gap-4"
                                >
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500">
                                        {customer.name
                                            .slice(0, 2)
                                            .toUpperCase()}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                            <span className="truncate">
                                                {customer.name}
                                            </span>
                                            {(customer.anonymized_at ||
                                                customer.deleted_at) && (
                                                <span className="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                                    Deleted
                                                </span>
                                            )}
                                        </p>
                                        <p className="truncate text-xs text-slate-400">
                                            {customer.email}
                                            {customer.phone
                                                ? ` · ${customer.phone}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="order-last flex w-full items-baseline justify-between gap-2 sm:order-none sm:block sm:w-auto sm:text-right">
                                        <p className="text-sm font-semibold text-slate-800">
                                            {customer.orders_count} orders
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            Joined{' '}
                                            {new Date(
                                                customer.created_at,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <Link
                                            href={route(
                                                'admin.customers.show',
                                                customer.id,
                                            )}
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <Eye className="size-4" />
                                        </Link>
                                        {!customer.anonymized_at && (
                                            <button
                                                type="button"
                                                title="Anonymize"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setDeleteTarget(customer);
                                                }}
                                                className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {customers.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {customers.current_page} of{' '}
                                {customers.last_page}
                            </p>
                            <div className="flex gap-1">
                                {customers.links[0]?.url && (
                                    <Link
                                        href={customers.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {customers.links[customers.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            customers.links[
                                                customers.links.length - 1
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

            <DeleteCustomerDialog
                customer={deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            />
        </>
    );
}
