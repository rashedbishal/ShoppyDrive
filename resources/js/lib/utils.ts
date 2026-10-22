import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import {
    CURRENCY_CODE,
    CURRENCY_LOCALE,
    CURRENCY_SYMBOL,
} from '@/lib/constants';
import type { Product, ProductVariant, StockStatus } from '@/types';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatCurrency(amount: number): string {
    const formatter = new Intl.NumberFormat(CURRENCY_LOCALE, {
        currency: CURRENCY_CODE,
        currencyDisplay: 'symbol',
        maximumFractionDigits: 2,
        minimumFractionDigits: 2,
        style: 'currency',
    });

    return formatter
        .format(amount)
        .replace('BDT', CURRENCY_SYMBOL)
        .replace(/\u00a0/g, '');
}

export function formatDate(
    date: string | number | Date,
    format: 'short' | 'medium' | 'long' | 'full' = 'medium',
): string {
    const options: Record<
        'short' | 'medium' | 'long' | 'full',
        Intl.DateTimeFormatOptions
    > = {
        short: { day: 'numeric', month: 'short', year: '2-digit' },
        medium: { day: 'numeric', month: 'short', year: 'numeric' },
        long: { day: 'numeric', month: 'long', year: 'numeric' },
        full: {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        },
    };

    return new Intl.DateTimeFormat('en-GB', options[format]).format(
        new Date(date),
    );
}

export function truncate(value: string, length: number): string {
    if (value.length <= length) {
        return value;
    }

    return `${value.slice(0, Math.max(0, length - 1)).trimEnd()}…`;
}

export function slugify(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

export function getInitials(name: string): string {
    const parts = name
        .split(/\s+/)
        .map((part) => part.trim())
        .filter(Boolean);

    if (parts.length === 0) {
        return '';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0].slice(0, 1)}${parts[parts.length - 1].slice(0, 1)}`.toUpperCase();
}

export type DebouncedFunction<T extends (...args: unknown[]) => void> = ((
    ...args: Parameters<T>
) => void) & {
    cancel: () => void;
    flush: () => void;
};

export function debounce<T extends (...args: unknown[]) => void>(
    fn: T,
    delay: number,
): DebouncedFunction<T> {
    let timeoutId: ReturnType<typeof setTimeout> | undefined;
    let lastArgs: Parameters<T> | undefined;

    const debounced = ((...args: Parameters<T>) => {
        lastArgs = args;

        if (timeoutId !== undefined) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => {
            timeoutId = undefined;
            if (lastArgs !== undefined) {
                fn(...lastArgs);
            }
        }, delay);
    }) as DebouncedFunction<T>;

    debounced.cancel = () => {
        if (timeoutId !== undefined) {
            clearTimeout(timeoutId);
            timeoutId = undefined;
        }
        lastArgs = undefined;
    };

    debounced.flush = () => {
        if (timeoutId !== undefined) {
            clearTimeout(timeoutId);
            timeoutId = undefined;
        }

        if (lastArgs !== undefined) {
            fn(...lastArgs);
            lastArgs = undefined;
        }
    };

    return debounced;
}

export function isVideoUrl(url: string): boolean {
    return /\.(mp4|webm|mov|ogg)$/i.test(url);
}

export function getDiscountPercent(
    price: number,
    comparePrice: number,
): number {
    if (comparePrice <= 0 || comparePrice <= price) {
        return 0;
    }

    return Math.round(((comparePrice - price) / comparePrice) * 100);
}

export function isInStock(product: Pick<Product, 'stock_quantity'>): boolean {
    return product.stock_quantity > 0;
}

export function getStockStatus(
    quantity: number,
    threshold: number,
): StockStatus {
    if (quantity <= 0) {
        return 'out_of_stock';
    }

    if (quantity <= threshold) {
        return 'low_stock';
    }

    return 'in_stock';
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}
