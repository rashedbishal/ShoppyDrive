import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ShoppingBag, Sparkles, Truck } from 'lucide-react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface CartItemProduct {
    name: string;
}

interface CartItem {
    id: number;
    quantity: number;
    price: number;
    product: CartItemProduct;
}

interface Cart {
    items?: CartItem[];
}

interface CartIndexProps {
    cart: Cart | null;
}

export default function CartIndex({ cart }: CartIndexProps) {
    const items = cart?.items ?? [];
    const formatCurrency = new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 0,
    });
    const subtotal = items.reduce(
        (total, item) => total + Number(item.price) * item.quantity,
        0,
    );

    return (
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
            <Head title="Shopping Cart" />

            <div className="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="space-y-2">
                    <p className="text-sm font-medium uppercase tracking-[0.22em] text-slate-500">
                        Cart review
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                        Shopping cart
                    </h1>
                    <p className="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Review line items, quantities, and checkout readiness
                        before moving to shipping and payment.
                    </p>
                </div>

                <Card className="border-slate-200/80 bg-white/90 shadow-sm">
                    <CardContent className="flex items-center gap-3 p-4">
                        <ShoppingBag className="size-5 text-slate-700" />
                        <div>
                            <p className="text-sm text-slate-500">Items</p>
                            <p className="text-lg font-semibold text-slate-950">
                                {items.length}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {items.length > 0 ? (
                <div className="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
                    <div className="space-y-4">
                        {items.map((item) => (
                            <Card
                                key={item.id}
                                className="border-slate-200/80 bg-white/90 shadow-sm"
                            >
                                <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.35),_transparent_40%),linear-gradient(135deg,_#0f172a,_#334155)] text-white">
                                            <Sparkles className="size-5" />
                                        </div>
                                        <div className="space-y-1">
                                            <p className="font-medium text-slate-950">
                                                {item.product.name}
                                            </p>
                                            <p className="text-sm text-slate-500">
                                                Quantity {item.quantity}
                                            </p>
                                        </div>
                                    </div>

                                    <p className="text-lg font-semibold text-slate-950">
                                        {formatCurrency.format(
                                            item.price * item.quantity,
                                        )}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <Card className="border-slate-200/80 bg-slate-950 text-white shadow-xl shadow-slate-950/10">
                        <CardHeader>
                            <CardTitle className="text-white">
                                Order summary
                            </CardTitle>
                            <CardDescription className="text-slate-300">
                                Review the totals before checkout.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center justify-between text-slate-300">
                                    <span>Subtotal</span>
                                    <span>{formatCurrency.format(subtotal)}</span>
                                </div>
                                <div className="flex items-center justify-between text-slate-300">
                                    <span>Shipping</span>
                                    <span>Calculated at checkout</span>
                                </div>
                                <div className="flex items-center justify-between text-slate-300">
                                    <span>Discount</span>
                                    <span>Applied with coupon</span>
                                </div>
                            </div>

                            <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p className="text-sm text-slate-300">
                                    Estimated total
                                </p>
                                <p className="mt-1 text-3xl font-semibold">
                                    {formatCurrency.format(subtotal)}
                                </p>
                            </div>

                            <Button
                                asChild
                                size="lg"
                                className="w-full bg-white text-slate-950 hover:bg-slate-100"
                            >
                                <Link href={route('checkout.index')}>
                                    Proceed to checkout
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            ) : (
                <Card className="border-slate-200/80 bg-white/90 shadow-sm">
                    <CardContent className="grid gap-6 p-8 md:grid-cols-[0.8fr_1.2fr] md:items-center">
                        <div className="flex h-48 items-center justify-center rounded-[2rem] bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.28),_transparent_40%),linear-gradient(135deg,_#0f172a,_#1e293b)] text-white">
                            <ShoppingBag className="size-10" />
                        </div>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <h2 className="text-2xl font-semibold tracking-tight text-slate-950">
                                    Your cart is empty
                                </h2>
                                <p className="max-w-xl text-sm leading-6 text-slate-600">
                                    Add products to begin checkout. The cart
                                    shell is ready for coupons, totals, and
                                    secure handoff.
                                </p>
                            </div>
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Button asChild>
                                    <Link href={route('home')}>Continue shopping</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={route('login')}>Log in</Link>
                                </Button>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-slate-500">
                                <Truck className="size-4" />
                                Guest carts and account carts share the same
                                checkout-ready design.
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
