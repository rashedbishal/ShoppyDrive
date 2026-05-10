import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Heart, ShoppingBag, Star } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Pagination } from '@/components/ui/pagination';
import { StarRating } from '@/components/ui/star-rating';
import { cn, formatCurrency, getDiscountPercent, getInitials } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
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

function ProductCard({ product }: { product: Product }): React.JSX.Element {
    const addItem = useCartStore((state) => state.addItem);
    const addToast = useUiStore((state) => state.addToast);
    const openCart = useUiStore((state) => state.openCart);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    const wishlisted = useWishlistStore((state) => state.isWishlisted(product.id));
    const [hovered, setHovered] = useState(false);

    const primaryImage = product.images?.find((img) => img.is_primary) ?? product.images?.[0];
    const secondaryImage = product.images?.find((img) => !img.is_primary);
    const primaryUrl = primaryImage?.path ?? product.primary_image_url ?? '';
    const secondaryUrl = secondaryImage?.path ?? '';
    const discount = product.discount_percentage ?? getDiscountPercent(product.price, product.compare_price ?? 0);
    const isOutOfStock = product.stock_quantity <= 0;

    const handleAddToCart = (): void => {
        addItem({ product, quantity: 1 });
        addToast({ type: 'success', title: 'Added to cart', message: `${product.name} added.` });
    };

    return (
        <Card
            className="group overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)] transition-all duration-200 hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]"
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
        >
            <div className="relative">
                <Link href={`/product/${product.slug}`} className="block">
                    <div className="relative aspect-[4/3] overflow-hidden bg-[var(--color-surface-alt)]">
                        {primaryUrl ? (
                            <img
                                src={primaryUrl}
                                alt={product.name}
                                loading="lazy"
                                className={cn(
                                    'h-full w-full object-cover transition duration-500',
                                    hovered && secondaryUrl ? 'opacity-0 scale-105' : 'opacity-100',
                                )}
                            />
                        ) : (
                            <div className="flex h-full items-center justify-center text-[var(--color-text-muted)]">
                                <ShoppingBag className="size-12" />
                            </div>
                        )}
                        {secondaryUrl ? (
                            <img
                                src={secondaryUrl}
                                alt=""
                                aria-hidden="true"
                                loading="lazy"
                                className={cn(
                                    'absolute inset-0 h-full w-full object-cover transition duration-500',
                                    hovered ? 'opacity-100 scale-105' : 'opacity-0',
                                )}
                            />
                        ) : null}

                        {discount > 0 ? (
                            <Badge variant="amber" className="absolute left-3 top-3">
                                -{discount}%
                            </Badge>
                        ) : null}

                        <button
                            type="button"
                            onClick={(e) => { e.preventDefault(); e.stopPropagation(); toggleWishlist(product.id); }}
                            aria-label={wishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
                            className={cn(
                                'absolute right-3 top-3 inline-flex size-9 items-center justify-center rounded-full border border-[var(--color-border)] bg-white/95 shadow-sm transition hover:scale-105',
                                wishlisted ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-secondary)]',
                            )}
                        >
                            <Heart className={cn('size-4', wishlisted ? 'fill-current' : '')} />
                        </button>
                    </div>
                </Link>
            </div>

            <div className="space-y-3 p-4">
                <div>
                    <Link
                        href={`/product/${product.slug}`}
                        className="line-clamp-2 text-sm font-semibold leading-6 text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)]"
                    >
                        {product.name}
                    </Link>
                </div>

                <div className="flex items-center gap-2">
                    <StarRating rating={product.average_rating ?? 0} size="sm" className="text-[var(--color-accent)]" />
                    <span className="text-xs text-[var(--color-text-secondary)]">({product.reviews_count ?? 0})</span>
                </div>

                <div className="flex items-end gap-2">
                    <p className="text-lg font-bold text-[var(--color-primary)]">
                        {formatCurrency(product.price)}
                    </p>
                    {product.compare_price && product.compare_price > product.price ? (
                        <p className="pb-0.5 text-sm text-[var(--color-text-muted)] line-through">
                            {formatCurrency(product.compare_price)}
                        </p>
                    ) : null}
                </div>

                <Button
                    fullWidth
                    disabled={isOutOfStock}
                    onClick={handleAddToCart}
                    className="transition-transform duration-200 group-hover:-translate-y-0.5"
                >
                    {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                </Button>
            </div>
        </Card>
    );
}

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
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {products.data.map((product) => (
                                    <ProductCard key={product.id} product={product} />
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
