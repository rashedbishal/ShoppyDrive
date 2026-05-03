import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import { Card } from '@/components/ui/card';

type Customer = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    orders_count: number;
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
    customers: Paginated<Customer>;
    filters: { search?: string };
};

export default function CustomersIndex({ customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const applySearch = () => {
        router.get(route('admin.customers.index'), { search }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Customers — Admin" />
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Customers</h1>
                    <p className="text-sm text-slate-500">{customers.total} total</p>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by name or email…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                                className="h-9 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="divide-y divide-slate-50">
                        {customers.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">No customers found.</p>
                        ) : customers.data.map((customer) => (
                            <div key={customer.id} className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500">
                                    {customer.name.slice(0, 2).toUpperCase()}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold text-slate-800">{customer.name}</p>
                                    <p className="text-xs text-slate-400">{customer.email}{customer.phone ? ` · ${customer.phone}` : ''}</p>
                                </div>
                                <div className="hidden text-right sm:block">
                                    <p className="text-sm font-semibold text-slate-800">{customer.orders_count} orders</p>
                                    <p className="text-xs text-slate-400">Joined {new Date(customer.created_at).toLocaleDateString()}</p>
                                </div>
                                <Link href={route('admin.customers.show', customer.id)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                    <Eye className="size-4" />
                                </Link>
                            </div>
                        ))}
                    </div>

                    {customers.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">Page {customers.current_page} of {customers.last_page}</p>
                            <div className="flex gap-1">
                                {customers.links[0]?.url && <Link href={customers.links[0].url} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Prev</Link>}
                                {customers.links[customers.links.length - 1]?.url && <Link href={customers.links[customers.links.length - 1].url!} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Next</Link>}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
