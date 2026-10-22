import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShoppingBag } from 'lucide-react';
import { ProductCard } from '@/components/shared/ProductCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

function BrandDetailContent({ brand, products }: BrandDetailPageProps): React.JSX.Element {
    const accent = BRAND_ACCENTS[brand.slug] ?? 'bg-gray-50 text-gray-700';

    return (
        <>
            <Head title={`${brand.name} | ShoppyDrive`} />

            <div className="space-y-10 py-8 lg:py-12">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Button asChild variant="ghost" className="mb-4 -ml-2 gap-2 text-[var(--color-text-secondary)]">
                        <Link href="/brands">
                            <ArrowLeft className="size-4" />
                            All Brands
                        </Link>
                    </Button>

                    <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
                        <div className={cn('flex size-20 shrink-0 items-center justify-center rounded-2xl text-2xl font-bold', accent)}>
                            {brand.logo ? (
                                <img src={brand.logo} alt={brand.name} className="size-12 object-contain" />
                            ) : (
                                getInitials(brand.name)
                            )}
                        </div>
                        <div className="space-y-1">
                            <h1 className="font-display text-3xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-4xl">
                                {brand.name}
                            </h1>
                            {brand.description ? (
                                <p className="text-sm leading-7 text-[var(--color-text-secondary)] sm:text-base">
                                    {brand.description}
                                </p>
                            ) : null}
                            <Badge variant="info">{brand.products_count ?? products.total} products</Badge>
                        </div>
                    </div>
                </div>

                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {products.data.length === 0 ? (
                        <EmptyState
                            icon={ShoppingBag}
                            title="No products yet"
                            description={`${brand.name} products will appear here once they are added.`}
                            action={{ label: 'Browse all products', href: '/products' }}
                        />
                    ) : (
                        <div className="space-y-8">
                            <div className={PRODUCT_GRID_CLASS}>
                                {products.data.map((product, index) => (
                                    <ProductCard key={product.id} product={product} index={index} />
                                ))}
                            </div>
                            {products.last_page > 1 ? (
                                <Pagination
                                    currentPage={products.current_page}
                                    lastPage={products.last_page}
                                    total={products.total}
                                    perPage={products.per_page}
                                    links={products.links}
                                />
                            ) : null}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

export default function BrandDetail(props: BrandDetailPageProps): React.JSX.Element {
    return (
        <BrandDetailContent {...props} />
    );
}
