import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Clock3, Zap } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PageHero } from '@/components/shared/PageHero';
import { ProductCard } from '@/components/shared/ProductCard';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { PRODUCT_GRID_CLASS } from '@/lib/constants';
import { getSoldPercent } from '@/lib/utils';
import type { Product } from '@/types';

type FlashSaleItem = {
    id: number;
    sale_price: number;
    sold_count: number;
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
        return (
            <span className="font-mono text-base font-semibold text-[var(--color-accent)]">
                Sale ended
            </span>
        );
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
                    className="flex min-w-[3.5rem] flex-col items-center rounded-xl bg-[var(--color-secondary)] px-2.5 py-1.5 text-center shadow-sm"
                >
                    <span className="text-xl font-bold text-white">
                        {String(seg.value).padStart(2, '0')}
                    </span>
                    <span className="text-[10px] tracking-[0.2em] text-white/70 uppercase">
                        {seg.label}
                    </span>
                </div>
            ))}
        </div>
    );
}

function FlashSalesContent({
    activeSale,
    upcomingSales,
    featuredProducts,
}: FlashSalesPageProps): React.JSX.Element {
    return (
        <>
            <Head title="Flash Sale | ShoppyDrive" />

            <div className="space-y-10 py-6 lg:py-8">
                {activeSale ? (
                    <>
                        <PageHero
                            badge={{ icon: Clock3, label: 'Live flash sale' }}
                            eyebrow="Flash sale"
                            title={activeSale.name}
                            description="Limited-time prices on top-rated products. Grab yours before they sell out."
                            chips={[
                                {
                                    icon: Zap,
                                    label: `${activeSale.items.length} ${activeSale.items.length === 1 ? 'deal' : 'deals'} live`,
                                    iconClassName: 'text-[var(--color-accent)]',
                                },
                            ]}
                            aside={
                                <div className="space-y-2 lg:justify-self-end">
                                    <p className="text-xs font-semibold tracking-[0.2em] text-[var(--color-text-muted)] uppercase">
                                        Ends in
                                    </p>
                                    <CountdownTimer
                                        endsAt={activeSale.ends_at}
                                    />
                                </div>
                            }
                        />

                        <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            {activeSale.items.length > 0 ? (
                                <div className={PRODUCT_GRID_CLASS}>
                                    {activeSale.items.map((item, index) => (
                                        <ProductCard
                                            key={item.id}
                                            index={index}
                                            product={{
                                                ...item.product,
                                                in_flash_sale: true,
                                                flash_sale_price:
                                                    item.sale_price,
                                            }}
                                            saleProgress={getSoldPercent(
                                                item.sold_count,
                                                item.product.available_stock ??
                                                    item.product.stock_quantity,
                                            )}
                                            saleProgressLabel={`${item.sold_count} sold`}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <EmptyState
                                    icon={Zap}
                                    title="No items in this sale"
                                    description="Flash sale items will appear here once added."
                                />
                            )}
                        </section>
                    </>
                ) : (
                    <PageHero
                        badge={{ icon: Zap, label: 'Flash sales' }}
                        eyebrow="Flash sale"
                        title="No active flash sale right now"
                        description="Stay tuned — our next flash sale will be announced soon. Browse our full catalog in the meantime."
                    >
                        <Button asChild className="h-10">
                            <Link href="/products">
                                Browse all products
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </PageHero>
                )}

                {upcomingSales.length > 0 ? (
                    <section className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold tracking-[0.24em] text-[var(--color-text-muted)] uppercase">
                                Coming up
                            </p>
                            <h2 className="font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-3xl">
                                Upcoming sales
                            </h2>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {upcomingSales.map((sale) => (
                                <Card
                                    key={sale.id}
                                    className="border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)]"
                                >
                                    <CardContent className="p-5">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-xl bg-[var(--color-accent-light)] text-[var(--color-accent)]">
                                                <Zap className="size-5" />
                                            </div>
                                            <div>
                                                <p className="font-semibold text-[var(--color-text-primary)]">
                                                    {sale.name}
                                                </p>
                                                <p className="text-sm text-[var(--color-text-secondary)]">
                                                    Starts{' '}
                                                    {new Date(
                                                        sale.starts_at,
                                                    ).toLocaleDateString(
                                                        'en-BD',
                                                        {
                                                            month: 'short',
                                                            day: 'numeric',
                                                        },
                                                    )}
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
                                <p className="text-xs font-semibold tracking-[0.24em] text-[var(--color-text-muted)] uppercase">
                                    Deals
                                </p>
                                <h2 className="font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-3xl">
                                    Featured deals
                                </h2>
                            </div>
                            <Button
                                asChild
                                variant="ghost"
                                className="w-fit px-0 text-[var(--color-primary)] hover:bg-transparent"
                            >
                                <Link href="/products?sort=popular">
                                    View all products
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </div>
                        <div className={PRODUCT_GRID_CLASS}>
                            {featuredProducts.map((product, index) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    index={index}
                                />
                            ))}
                        </div>
                    </section>
                ) : null}
            </div>
        </>
    );
}

export default function FlashSales(
    props: FlashSalesPageProps,
): React.JSX.Element {
    return <FlashSalesContent {...props} />;
}
