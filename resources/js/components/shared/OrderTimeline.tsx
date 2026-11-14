import { Clock, Package, Truck, CheckCircle2, XCircle } from 'lucide-react';
import React from 'react';

import { formatDate } from '@/lib/utils';
import type { OrderStatus, OrderStatusHistory } from '@/types';

interface OrderTimelineProps {
    histories: OrderStatusHistory[];
    /** DISC-49: tighter spacing, smaller markers (account order details). */
    compact?: boolean;
}

export function OrderTimeline({
    histories = [],
    compact = false,
}: OrderTimelineProps): React.JSX.Element {
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
        refunded: 'text-gray-600 bg-gray-50 border-gray-100',
    };

    return (
        <div
            className={
                compact
                    ? 'relative space-y-2.5 pl-7 before:absolute before:inset-0 before:left-[11px] before:z-0 before:w-0.5 before:bg-[var(--color-border)]'
                    : 'relative space-y-4 pl-8 before:absolute before:inset-0 before:left-[15px] before:z-0 before:w-0.5 before:bg-[var(--color-border)]'
            }
        >
            {histories.map((item, idx) => {
                const Icon = statusIcons[item.status] || Clock;
                const isLatest = idx === histories.length - 1;

                // Extract primary color from statusColors (e.g. 'text-amber-500')
                const statusTextColor =
                    statusColors[item.status]?.split(' ')[0] ||
                    'text-[var(--color-primary)]';
                const statusBgColor =
                    statusColors[item.status]?.split(' ')[1] ||
                    'bg-[var(--color-primary)]';
                const statusBorderColor =
                    statusColors[item.status]?.split(' ')[2] ||
                    'border-[var(--color-primary)]';

                return (
                    <div key={item.id} className="group relative z-10">
                        <div
                            className={`absolute flex items-center justify-center rounded-full border-white shadow-sm transition-all ${
                                compact
                                    ? '-left-7 h-6 w-6 border-2'
                                    : '-left-10 h-8 w-8 border-4'
                            } ${
                                isLatest
                                    ? `${statusBgColor} ${statusTextColor} ${compact ? 'ring-2' : 'scale-110 ring-4'} ring-[var(--color-primary-light)]`
                                    : 'bg-white text-[var(--color-text-muted)]'
                            }`}
                        >
                            {isLatest ? (
                                <Icon
                                    size={compact ? 12 : 14}
                                    strokeWidth={3}
                                />
                            ) : (
                                <div className="h-1.5 w-1.5 rounded-full bg-current" />
                            )}
                        </div>

                        <div
                            className={`rounded-xl border transition-all ${
                                compact ? 'px-3 py-2' : 'p-4'
                            } ${
                                isLatest
                                    ? `bg-white ${statusBorderColor} ${compact ? 'shadow-sm' : 'shadow-md'}`
                                    : 'border-[var(--color-border)] bg-[var(--color-surface-alt)]'
                            }`}
                        >
                            <div
                                className={`flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5 ${
                                    compact && !item.notes
                                        ? ''
                                        : compact
                                          ? 'mb-1'
                                          : 'mb-2'
                                }`}
                            >
                                <h4
                                    className={`${compact ? 'text-xs' : 'text-sm'} font-bold tracking-wider uppercase ${isLatest ? statusTextColor : 'text-[var(--color-text-primary)]'}`}
                                >
                                    {item.status.replace('_', ' ')}
                                </h4>
                                <span className="text-[10px] font-bold tracking-tighter text-[var(--color-text-secondary)] uppercase">
                                    {formatDate(item.created_at, 'full')}
                                </span>
                            </div>
                            {item.notes && (
                                <p className="text-xs leading-relaxed text-[var(--color-text-secondary)]">
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
