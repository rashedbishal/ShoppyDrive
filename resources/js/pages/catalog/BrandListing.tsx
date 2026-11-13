import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Award, Package, Search, X } from 'lucide-react';
import { useState } from 'react';
import { BrandCard } from '@/components/shared/BrandCard';
import { PageHero } from '@/components/shared/PageHero';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import type { Brand } from '@/types';

type BrandWithCount = Brand & { products_count?: number };

type BrandListingPageProps = {
    brands: BrandWithCount[];
};

/** 2 columns on mobile, up to 5 on desktop, matching the plain logo-card design. */
const BRAND_GRID_CLASS =
    'grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 lg:grid-cols-5';

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
            <Head title="All Brands" />

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
                />

                {/* Search */}
                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-xl space-y-2">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <Input
                                maxLength={64}
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
                            <p className="px-4 text-xs text-[var(--color-text-secondary)]">
                                Showing {filtered.length} of {brands.length}{' '}
                                brands
                            </p>
                        ) : null}
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
                        <div className={BRAND_GRID_CLASS}>
                            {filtered.map((brand) => (
                                <BrandCard
                                    key={brand.id}
                                    brand={{
                                        id: brand.id,
                                        name: brand.name,
                                        slug: brand.slug,
                                        logo: brand.logo ?? null,
                                        productsCount:
                                            brand.products_count ?? 0,
                                    }}
                                />
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
