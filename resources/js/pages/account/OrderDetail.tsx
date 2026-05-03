import { Head, Link } from '@inertiajs/react';
import { 
    ShoppingBag, 
    ArrowLeft, 
    Package, 
    Calendar,
    MapPin,
    CreditCard,
    Download,
    Truck,
    Undo2,
    MessageSquare
} from 'lucide-react';
import React from 'react';

import AccountLayout from '@/components/layout/AccountLayout';
import { OrderStatusBadge, PaymentStatusBadge } from '@/components/shared/OrderStatusBadge';
import { OrderTimeline } from '@/components/shared/OrderTimeline';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { Order } from '@/types';

interface OrderDetailProps {
    order: Order;
}

export default function OrderDetail({
    order,
}: OrderDetailProps): React.JSX.Element {
    return (
        <AccountLayout>
            <Head title={`Order #${order.order_number}`} />
            
            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div className="flex items-center gap-4">
                        <Link 
                            href={route('account.orders')}
                            className="p-2 rounded-full bg-white border border-[var(--color-border)] text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors"
                        >
                            <ArrowLeft size={18} />
                        </Link>
                        <div>
                            <div className="flex items-center gap-3 mb-1">
                                <h1 className="text-2xl font-serif font-bold text-[var(--color-text-primary)]">Order #{order.order_number}</h1>
                                <OrderStatusBadge status={order.status} />
                            </div>
                            <p className="text-sm text-[var(--color-text-secondary)] font-medium flex items-center gap-2">
                                <Calendar size={14} />
                                Placed on {formatDate(order.created_at, 'long')}
                            </p>
                        </div>
                    </div>
                    
                    <div className="flex gap-3">
                        <Button variant="outline" className="rounded-xl font-bold border-[var(--color-border)] hover:bg-[var(--color-surface-alt)]">
                            <Download size={16} className="mr-2" />
                            Invoice
                        </Button>
                        {order.status === 'delivered' && (
                            <Button className="rounded-xl font-bold bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)]">
                                <Undo2 size={16} className="mr-2" />
                                Return Items
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    {/* Left: Timeline and Items */}
                    <div className="lg:col-span-8 space-y-8">
                        {/* Status Timeline */}
                        <div className="bg-white rounded-3xl p-8 md:p-10 shadow-sm border border-[var(--color-border)]">
                            <h2 className="text-xl font-bold text-[var(--color-text-primary)] mb-8 flex items-center gap-3">
                                <ClockIcon size={20} className="text-[var(--color-primary)]" />
                                Order Journey
                            </h2>
                            <OrderTimeline histories={order.status_histories} />
                        </div>

                        {/* Items Table */}
                        <div className="bg-white rounded-3xl shadow-sm border border-[var(--color-border)] overflow-hidden">
                            <div className="p-6 md:p-8 border-b border-[var(--color-border)]">
                                <h2 className="text-xl font-bold text-[var(--color-text-primary)]">Order Items</h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-[var(--color-surface-alt)] text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest">
                                            <th className="px-8 py-4">Product</th>
                                            <th className="px-8 py-4 text-center">Price</th>
                                            <th className="px-8 py-4 text-center">Qty</th>
                                            <th className="px-8 py-4 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--color-border)]">
                                        {order.items.map((item) => (
                                            <tr key={item.id} className="hover:bg-[var(--color-surface-alt)]/50 transition-colors">
                                                <td className="px-8 py-6">
                                                    <div className="flex gap-4">
                                                        <div className="w-16 h-16 rounded-xl bg-[var(--color-surface-alt)] overflow-hidden border border-[var(--color-border)] shrink-0">
                                                            <img 
                                                                src={item.product?.primary_image_url || '/placeholder.webp'} 
                                                                alt={item.product_name} 
                                                                className="w-full h-full object-cover" 
                                                            />
                                                        </div>
                                                        <div className="flex flex-col justify-center min-w-0">
                                                            <p className="text-sm font-bold text-[var(--color-text-primary)] truncate">{item.product_name}</p>
                                                            {item.variant && (
                                                                <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest mt-0.5">
                                                                    {Object.entries(item.variant.attributes).map(([k, v]) => `${k}: ${v}`).join(' | ')}
                                                                </p>
                                                            )}
                                                            <p className="text-xs text-[var(--color-text-muted)] mt-1 font-mono">SKU: {item.sku || 'N/A'}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-8 py-6 text-sm font-bold text-[var(--color-text-primary)] text-center">
                                                    {formatCurrency(item.price)}
                                                </td>
                                                <td className="px-8 py-6 text-sm font-bold text-[var(--color-text-secondary)] text-center">
                                                    {item.quantity}
                                                </td>
                                                <td className="px-8 py-6 text-sm font-bold text-[var(--color-primary)] text-right">
                                                    {formatCurrency(item.subtotal)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            
                            {/* Detailed Totals */}
                            <div className="bg-[var(--color-surface-alt)]/50 p-8 flex justify-end">
                                <div className="w-full max-w-xs space-y-3">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-[var(--color-text-secondary)] font-medium">Subtotal</span>
                                        <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(order.subtotal)}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-[var(--color-text-secondary)] font-medium">Shipping Cost</span>
                                        <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(order.shipping_cost)}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-[var(--color-text-secondary)] font-medium">Tax</span>
                                        <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(order.tax)}</span>
                                    </div>
                                    {order.discount > 0 && (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-[var(--color-text-secondary)] font-medium">Discount ({order.coupon_code})</span>
                                            <span className="font-bold text-[var(--color-success)]">-{formatCurrency(order.discount)}</span>
                                        </div>
                                    )}
                                    <div className="h-px bg-[var(--color-border)] w-full my-4" />
                                    <div className="flex justify-between items-end">
                                        <span className="text-lg font-bold text-[var(--color-text-primary)]">Total</span>
                                        <span className="text-2xl font-bold text-[var(--color-primary)]">{formatCurrency(order.total)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right: Order Summary Details */}
                    <div className="lg:col-span-4 space-y-8">
                        {/* Tracking Info */}
                        {order.tracking_number && (
                            <div className="bg-gradient-to-br from-[var(--color-secondary)] to-[#2A2A4E] rounded-3xl p-8 text-white shadow-lg shadow-blue-100/50">
                                <div className="flex items-center gap-3 mb-6">
                                    <Truck size={24} className="text-blue-400" />
                                    <h3 className="text-lg font-bold">Track Shipment</h3>
                                </div>
                                <div className="p-4 rounded-2xl bg-white/10 border border-white/10 mb-6">
                                    <p className="text-[10px] font-bold text-blue-200 uppercase tracking-widest mb-1">Tracking ID</p>
                                    <p className="text-xl font-mono font-bold tracking-tight">{order.tracking_number}</p>
                                </div>
                                <Button className="w-full h-12 rounded-xl bg-white text-[var(--color-secondary)] hover:bg-gray-100 font-bold">
                                    Track on Carrier Site
                                </Button>
                            </div>
                        )}

                        {/* Customer & Address Details */}
                        <div className="bg-white rounded-3xl p-8 shadow-sm border border-[var(--color-border)] space-y-8">
                            <div>
                                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <MapPin size={16} className="text-[var(--color-primary)]" />
                                    Shipping Address
                                </h3>
                                <div className="p-5 rounded-2xl bg-[var(--color-surface-alt)] border border-[var(--color-border)]">
                                    <p className="font-bold text-[var(--color-text-primary)] text-sm mb-1">{order.shipping_address.name}</p>
                                    <p className="text-xs text-[var(--color-text-secondary)] font-medium mb-3">{order.shipping_address.phone}</p>
                                    <p className="text-xs text-[var(--color-text-secondary)] leading-relaxed">
                                        {order.shipping_address.address_line1}<br />
                                        {order.shipping_address.address_line2 && <>{order.shipping_address.address_line2}<br /></>}
                                        {order.shipping_address.city}, {order.shipping_address.state} {order.shipping_address.postal_code}<br />
                                        {order.shipping_address.country}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <CreditCard size={16} className="text-[var(--color-primary)]" />
                                    Payment Method
                                </h3>
                                <div className="p-5 rounded-2xl bg-[var(--color-surface-alt)] border border-[var(--color-border)]">
                                    <div className="flex items-center justify-between mb-4">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)] uppercase">{order.payment_method}</p>
                                        <PaymentStatusBadge status={order.payment_status} />
                                    </div>
                                    <div className="flex items-center gap-3 p-3 rounded-xl bg-white border border-[var(--color-border)]">
                                        <div className="w-10 h-10 rounded-lg bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-primary)]">
                                            {order.payment_method === 'sslcommerz' ? <CreditCard size={20} /> : <ShoppingBag size={20} />}
                                        </div>
                                        <div>
                                            <p className="text-xs font-bold text-[var(--color-text-primary)]">
                                                {order.payment_method === 'sslcommerz' ? 'Paid via Online' : 'Cash on Delivery'}
                                            </p>
                                            <p className="text-[10px] text-[var(--color-text-muted)] font-medium">Transaction ID: {order.payment_method === 'sslcommerz' ? 'TXN-98234723' : 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-4">Help & Support</h3>
                                <div className="space-y-3">
                                    <Button variant="outline" className="w-full justify-start rounded-xl font-bold border-[var(--color-border)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]">
                                        <MessageSquare size={16} className="mr-3" />
                                        Contact Support
                                    </Button>
                                    <Button variant="outline" className="w-full justify-start rounded-xl font-bold border-[var(--color-border)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]">
                                        <Package size={16} className="mr-3" />
                                        Return Policy
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AccountLayout>
    );
}

function ClockIcon({ size = 20, className = "" }) {
    return (
        <svg 
            xmlns="http://www.w3.org/2000/svg" 
            width={size} 
            height={size} 
            viewBox="0 0 24 24" 
            fill="none" 
            stroke="currentColor" 
            strokeWidth="2" 
            strokeLinecap="round" 
            strokeLinejoin="round" 
            className={className}
        >
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
    );
}
