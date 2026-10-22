import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Award,
    Package,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { PageHero } from '@/components/shared/PageHero';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { PRODUCT_GRID_CLASS } from '@/lib/constants';
import { getInitials } from '@/lib/utils';
import type { Brand } from '@/types';

type BrandWithCount = Brand & { products_count?: number };

type BrandListingPageProps = {
    brands: BrandWithCount[];
};

/**
 * A stable accent hue per brand, derived from its name, so each brand keeps
 * the same colour across visits without storing one in the database.
 */
function getBrandHue(name: string): number {
    let hash = 0;

    for (const character of name) {
        hash = (hash * 31 + character.charCodeAt(0)) % 360;
    }

    return hash;
}

function BrandCard({ brand }: { brand: BrandWithCount }): React.JSX.Element {
    const [hasLogoError, setHasLogoError] = useState(false);
    const productCount = brand.products_count ?? 0;
    const showLogo = Boolean(brand.logo) && !hasLogoError;

    return (
        <Link
            href={`/brand/${brand.slug}`}
            style={
                {
                    '--brand-color': `hsl(${getBrandHue(brand.name)} 65% 48%)`,
                } as React.CSSProperties
            }
            className="group overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)] transition duration-300 hover:-translate-y-1 hover:border-[color-mix(in_srgb,var(--brand-color)_40%,var(--color-border))] hover:shadow-[var(--shadow-card-hover)]"
        >
            <div className="relative flex h-24 items-center justify-center overflow-hidden bg-[linear-gradient(135deg,color-mix(in_srgb,var(--brand-color)_18%,var(--color-surface)),color-mix(in_srgb,var(--brand-color)_4%,var(--color-surface)))] sm:h-28">
                <div className="pointer-events-none absolute inset-0 [background-image:radial-gradient(color-mix(in_srgb,var(--brand-color)_35%,transparent)_1px,transparent_1px)] [background-size:12px_12px] opacity-50" />
                <div className="pointer-events-none absolute -right-6 -bottom-8 size-24 rounded-full bg-[var(--brand-color)] opacity-15 blur-2xl transition-opacity duration-300 group-hover:opacity-30" />
                <div className="relative flex size-14 items-center justify-center overflow-hidden rounded-xl bg-white p-2 shadow-[0_10px_24px_-12px_var(--brand-color)] ring-1 ring-black/5 transition duration-300 group-hover:scale-105 sm:size-16">
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
                        className="text-base font-bold text-[var(--brand-color)]"
                        aria-hidden="true"
                    >
                        {getInitials(brand.name)}
                    </span>
                )}
                </div>
            </div>

            <div className="flex items-center justify-between gap-2 p-3">
                <div className="min-w-0">
                <h3 className="truncate text-sm font-semibold text-[var(--color-text-primary)] transition group-hover:text-[var(--color-primary)]">
                    {brand.name}
                </h3>
                </div>
                <span className="shrink-0 rounded-full bg-[var(--color-surface-alt)] px-2 py-1 text-[11px] font-medium text-[var(--color-text-secondary)]">
                    {productCount} {productCount === 1 ? 'item' : 'items'}
                </span>
            </div>
        </Link>
    );
}

function BrandListingContent({
    brands,
}: BrandListingPageProps): React.JSX.Element {
    const [search, setSearch] = useState('');

    const filtered = brands.filter((brand) =>
        brand.name.toLowerCase().includes(search.trim().toLowerCase()),
    );
    const totalProducts = brands.reduce(
        (sum, brand) => sum + (brand.products_count ?? 0),
        0,
    );

    return (
        <>
            <Head title="All Brands | ShoppyDrive" />

            <div className="space-y-6 py-6 lg:py-8">
                <PageHero
                    badge={{ icon: Award, label: 'Brand directory' }}
                    eyebrow="ShoppyDrive"
                    title="Our Brands"
                    description="Discover top brands across fashion, electronics, beauty, home and more — all on ShoppyDrive."
                    chips={[
                        {
                            icon: Award,
                            label: `${brands.length.toLocaleString()} ${brands.length === 1 ? 'brand' : 'brands'}`,
                        },
                        {
                            icon: Package,
                            label: `${totalProducts.toLocaleString()} ${totalProducts === 1 ? 'product' : 'products'}`,
                            iconClassName: 'text-[var(--color-success)]',
                        },
                    ]}
                    aside={
                        <div className="space-y-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                <Input
                                    type="search"
                                    placeholder="Search brands..."
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    aria-label="Search brands"
                                    className="h-11 rounded-full border-[var(--color-border)] bg-[var(--color-surface)] pr-11 pl-11 shadow-sm"
                                />
                                {search ? (
                                    <button
                                        type="button"
                                        onClick={() => setSearch('')}
                                        aria-label="Clear search"
                                        className="absolute top-1/2 right-3 flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-[var(--color-text-muted)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]"
                                    >
                                        <X className="size-4" />
                                    </button>
                                ) : null}
                            </div>
                            {search ? (
                                <p className="px-4 text-xs text-[var(--color-text-muted)]">
                                    Showing {filtered.length} of {brands.length}{' '}
                                    brands
                                </p>
                            ) : null}
                        </div>
                    }
                />

                {/* Brand grid */}
                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {filtered.length === 0 ? (
                        <EmptyState
                            icon={Package}
                            title="No brands found"
                            description={
                                search
                                    ? `No brands match "${search}".`
                                    : 'No brands available yet.'
                            }
                            action={
                                search
                                    ? { label: 'Clear search', href: '/brands' }
                                    : undefined
                            }
                        />
                    ) : (
                        <div className={PRODUCT_GRID_CLASS}>
                            {filtered.map((brand) => (
                                <BrandCard key={brand.id} brand={brand} />
                            ))}
                        </div>
                    )}
                </section>

                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card className="border-[var(--color-border)] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-card-hover)]">
                        <CardContent className="flex flex-col items-center gap-4 px-6 py-8 text-center sm:flex-row sm:justify-between sm:text-left">
                            <div className="space-y-2">
                                <h3 className="text-xl font-semibold">
                                    Can't find your brand?
                                </h3>
                                <p className="text-sm text-white/75">
                                    Browse all products or search across our
                                    full catalog.
                                </p>
                            </div>
                            <Button
                                asChild
                                className="shrink-0 bg-white text-[var(--color-secondary)] hover:bg-white/90"
                            >
                                <Link href="/products">
                                    Browse all products
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

export default function BrandListing({
    brands,
}: BrandListingPageProps): React.JSX.Element {
    return <BrandListingContent brands={brands} />;
}
