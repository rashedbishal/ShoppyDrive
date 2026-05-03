import { Head } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, Heart, ShieldCheck, Star, Truck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '../layouts/AppLayout';

interface Product {
    id?: number;
    name: string;
    description: string;
    price: number;
    compare_price?: number | null;
    short_description?: string | null;
}

interface ProductShowProps {
    product: Product;
}

export default function ProductShow({ product }: ProductShowProps) {
    const formatCurrency = new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 0,
    });
    const hasDiscount =
        product.compare_price !== undefined &&
        product.compare_price !== null &&
        product.compare_price > product.price;
    const comparePrice = product.compare_price ?? product.price;
    const discountAmount = Math.max(comparePrice - product.price, 0);

    return (
        <AppLayout>
            <Head title={product.name} />

            <div className="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
                <div className="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/90 shadow-lg">
                    <div className="aspect-square bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.35),_transparent_40%),linear-gradient(135deg,_#0f172a,_#1e293b)] p-6 text-white">
                        <div className="flex h-full flex-col justify-between">
                            <Badge className="w-fit bg-white/10 text-white">
                                Product gallery
                            </Badge>
                            <div className="grid grid-cols-3 gap-3">
                                <div className="aspect-square rounded-2xl border border-white/10 bg-white/10" />
                                <div className="aspect-square rounded-2xl border border-white/10 bg-white/15" />
                                <div className="aspect-square rounded-2xl border border-white/10 bg-white/10" />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="space-y-6">
                    <Card className="border-slate-200/80 bg-white/90 shadow-sm">
                        <CardHeader className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary">New arrival</Badge>
                                {hasDiscount ? (
                                    <Badge className="bg-emerald-600 text-white">
                                        Save {formatCurrency.format(discountAmount)}
                                    </Badge>
                                ) : null}
                            </div>
                            <CardTitle className="text-3xl tracking-tight">
                                {product.name}
                            </CardTitle>
                            <CardDescription className="text-base leading-7">
                                {product.short_description ??
                                    'A refined product presentation with room for variants, gallery media, stock indicators, and trust signals.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="flex items-end gap-4">
                                <p className="text-3xl font-semibold text-slate-950">
                                    {formatCurrency.format(product.price)}
                                </p>
                                {hasDiscount ? (
                                    <p className="pb-1 text-base text-slate-500 line-through">
                                        {formatCurrency.format(comparePrice)}
                                    </p>
                                ) : null}
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                {[
                                    {
                                        icon: ShieldCheck,
                                        title: 'Secure purchase',
                                        description: 'Checkout and payment are designed for safe handoff.',
                                    },
                                    {
                                        icon: Truck,
                                        title: 'Delivery-ready',
                                        description: 'Supports shipping cost and address selection.',
                                    },
                                    {
                                        icon: BadgeCheck,
                                        title: 'Inventory-aware',
                                        description: 'Prepared for stock status and reservation flow.',
                                    },
                                    {
                                        icon: Star,
                                        title: 'Review-friendly',
                                        description: 'Product page can expand into ratings and Q&A.',
                                    },
                                ].map((item) => {
                                    const Icon = item.icon;

                                    return (
                                        <div
                                            key={item.title}
                                            className="flex items-start gap-3 rounded-2xl border border-slate-200/80 bg-slate-50 p-4"
                                        >
                                            <Icon className="mt-0.5 size-5 text-slate-700" />
                                            <div className="space-y-1">
                                                <p className="font-medium text-slate-950">
                                                    {item.title}
                                                </p>
                                                <p className="text-sm leading-6 text-slate-600">
                                                    {item.description}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <p className="text-sm leading-7 text-slate-600">
                                {product.description}
                            </p>

                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Button className="sm:flex-1">
                                    Add to cart
                                </Button>
                                <Button variant="outline" className="sm:flex-1">
                                    <Heart className="size-4" />
                                    Wishlist
                                </Button>
                                <Button variant="secondary" className="sm:flex-1">
                                    Buy now
                                    <ArrowRight className="size-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
