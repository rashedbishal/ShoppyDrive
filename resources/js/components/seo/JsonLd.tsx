import React from 'react';
import type { Product, AppSettings } from '@/types';

interface JsonLdProps {
    schema: object;
}

export function JsonLd({ schema }: JsonLdProps): React.JSX.Element {
    return (
        <script
            type="application/ld+json"
            dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
        />
    );
}

export function generateProductSchema(product: Product) {
    return {
        '@context': 'https://schema.org/',
        '@type': 'Product',
        name: product.name,
        image: (product.images || []).map((img) => img.path),
        description: product.short_description || product.description,
        sku: product.sku,
        brand: {
            '@type': 'Brand',
            name: product.brand?.name || 'ShoppyDrive',
        },
        offers: {
            '@type': 'Offer',
            url: typeof window !== 'undefined' ? window.location.href : '',
            priceCurrency: 'BDT',
            price: product.flash_sale_price || product.price,
            availability: product.stock_quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            itemCondition: 'https://schema.org/NewCondition',
        },
        aggregateRating: product.reviews_count ? {
            '@type': 'AggregateRating',
            ratingValue: product.average_rating || 5,
            reviewCount: product.reviews_count,
        } : undefined,
    };
}

export function generateBreadcrumbSchema(items: { label: string; href?: string }[]) {
    return {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: items.map((item, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            name: item.label,
            item: item.href ? (item.href.startsWith('http') ? item.href : `${window.location.origin}${item.href}`) : undefined,
        })),
    };
}

export function generateOrganizationSchema(settings: AppSettings) {
    return {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: settings.site_name,
        url: window.location.origin,
        logo: settings.logo,
        contactPoint: {
            '@type': 'ContactPoint',
            telephone: settings.contact_phone,
            contactType: 'customer service',
            email: settings.contact_email,
        },
        sameAs: Object.values(settings.social_links).filter(Boolean),
    };
}
