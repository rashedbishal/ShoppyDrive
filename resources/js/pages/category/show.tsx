import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BadgePercent, Star } from 'lucide-react';
import { route } from 'ziggy-js';
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

interface Category {
    id: number;
    name: string;
    slug?: string;
}

interface Product {
    id: number;
    name: string;
    price: number;
    slug: string;
}

interface CategoryShowProps {
    category: Category;
    products: {
        data: Product[];
    };
}

export default function CategoryShow({
    category,
    products,
}: CategoryShowProps) {
    const formatCurrency = new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 0,
    });

    return (
        <AppLayout>
            <Head title={category.name} />

            <section className="mb-8 grid gap-4 rounded-[2rem] border border-slate-200/80 bg-white/85 p-6 shadow-sm backdrop-blur lg:grid-cols-[1.2fr_0.8fr] lg:p-8">
                <div className="space-y-4">
                    <Badge variant="secondary" className="px-3 py-1">
                        Category view
                    </Badge>
                    <div className="space-y-2">
                        <h1 className="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                            {category.name}
                        </h1>
                        <p className="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Browse products in a clean grid layout with quick
                            access to pricing and the product detail page.
                        </p>
                    </div>
                </div>

                <Card className="border-slate-200/80 bg-slate-950 text-white shadow-none">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg text-white">
                            <BadgePercent className="size-4 text-emerald-400" />
                            Storefront-ready layout
                        </CardTitle>
                        <CardDescription className="text-slate-300">
                            Intended for filter chips, results counts, and
                            pagination.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </section>

            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                {products.data.map((product) => (
                    <Card
                        key={product.id}
                        className="group overflow-hidden border-slate-200/80 bg-white/90 shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl"
                    >
                        <div className="aspect-[4/3] bg-[radial-gradient(circle_at_top_right,_rgba(59,130,246,0.35),_transparent_35%),linear-gradient(135deg,_#0f172a,_#1e293b)] p-5 text-white">
                            <div className="flex h-full flex-col justify-between">
                                <Badge className="w-fit bg-white/10 text-white">
                                    Featured product
                                </Badge>
                                <div className="space-y-1">
                                    <p className="text-sm text-slate-300">
                                        Clean presentation card
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        {formatCurrency.format(product.price)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <CardHeader className="space-y-2">
                            <CardTitle className="text-lg">
                                {product.name}
                            </CardTitle>
                            <CardDescription>
                                Ready for imagery, variants, reviews, and
                                availability indicators.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="flex items-center justify-between pb-6">
                            <div className="flex items-center gap-2 text-sm text-slate-500">
                                <Star className="size-4 text-amber-500" />
                                Product detail
                            </div>
                            <Button asChild size="sm">
                                <Link
                                    href={route('product.show', product.slug)}
                                >
                                    View
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
