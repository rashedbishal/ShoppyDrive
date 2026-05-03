import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Package, Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn, getInitials } from '@/lib/utils';
import type { Brand } from '@/types';

const BRAND_ACCENTS = [
    'bg-sky-50 text-sky-700 border-sky-200',
    'bg-amber-50 text-amber-700 border-amber-200',
    'bg-emerald-50 text-emerald-700 border-emerald-200',
    'bg-rose-50 text-rose-700 border-rose-200',
    'bg-indigo-50 text-indigo-700 border-indigo-200',
    'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
    'bg-orange-50 text-orange-700 border-orange-200',
    'bg-teal-50 text-teal-700 border-teal-200',
    'bg-violet-50 text-violet-700 border-violet-200',
    'bg-blue-50 text-blue-700 border-blue-200',
];

type BrandWithCount = Brand & { products_count?: number };

type BrandListingPageProps = {
    brands: BrandWithCount[];
};

function BrandCard({ brand, accent }: { brand: BrandWithCount; accent: string }): React.JSX.Element {
    return (
        <Link
            href={`/brand/${brand.slug}`}
            className="group block"
        >
            <Card className="h-full overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)] transition-all duration-200 hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]">
                <CardContent className="flex flex-col items-center gap-4 p-6 text-center">
                    <div
                        className={cn(
                            'flex size-16 items-center justify-center rounded-2xl border text-xl font-bold transition-transform duration-200 group-hover:scale-110',
                            accent,
                        )}
                        aria-hidden="true"
                    >
                        {brand.logo ? (
                            <img src={brand.logo} alt={brand.name} className="size-10 object-contain" />
                        ) : (
                            getInitials(brand.name)
                        )}
                    </div>
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold text-[var(--color-text-primary)] transition group-hover:text-[var(--color-primary)]">
                            {brand.name}
                        </h3>
                        {brand.description ? (
                            <p className="text-sm leading-5 text-[var(--color-text-secondary)]">
                                {brand.description}
                            </p>
                        ) : null}
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="info" className="text-xs">
                            {brand.products_count ?? 0} products
                        </Badge>
                    </div>
                    <div className="mt-auto flex items-center gap-1 text-xs font-semibold text-[var(--color-primary)] opacity-0 transition group-hover:opacity-100">
                        Shop now
                        <ArrowRight className="size-3.5 transition group-hover:translate-x-0.5" />
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

function BrandCardSkeleton(): React.JSX.Element {
    return (
        <Card className="border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)]">
            <CardContent className="flex flex-col items-center gap-4 p-6">
                <Skeleton className="size-16 rounded-2xl" />
                <div className="w-full space-y-2 text-center">
                    <Skeleton className="mx-auto h-4 w-24" />
                    <Skeleton className="mx-auto h-3 w-32" />
                </div>
                <Skeleton className="h-5 w-16 rounded-full" />
            </CardContent>
        </Card>
    );
}

function BrandListingContent({ brands }: BrandListingPageProps): React.JSX.Element {
    const [search, setSearch] = useState('');

    const filtered = brands.filter((brand) =>
        brand.name.toLowerCase().includes(search.toLowerCase()),
    );

    return (
        <>
            <Head title="All Brands | ShoppyDrive" />

            <div className="space-y-10 py-8 lg:py-12">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="space-y-2">
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--color-text-muted)]">
                            ShoppyDrive
                        </p>
                        <h1 className="font-display text-3xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-4xl">
                            Our Brands
                        </h1>
                        <p className="max-w-2xl text-sm leading-7 text-[var(--color-text-secondary)] sm:text-base">
                            Discover top brands across fashion, electronics, beauty, home and more — all on ShoppyDrive.
                        </p>
                    </div>
                </div>

                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="relative max-w-sm">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                        <Input
                            type="search"
                            placeholder="Search brands..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </div>

                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {filtered.length === 0 ? (
                        <EmptyState
                            icon={Package}
                            title="No brands found"
                            description={search ? `No brands match "${search}".` : 'No brands available yet.'}
                            action={search ? { label: 'Clear search', href: '/brands' } : undefined}
                        />
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                            {filtered.map((brand, index) => (
                                <BrandCard
                                    key={brand.id}
                                    brand={brand}
                                    accent={BRAND_ACCENTS[index % BRAND_ACCENTS.length]}
                                />
                            ))}
                        </div>
                    )}
                </div>

                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card className="border-[var(--color-border)] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-card-hover)]">
                        <CardContent className="flex flex-col items-center gap-4 px-6 py-8 text-center sm:flex-row sm:justify-between sm:text-left">
                            <div className="space-y-2">
                                <h3 className="text-xl font-semibold">Can't find your brand?</h3>
                                <p className="text-sm text-white/75">
                                    Browse all products or search across our full catalog.
                                </p>
                            </div>
                            <Button asChild className="shrink-0 bg-white text-[var(--color-secondary)] hover:bg-white/90">
                                <Link href="/products">
                                    Browse all products
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

export default function BrandListing({ brands }: BrandListingPageProps): React.JSX.Element {
    return (
        <BrandListingContent brands={brands} />
    );
}
