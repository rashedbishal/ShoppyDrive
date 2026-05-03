import React from 'react';
import { Badge } from '@/components/ui/badge';
import type { OrderStatus } from '@/types';

interface OrderStatusBadgeProps {
    status: OrderStatus;
    className?: string;
}

export function OrderStatusBadge({ status, className }: OrderStatusBadgeProps): React.JSX.Element {
    const variants: Record<OrderStatus, 'default' | 'success' | 'warning' | 'error' | 'info' | 'amber'> = {
        pending: 'warning',
        processing: 'info',
        shipped: 'amber',
        delivered: 'success',
        cancelled: 'error',
        refunded: 'default',
    };

    return (
        <Badge variant={variants[status] || 'default'} className={`rounded-full text-[10px] uppercase font-bold px-3 ${className}`}>
            {status}
        </Badge>
    );
}

export function PaymentStatusBadge({ status, className }: { status: string; className?: string }): React.JSX.Element {
    const isPaid = status.toLowerCase() === 'paid';
    return (
        <Badge variant={isPaid ? 'success' : 'default'} className={`rounded-full text-[10px] uppercase font-bold px-3 ${className}`}>
            {status}
        </Badge>
    );
}
