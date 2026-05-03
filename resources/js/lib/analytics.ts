import type { Cart, Order, Product } from '@/types';

interface CookieConsent {
    analytics: boolean;
    marketing: boolean;
    timestamp: number;
}

function getConsent(): CookieConsent | null {
    if (typeof window === 'undefined') return null;
    const consent = localStorage.getItem('cookie_consent');
    return consent ? JSON.parse(consent) : null;
}

export function trackPageView(url: string): void {
    const consent = getConsent();
    if (!consent?.analytics) return;

    if (typeof window.gtag === 'function') {
        window.gtag('config', window.GA_ID, {
            page_path: url,
        });
    }
}

export function trackAddToCart(product: Product, quantity: number): void {
    const consent = getConsent();
    if (!consent?.analytics) return;

    if (typeof window.gtag === 'function') {
        window.gtag('event', 'add_to_cart', {
            currency: 'BDT',
            value: (product.flash_sale_price || product.price) * quantity,
            items: [
                {
                    item_id: product.sku || product.id.toString(),
                    item_name: product.name,
                    item_brand: product.brand?.name,
                    item_category: product.category.name,
                    price: product.flash_sale_price || product.price,
                    quantity: quantity,
                },
            ],
        });
    }
}

export function trackViewItem(product: Product): void {
    const consent = getConsent();
    if (!consent?.analytics) return;

    if (typeof window.gtag === 'function') {
        window.gtag('event', 'view_item', {
            currency: 'BDT',
            value: product.flash_sale_price || product.price,
            items: [
                {
                    item_id: product.sku || product.id.toString(),
                    item_name: product.name,
                    item_brand: product.brand?.name,
                    item_category: product.category.name,
                    price: product.flash_sale_price || product.price,
                },
            ],
        });
    }
}

export function trackBeginCheckout(cart: Cart): void {
    const consent = getConsent();
    if (!consent?.analytics) return;

    if (typeof window.gtag === 'function') {
        window.gtag('event', 'begin_checkout', {
            currency: 'BDT',
            value: cart.total,
            items: cart.items.map((item) => ({
                item_id: item.product.sku || item.product.id.toString(),
                item_name: item.product.name,
                item_brand: item.product.brand?.name,
                item_category: item.product.category.name,
                price: item.price,
                quantity: item.quantity,
            })),
        });
    }
}

export function trackPurchase(order: Order): void {
    const consent = getConsent();
    if (!consent?.analytics) return;

    if (typeof window.gtag === 'function') {
        window.gtag('event', 'purchase', {
            transaction_id: order.order_number,
            value: order.total,
            currency: 'BDT',
            tax: order.tax,
            shipping: order.shipping_cost,
            items: order.items.map((item) => ({
                item_id: item.sku || item.id.toString(),
                item_name: item.product_name,
                quantity: item.quantity,
                price: item.price,
            })),
        });
    }
}

declare global {
    interface Window {
        gtag: (...args: any[]) => void;
        GA_ID: string;
    }
}
