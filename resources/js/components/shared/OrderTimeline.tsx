import { Check, Clock, Package, Truck, CheckCircle2, XCircle } from 'lucide-react';
import React from 'react';

import { formatDate } from '@/lib/utils';
import type { OrderStatus, OrderStatusHistory } from '@/types';

interface OrderTimelineProps {
    histories: OrderStatusHistory[];
}

export function OrderTimeline({ histories = [] }: OrderTimelineProps): React.JSX.Element {
    const statusIcons: Record<OrderStatus, any> = {
        pending: Clock,
        processing: Package,
        shipped: Truck,
        delivered: CheckCircle2,
        cancelled: XCircle,
        refunded: Clock, // Replace with appropriate icon
    };

    const statusColors: Record<OrderStatus, string> = {
        pending: 'text-amber-500 bg-amber-50 border-amber-100',
        processing: 'text-blue-500 bg-blue-50 border-blue-100',
        shipped: 'text-purple-500 bg-purple-50 border-purple-100',
        delivered: 'text-green-500 bg-green-50 border-green-100',
        cancelled: 'text-red-500 bg-red-50 border-red-100',
        refunded: 'text-gray-500 bg-gray-50 border-gray-100',
    };

    return (
        <div className="relative pl-8 space-y-8 before:absolute before:inset-0 before:left-[15px] before:w-0.5 before:bg-[var(--color-border)] before:z-0">
            {histories.map((item, idx) => {
                const Icon = statusIcons[item.status] || Clock;
                const isLatest = idx === histories.length - 1;
                
                // Extract primary color from statusColors (e.g. 'text-amber-500')
                const statusTextColor = statusColors[item.status]?.split(' ')[0] || 'text-[var(--color-primary)]';
                const statusBgColor = statusColors[item.status]?.split(' ')[1] || 'bg-[var(--color-primary)]';
                const statusBorderColor = statusColors[item.status]?.split(' ')[2] || 'border-[var(--color-primary)]';

                return (
                    <div key={item.id} className="relative z-10 group">
                        <div className={`absolute -left-10 w-8 h-8 rounded-full border-4 border-white flex items-center justify-center shadow-sm transition-all ${
                            isLatest ? `${statusBgColor} ${statusTextColor} scale-110 ring-4 ring-[var(--color-primary-light)]` : 'bg-white text-[var(--color-text-muted)]'
                        }`}>
                            {isLatest ? <Icon size={14} strokeWidth={3} /> : <div className="w-1.5 h-1.5 rounded-full bg-current" />}
                        </div>
                        
                        <div className={`p-5 rounded-2xl border transition-all ${
                            isLatest ? `bg-white ${statusBorderColor} shadow-md` : 'bg-[var(--color-surface-alt)] border-[var(--color-border)]'
                        }`}>
                            <div className="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <h4 className={`text-sm font-bold uppercase tracking-wider ${isLatest ? statusTextColor : 'text-[var(--color-text-primary)]'}`}>
                                    {item.status.replace('_', ' ')}
                                </h4>
                                <span className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-tighter">
                                    {formatDate(item.created_at, 'full')}
                                </span>
                            </div>
                            {item.notes && (
                                <p className="text-xs text-[var(--color-text-secondary)] leading-relaxed">
                                    {item.notes}
                                </p>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
