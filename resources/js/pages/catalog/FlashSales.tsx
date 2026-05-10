import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Clock3, Heart, ShoppingBag, Zap } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { StarRating } from '@/components/ui/star-rating';
import { cn, formatCurrency, getDiscountPercent } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type { PaginatedData, Product } from '@/types';

type FlashSaleItem = {
    id: number;
    sale_price: number;
    sold_count: number;
    stock_limit: number;
    product: Product;
};

type FlashSale = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    items: FlashSaleItem[];
};

type FlashSalesPageProps = {
    activeSale: FlashSale | null;
    upcomingSales: FlashSale[];
    featuredProducts: Product[];
};

function CountdownTimer({ endsAt }: { endsAt: string }): React.JSX.Element {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = window.setInterval(() => setNow(new Date()), 1000);
        return () => window.clearInterval(id);
    }, []);

    const remaining = useMemo(() => {
        const diff = Math.max(0, new Date(endsAt).getTime() - now.getTime());
        const total = Math.floor(diff / 1000);
        return {
            diff,
            days: Math.floor(total / 86_400),
            hours: Math.floor((total % 86_400) / 3_600),
            minutes: Math.floor((total % 3_600) / 60),
            seconds: total % 60,
        };
    }, [endsAt, now]);

    if (remaining.diff <= 0) {
        return <span className="font-mono text-base font-semibold text-[var(--color-accent)]">Sale ended</span>;
    }

    const segments = [
        { label: 'days', value: remaining.days },
        { label: 'hrs', value: remaining.hours },
        { label: 'min', value: remaining.minutes },
        { label: 'sec', value: remaining.seconds },
    ];

    return (
        <div className="flex flex-wrap items-center gap-2 font-mono">
            {segments.map((seg) => (
                <div
                    key={seg.label}
                    className="flex min-w-[4rem] flex-col items-center rounded-2xl border border-white/10 bg-white/10 px-3 py-2 text-center backdrop-blur-md"
                >
                    <span className="text-2xl font-bold text-white">{String(seg.value).padStart(2, '0')}</span>
                    <span className="text-[10px] uppercase tracking-[0.2em] text-white/70">{seg.label}</span>
                </div>
            ))}
        </div>
    );
}

function FlashProductCard({ item }: { item: FlashSaleItem }): React.JSX.Element {
    const { product } = item;
    const addItem = useCartStore((state) => state.addItem);
    const addToast = useUiStore((state) => state.addToast);
    const openCart = useUiStore((state) => state.openCart);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    const wishlisted = useWishlistStore((state) => state.isWishlisted(product.id));
    const [hovered, setHovered] = useState(false);

    const primaryUrl = product.images?.find((i) => i.is_primary)?.path ?? product.primary_image_url ?? '';
    const secondaryUrl = product.images?.find((i) => !i.is_primary)?.path ?? '';
    const salePrice = item.sale_price;
    const discount = getDiscountPercent(salePrice, product.compare_price ?? product.price);
    const progress = Math.min(100, Math.round((item.sold_count / item.stock_limit) * 100));
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

                        <Badge className="absolute left-3 top-3 bg-[var(--color-accent)] text-[var(--color-secondary)]">
                            <Zap className="size-3" />
                            FLASH {discount > 0 ? `-${discount}%` : 'DEAL'}
                        </Badge>

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
                <div className="space-y-1">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--color-text-muted)]">
                        {product.brand?.name ?? 'ShoppyDrive'}
                    </p>
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
                    <p className="text-xl font-bold text-[var(--color-primary)]">
                        {formatCurrency(salePrice)}
                    </p>
                    {product.price > salePrice ? (
                        <p className="pb-0.5 text-sm text-[var(--color-text-muted)] line-through">
                            {formatCurrency(product.price)}
                        </p>
                    ) : null}
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between text-xs text-[var(--color-text-secondary)]">
                        <span>{item.sold_count} sold</span>
                        <span>{item.stock_limit - item.sold_count} left</span>
                    </div>
                    <div
                        className="h-2 overflow-hidden rounded-full bg-[var(--color-surface-alt)]"
                        aria-label={`${progress}% sold`}
                    >
                        <div
                            className="h-full rounded-full bg-[var(--color-accent)] transition-all"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </div>

                <Button
                    fullWidth
                    disabled={isOutOfStock}
                    onClick={handleAddToCart}
                    className="transition-transform duration-200 group-hover:-translate-y-0.5"
                >
                    {isOutOfStock ? 'Sold Out' : 'Add to Cart'}
                </Button>
            </div>
        </Card>
    );
}

