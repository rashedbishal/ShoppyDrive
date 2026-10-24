import { Link } from '@inertiajs/react';
import { useState } from 'react';

import { cn, getInitials } from '@/lib/utils';

export type BrandCardData = {
    id: number | string;
    name: string;
    slug: string;
    logo: string | null;
    productsCount: number;
};

type BrandCardProps = {
    brand: BrandCardData;
    className?: string;
};

/** A stable accent hue per brand, derived from its name, so each brand keeps the same colour without storing one in the database. */
function getBrandHue(name: string): number {
    let hash = 0;

    for (const character of name) {
        hash = (hash * 31 + character.charCodeAt(0)) % 360;
    }

    return hash;
}

/**
 * The single brand card used everywhere brands are listed: a bare logo with
 * the name and product count beneath it, no card container.
 */
export function BrandCard({
    brand,
    className,
}: BrandCardProps): React.JSX.Element {
    const [hasLogoError, setHasLogoError] = useState(false);
    const showLogo = Boolean(brand.logo) && !hasLogoError;

    return (
        <Link
            href={`/brand/${brand.slug}`}
            style={
                {
                    '--brand-color': `hsl(${getBrandHue(brand.name)} 65% 48%)`,
                } as React.CSSProperties
            }
            className={cn(
                'group flex flex-col items-center gap-3 rounded-xl p-3 text-center transition duration-300 hover:-translate-y-1',
                className,
            )}
        >
            <div className="flex size-24 items-center justify-center transition duration-300 group-hover:scale-105 sm:size-28">
                {showLogo ? (
                    <img
                        src={brand.logo ?? ''}
                        alt={`${brand.name} logo`}
                        loading="lazy"
                        decoding="async"
                        onError={() => setHasLogoError(true)}
                        className="h-full w-full object-contain"
                    />
                ) : (
                    <span
                        className="text-3xl font-bold text-[var(--brand-color)]"
                        aria-hidden="true"
                    >
                        {getInitials(brand.name)}
                    </span>
                )}
            </div>

            <div className="min-w-0">
                <h3 className="truncate text-sm font-semibold text-[var(--color-text-primary)] transition group-hover:text-[var(--color-primary)]">
                    {brand.name}
                </h3>
                <p className="text-xs text-[var(--color-text-muted)]">
                    {brand.productsCount}{' '}
                    {brand.productsCount === 1 ? 'item' : 'items'}
                </p>
            </div>
        </Link>
    );
}
