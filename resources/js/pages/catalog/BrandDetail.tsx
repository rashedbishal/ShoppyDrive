import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Award, Package, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import { PageHero } from '@/components/shared/PageHero';
import { ProductCard } from '@/components/shared/ProductCard';
import { EmptyState } from '@/components/ui/empty-state';
import { Pagination } from '@/components/ui/pagination';
import { PRODUCT_GRID_CLASS } from '@/lib/constants';
import { cn, getInitials } from '@/lib/utils';
import type { Brand, PaginatedData, Product } from '@/types';

const BRAND_ACCENTS: Record<string, string> = {
    samsung: 'bg-sky-50 text-sky-700',
    aarong: 'bg-amber-50 text-amber-700',
    anker: 'bg-emerald-50 text-emerald-700',
    walton: 'bg-rose-50 text-rose-700',
    nike: 'bg-indigo-50 text-indigo-700',
    cerave: 'bg-fuchsia-50 text-fuchsia-700',
    levis: 'bg-blue-50 text-blue-700',
    fossil: 'bg-orange-50 text-orange-700',
    adidas: 'bg-lime-50 text-lime-700',
    bata: 'bg-teal-50 text-teal-700',
    xiaomi: 'bg-violet-50 text-violet-700',
    puma: 'bg-pink-50 text-pink-700',
    titan: 'bg-yellow-50 text-yellow-700',
    hm: 'bg-gray-50 text-gray-700',
    apple: 'bg-zinc-50 text-zinc-700',
};

type BrandDetailPageProps = {
    brand: Brand & { products_count?: number };
    products: PaginatedData<Product>;
};

function BrandDetailContent({
    brand,
    products,
}: BrandDetailPageProps): React.JSX.Element {
    const accent = BRAND_ACCENTS[brand.slug] ?? 'bg-gray-50 text-gray-700';
    const [hasLogoError, setHasLogoError] = useState(false);
    const showLogo = Boolean(brand.logo) && !hasLogoError;

    return (
        <>
            <Head title={brand.name} />

            <div className="space-y-6 py-6 lg:py-8">
                <PageHero
                    badge={{ icon: Award, label: 'Brand' }}
                    eyebrow={
                        <Link
                            href="/brands"
                            className="inline-flex items-center gap-1.5 transition hover:text-[var(--color-primary)]"
                        >
                            <ArrowLeft className="size-3.5" />
                            All brands
                        </Link>
                    }
                    title={brand.name}
                    description={brand.description}
                    chips={[
                        {
                            icon: Package,
                            label: `${(brand.products_count ?? products.total).toLocaleString()} ${(brand.products_count ?? products.total) === 1 ? 'product' : 'products'}`,
                        },
                    ]}
                    aside={
                        <div className="flex lg:justify-end">
                            <div
                                className={cn(
                                    'flex size-20 items-center justify-center rounded-2xl text-2xl font-bold shadow-sm ring-1 ring-black/5 lg:size-24',
                                    accent,
                                )}
                            >
                                {showLogo ? (
                                    <img
                                        src={brand.logo ?? ''}
                                        alt={`${brand.name} logo`}
                                        onError={() => setHasLogoError(true)}
                                        className="size-12 object-contain lg:size-14"
                                    />
                                ) : (
                                    getInitials(brand.name)
                                )}
                            </div>
                        </div>
                    }
                />

                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {products.data.length === 0 ? (
                        <EmptyState
                            icon={ShoppingBag}
                            title="No products yet"
                            description={`${brand.name} products will appear here once they are added.`}
                            action={{
                                label: 'Browse all products',
                                href: '/products',
                            }}
                        />
                    ) : (
                        <div className="space-y-8">
                            <div className={PRODUCT_GRID_CLASS}>
                                {products.data.map((product, index) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        index={index}
                                    />
                                ))}
                            </div>
                            {products.last_page > 1 ? (
                                <Pagination
                                    data={products}
                                    onPageChange={(page) =>
                                        router.get(
                                            route('brand.show', brand.slug),
                                            { page },
                                        )
                                    }
                                />
                            ) : null}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

export default function BrandDetail(
    props: BrandDetailPageProps,
): React.JSX.Element {
    return <BrandDetailContent {...props} />;
}
