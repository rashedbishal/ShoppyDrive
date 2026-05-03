import { Head } from '@inertiajs/react';
import React from 'react';

interface PageMetaProps {
    title?: string;
    description?: string;
    image?: string;
    type?: 'website' | 'product' | 'article';
    canonicalUrl?: string;
    noIndex?: boolean;
}

export function PageMeta({
    title,
    description,
    image,
    type = 'website',
    canonicalUrl,
    noIndex = false,
}: PageMetaProps): React.JSX.Element {
    const siteName = 'ShoppyDrive';
    const fullTitle = title ? `${title} | ${siteName}` : siteName;
    const defaultDescription = 'Premium e-commerce destination in Bangladesh for electronics, lifestyle, and more.';
    const metaDescription = description || defaultDescription;
    const metaImage = image || '/og-image.webp'; // Replace with actual default OG image path
    const url = canonicalUrl || typeof window !== 'undefined' ? window.location.href : '';

    return (
        <Head>
            <title>{fullTitle}</title>
            <meta name="description" content={metaDescription} />
            {noIndex && <meta name="robots" content="noindex, nofollow" />}
            
            {/* Open Graph */}
            <meta property="og:title" content={fullTitle} />
            <meta property="og:description" content={metaDescription} />
            <meta property="og:image" content={metaImage} />
            <meta property="og:url" content={url} />
            <meta property="og:type" content={type} />
            <meta property="og:site_name" content={siteName} />

            {/* Twitter */}
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content={fullTitle} />
            <meta name="twitter:description" content={metaDescription} />
            <meta name="twitter:image" content={metaImage} />

            {canonicalUrl && <link rel="canonical" href={canonicalUrl} />}
        </Head>
    );
}
