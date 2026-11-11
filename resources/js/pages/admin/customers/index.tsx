import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { DeleteCustomerDialog } from '@/components/admin/delete-customer-dialog';
import { Card } from '@/components/ui/card';

type Customer = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    orders_count: number;
    created_at: string;
    anonymized_at: string | null;
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
    filters: { search?: string };
};

export default function CustomersIndex({ customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<Customer | null>(null);

    const applySearch = () => {
        if (search === (filters.search ?? '')) {
            return;
        }

        router.get(
            route('admin.customers.index'),
            { search },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.customers.index'),
            {},
            { preserveState: true, replace: true },
        );
    };

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
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by name or email…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applySearch()
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                        <ClearFiltersButton
                            active={Boolean(search || filters.search)}
                            onClear={clearFilters}
                        />
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
                                        <p className="truncate text-sm font-semibold text-slate-800">
                                            {customer.name}
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