function FeaturedProductCard({ product }: { product: Product }): React.JSX.Element {
    const addItem = useCartStore((state) => state.addItem);
    const addToast = useUiStore((state) => state.addToast);
    const openCart = useUiStore((state) => state.openCart);
    const primaryUrl = product.images?.find((i) => i.is_primary)?.path ?? product.primary_image_url ?? '';
    const discount = getDiscountPercent(product.price, product.compare_price ?? 0);
    const isOutOfStock = product.stock_quantity <= 0;

    return (
        <Card className="group overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)] transition-all hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]">
            <Link href={`/product/${product.slug}`} className="block">
                <div className="relative aspect-[4/3] overflow-hidden bg-[var(--color-surface-alt)]">
                    {primaryUrl ? (
                        <img src={primaryUrl} alt={product.name} loading="lazy" className="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                    ) : (
                        <div className="flex h-full items-center justify-center text-[var(--color-text-muted)]"><ShoppingBag className="size-12" /></div>
                    )}
                    {discount > 0 ? (
                        <Badge variant="amber" className="absolute left-3 top-3">-{discount}%</Badge>
                    ) : null}
                </div>
            </Link>
            <div className="space-y-3 p-4">
                <Link href={`/product/${product.slug}`} className="line-clamp-2 text-sm font-semibold text-[var(--color-text-primary)] hover:text-[var(--color-primary)]">
                    {product.name}
                </Link>
                <div className="flex items-end gap-2">
                    <p className="text-lg font-bold text-[var(--color-primary)]">{formatCurrency(product.price)}</p>
                    {product.compare_price && product.compare_price > product.price ? (
                        <p className="pb-0.5 text-sm text-[var(--color-text-muted)] line-through">{formatCurrency(product.compare_price)}</p>
                    ) : null}
                </div>
                <Button fullWidth disabled={isOutOfStock} onClick={() => { addItem({ product, quantity: 1 }); addToast({ type: 'success', title: 'Added to cart', message: `${product.name} added.` }); }}>
                    {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                </Button>
            </div>
        </Card>
    );
}

function FlashSalesContent({ activeSale, upcomingSales, featuredProducts }: FlashSalesPageProps): React.JSX.Element {
    return (
        <>
            <Head title="Flash Sale | ShoppyDrive" />

            <div className="space-y-16 py-8 lg:py-12">
                {activeSale ? (
                    <section className="bg-[var(--color-secondary)] py-12 text-white lg:py-16">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="space-y-6">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                    <div className="space-y-3">
                                        <Badge className="w-fit bg-[var(--color-accent)] text-[var(--color-secondary)]">
                                            <Clock3 className="size-3.5" />
                                            Live flash sale
                                        </Badge>
                                        <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                                            {activeSale.name}
                                        </h1>
                                        <p className="max-w-xl text-sm leading-7 text-white/75 sm:text-base">
                                            Limited-time prices on top-rated products. Grab yours before they sell out.
                                        </p>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs uppercase tracking-[0.2em] text-white/60">Ends in</p>
                                        <CountdownTimer endsAt={activeSale.ends_at} />
                                    </div>
                                </div>

                                {activeSale.items.length > 0 ? (
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        {activeSale.items.map((item) => (
                                            <FlashProductCard key={item.id} item={item} />
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={Zap}
                                        title="No items in this sale"
                                        description="Flash sale items will appear here once added."
                                    />
                                )}
                            </div>
                        </div>
                    </section>
                ) : (
                    <section className="bg-[var(--color-secondary)] py-16 text-white lg:py-24">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="flex flex-col items-center gap-6 text-center">
                                <div className="flex size-20 items-center justify-center rounded-full bg-white/10">
                                    <Zap className="size-10 text-[var(--color-accent)]" />
                                </div>
                                <div className="space-y-2">
                                    <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                                        No active flash sale right now
                                    </h1>
                                    <p className="max-w-xl text-sm leading-7 text-white/75 sm:text-base">
                                        Stay tuned — our next flash sale will be announced soon. Browse our full catalog in the meantime.
                                    </p>
                                </div>
                                <Button asChild className="bg-white text-[var(--color-secondary)] hover:bg-white/90">
                                    <Link href="/products">
                                        Browse all products
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </section>
                )}

                {upcomingSales.length > 0 ? (
                    <section className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--color-text-muted)]">
                                Coming up
                            </p>
                            <h2 className="font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-3xl">
                                Upcoming sales
                            </h2>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {upcomingSales.map((sale) => (
                                <Card key={sale.id} className="border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)]">
                                    <CardContent className="p-5">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-xl bg-[var(--color-accent-light)] text-[var(--color-accent)]">
                                                <Zap className="size-5" />
                                            </div>
                                            <div>
                                                <p className="font-semibold text-[var(--color-text-primary)]">{sale.name}</p>
                                                <p className="text-sm text-[var(--color-text-secondary)]">
                                                    Starts {new Date(sale.starts_at).toLocaleDateString('en-BD', { month: 'short', day: 'numeric' })}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </section>
                ) : null}

                {featuredProducts.length > 0 ? (
                    <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--color-text-muted)]">
                                    Deals
                                </p>
                                <h2 className="font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-3xl">
                                    Featured deals
                                </h2>
                            </div>
                            <Button asChild variant="ghost" className="w-fit px-0 text-[var(--color-primary)] hover:bg-transparent">
                                <Link href="/products?sort=popular">
                                    View all products
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {featuredProducts.map((product) => (
                                <FeaturedProductCard key={product.id} product={product} />
                            ))}
                        </div>
                    </section>
                ) : null}
            </div>
        </>
    );
}

export default function FlashSales(props: FlashSalesPageProps): React.JSX.Element {
    return (
        <FlashSalesContent {...props} />
    );
}
