import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    Award,
    Package,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
            className="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)] transition duration-300 hover:-translate-y-1 hover:border-[color-mix(in_srgb,var(--brand-color)_35%,var(--color-border))] hover:shadow-[var(--shadow-card-hover)]"
        >
            {/* Logo stage — tinted with the brand's accent colour */}
            <div className="relative flex aspect-[16/10] items-center justify-center overflow-hidden bg-[linear-gradient(135deg,color-mix(in_srgb,var(--brand-color)_16%,var(--color-surface)),color-mix(in_srgb,var(--brand-color)_4%,var(--color-surface)))]">
                <div className="pointer-events-none absolute inset-0 [background-image:radial-gradient(color-mix(in_srgb,var(--brand-color)_40%,transparent)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)] [background-size:14px_14px] opacity-40" />
                <div className="pointer-events-none absolute size-32 rounded-full bg-[var(--brand-color)] opacity-20 blur-3xl transition-opacity duration-500 group-hover:opacity-40" />
                <div className="pointer-events-none absolute inset-y-0 -left-1/2 w-1/2 -skew-x-12 bg-gradient-to-r from-transparent via-white/40 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-[400%]" />

                <div className="relative flex size-[4.5rem] items-center justify-center rounded-2xl bg-white p-3 shadow-[0_12px_32px_-14px_var(--brand-color)] ring-1 ring-black/5 transition duration-500 group-hover:scale-105 group-hover:shadow-[0_16px_40px_-12px_var(--brand-color)] sm:size-24">
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
                            className="font-display text-2xl font-bold tracking-tight text-[var(--brand-color)] sm:text-3xl"
                            aria-hidden="true"
                        >
                            {getInitials(brand.name)}
                        </span>
                    )}
                </div>
            </div>

            <div className="flex flex-1 flex-col gap-1 p-3 sm:p-4">
                <h3 className="truncate text-sm font-semibold text-[var(--color-text-primary)] transition group-hover:text-[var(--color-primary)] sm:text-base">
                    {brand.name}
                </h3>
                <p className="line-clamp-2 h-8 text-xs leading-4 text-[var(--color-text-secondary)] sm:h-10 sm:text-sm sm:leading-5">
                    {brand.description ||
                        `Shop the ${brand.name} collection on ShoppyDrive.`}
                </p>

                <div className="mt-auto flex items-center justify-between gap-2 border-t border-dashed border-[var(--color-border)] pt-2.5 sm:pt-3">
                    <span className="inline-flex min-w-0 items-center gap-1.5 text-xs font-medium text-[var(--color-text-secondary)]">
                        <Package className="size-3.5 shrink-0 text-[var(--brand-color)]" />
                        <span className="truncate">
                            {productCount}{' '}
                            {productCount === 1 ? 'product' : 'products'}
                        </span>
                    </span>
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full border border-[var(--color-border)] text-[var(--color-text-secondary)] transition duration-300 group-hover:border-transparent group-hover:bg-[var(--color-primary)] group-hover:text-white">
                        <ArrowUpRight className="size-4 transition-transform duration-300 group-hover:rotate-45" />
                    </span>
                </div>
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

            <div className="space-y-8 py-6 lg:space-y-10 lg:py-10">
                {/* Hero */}
                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="relative isolate overflow-hidden rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] px-5 py-8 shadow-[var(--shadow-card)] sm:px-8 sm:py-10 lg:px-12">
                        <div className="pointer-events-none absolute -top-24 -right-16 -z-10 size-72 rounded-full bg-[var(--color-primary)] opacity-10 blur-3xl" />
                        <div className="pointer-events-none absolute -bottom-28 left-1/4 -z-10 size-72 rounded-full bg-[var(--color-accent)] opacity-15 blur-3xl" />
                        <div className="pointer-events-none absolute inset-0 -z-10 [background-image:linear-gradient(var(--color-border)_1px,transparent_1px),linear-gradient(90deg,var(--color-border)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black,transparent)] [background-size:32px_32px] opacity-40" />

                        <div className="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                            <div className="max-w-2xl space-y-4">
                                <Badge variant="amber" className="w-fit">
                                    <Award className="size-3.5" />
                                    Brand directory
                                </Badge>
                                <div className="space-y-2">
                                    <h1 className="font-display text-3xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-4xl lg:text-5xl">
                                        Our Brands
                                    </h1>
                                    <p className="text-sm leading-7 text-[var(--color-text-secondary)] sm:text-base">
                                        Discover top brands across fashion,
                                        electronics, beauty, home and more — all
                                        on ShoppyDrive.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-3">
                                    {[
                                        {
                                            value: brands.length,
                                            label:
                                                brands.length === 1
                                                    ? 'brand'
                                                    : 'brands',
                                        },
                                        {
                                            value: totalProducts,
                                            label:
                                                totalProducts === 1
                                                    ? 'product'
                                                    : 'products',
                                        },
                                    ].map((stat) => (
                                        <div
                                            key={stat.label}
                                            className="inline-flex items-baseline gap-1.5 rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-4 py-2"
                                        >
                                            <span className="font-display text-lg font-bold text-[var(--color-text-primary)]">
                                                {stat.value.toLocaleString()}
                                            </span>
                                            <span className="text-sm text-[var(--color-text-secondary)]">
                                                {stat.label}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="w-full space-y-2 lg:max-w-sm">
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
                                        className="h-12 rounded-full border-[var(--color-border)] bg-[var(--color-surface)] pr-11 pl-11 shadow-sm"
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
                                        Showing {filtered.length} of{' '}
                                        {brands.length} brands
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </section>

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
