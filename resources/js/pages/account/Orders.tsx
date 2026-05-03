import { Head, Link, router } from '@inertiajs/react';
import { 
    ShoppingBag, 
    Search, 
    ChevronRight,
    Package,
    Calendar,
    ArrowRight
} from 'lucide-react';
import React, { useState } from 'react';

import AccountLayout from '@/components/layout/AccountLayout';
import { OrderStatusBadge, PaymentStatusBadge } from '@/components/shared/OrderStatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { Order, PaginatedData } from '@/types';

interface OrdersProps {
    orders: PaginatedData<Order>;
    filters: {
        search?: string;
        status?: string;
    };
}

export default function Orders({
    orders,
    filters,
}: OrdersProps): React.JSX.Element {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('account.orders'), { search }, { preserveState: true });
    };

    return (
        <AccountLayout>
            <Head title="My Orders" />
            
            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-[var(--color-text-primary)] mb-1">My Orders</h1>
                        <p className="text-[var(--color-text-secondary)]">Check the status of your recent orders and manage returns.</p>
                    </div>
                    
                    <form onSubmit={handleSearch} className="relative max-w-sm w-full">
                        <Input 
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by order ID..." 
                            className="h-12 pl-12 rounded-xl border-[var(--color-border)] focus:ring-[var(--color-primary)]"
                        />
                        <Search size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                    </form>
                </div>

                {/* Orders List */}
                <div className="space-y-6">
                    {orders.data.length > 0 ? (
                        orders.data.map((order) => (
                            <div 
                                key={order.id} 
                                className="bg-white rounded-3xl shadow-sm border border-[var(--color-border)] overflow-hidden hover:shadow-md transition-all group"
                            >
                                {/* Order Header */}
                                <div className="p-6 md:p-8 border-b border-[var(--color-border)] flex flex-wrap items-center justify-between gap-4 bg-[var(--color-surface-alt)]/50">
                                    <div className="flex items-center gap-4">
                                        <div className="w-12 h-12 rounded-2xl bg-white border border-[var(--color-border)] flex items-center justify-center text-[var(--color-primary)] shadow-sm">
                                            <Package size={24} />
                                        </div>
                                        <div>
                                            <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest leading-none mb-1">Order ID</p>
                                            <h3 className="text-lg font-mono font-bold text-[var(--color-text-primary)]">#{order.order_number}</h3>
                                        </div>
                                    </div>
                                    
                                    <div className="flex flex-wrap items-center gap-3 sm:gap-6">
                                        <div className="text-right hidden sm:block">
                                            <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest leading-none mb-1">Date Placed</p>
                                            <p className="text-sm font-bold text-[var(--color-text-primary)] flex items-center gap-1.5 justify-end">
                                                <Calendar size={14} className="text-[var(--color-text-muted)]" />
                                                {formatDate(order.created_at)}
                                            </p>
                                        </div>
                                        <div className="h-8 w-px bg-[var(--color-border)] hidden sm:block" />
                                        <div className="flex items-center gap-2">
                                            <OrderStatusBadge status={order.status} />
                                            <PaymentStatusBadge status={order.payment_status} />
                                        </div>
                                    </div>
                                </div>

                                {/* Order Items Preview */}
                                <div className="p-6 md:p-8">
                                    <div className="flex items-center justify-between mb-6">
                                        <div className="flex -space-x-4">
                                            {order.items.slice(0, 3).map((item, idx) => (
                                                <div 
                                                    key={idx} 
                                                    className="w-16 h-16 rounded-xl border-4 border-white bg-[var(--color-surface-alt)] overflow-hidden shadow-sm relative z-[10]"
                                                    style={{ zIndex: 10 - idx }}
                                                >
                                                    <img 
                                                        src={item.product?.primary_image_url || '/placeholder.webp'} 
                                                        alt={item.product_name} 
                                                        className="w-full h-full object-cover" 
                                                    />
                                                </div>
                                            ))}
                                            {order.items.length > 3 && (
                                                <div className="w-16 h-16 rounded-xl border-4 border-white bg-[var(--color-primary-light)] flex items-center justify-center text-[var(--color-primary)] font-bold text-sm shadow-sm relative z-0">
                                                    +{order.items.length - 3}
                                                </div>
                                            )}
                                        </div>
                                        
                                        <div className="text-right">
                                            <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest mb-1">Total Amount</p>
                                            <p className="text-2xl font-bold text-[var(--color-primary)]">{formatCurrency(order.total)}</p>
                                        </div>
                                    </div>

                                    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-[var(--color-border)]">
                                        <p className="text-sm text-[var(--color-text-secondary)] font-medium italic">
                                            {order.status === 'delivered' 
                                                ? `Delivered on ${formatDate(order.delivered_at || '')}` 
                                                : `Estimated Delivery: 3-5 business days`}
                                        </p>
                                        
                                        <div className="flex gap-3 w-full sm:w-auto">
                                            <Button asChild variant="outline" className="flex-1 sm:flex-none rounded-xl font-bold border-[var(--color-border)] hover:bg-[var(--color-surface-alt)]">
                                                <Link href={route('account.orders.show', order.order_number)}>
                                                    View Details
                                                </Link>
                                            </Button>
                                            <Button asChild className="flex-1 sm:flex-none rounded-xl font-bold bg-[var(--color-secondary)] text-white hover:bg-[#2A2A4E]">
                                                <Link href={route('order.track', { order: order.order_number })}>
                                                    Track Order
                                                    <ArrowRight size={16} className="ml-2" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-white rounded-3xl p-20 border border-[var(--color-border)] text-center">
                            <div className="w-20 h-20 rounded-full bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-text-muted)] mx-auto mb-6">
                                <ShoppingBag size={40} />
                            </div>
                            <h3 className="text-xl font-bold text-[var(--color-text-primary)] mb-2">No orders found</h3>
                            <p className="text-[var(--color-text-secondary)] mb-10 max-w-sm mx-auto">
                                {search 
                                    ? `We couldn't find any orders matching "${search}". Try searching with a different order ID.` 
                                    : "You haven't placed any orders yet. Browse our catalog and start shopping!"}
                            </p>
                            <Button asChild className="rounded-xl px-10 h-14 font-bold shadow-lg">
                                <Link href={route('products.index')}>Explore Products</Link>
                            </Button>
                        </div>
                    )}
                </div>

                {/* Pagination (Simplified) */}
                {orders.last_page > 1 && (
                    <div className="flex justify-center mt-12">
                        {/* Standard Pagination Component would go here */}
                    </div>
                )}
            </div>
        </AccountLayout>
    );
}
