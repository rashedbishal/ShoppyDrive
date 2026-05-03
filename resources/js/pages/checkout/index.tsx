import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ArrowRight, BadgeCheck, CreditCard, MapPin, ShieldCheck, Truck } from 'lucide-react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface CartItem {
    id: number;
    quantity: number;
    price: number;
    product: {
        name: string;
    };
}

interface CheckoutCart {
    items?: CartItem[];
}

interface CheckoutPageProps {
    cart: CheckoutCart | null;
}

export default function CheckoutIndex({ cart }: CheckoutPageProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        address_line1: '',
        city: '',
        state: '',
        postal_code: '',
        payment_method: 'cod',
    });

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

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('checkout.store'));
    };

    return (
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
            <Head title="Checkout" />

            <div className="mb-8 space-y-2">
                <p className="text-sm font-medium uppercase tracking-[0.22em] text-slate-500">
                    Secure checkout
                </p>
                <h1 className="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                    Review and place your order
                </h1>
                <p className="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                    The checkout shell is structured for customer details,
                    shipping address, and payment method selection.
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <form onSubmit={submit} className="space-y-6">
                    <Card className="border-slate-200/80 bg-white/90 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BadgeCheck className="size-5 text-emerald-600" />
                                Customer details
                            </CardTitle>
                            <CardDescription>
                                Pre-fill these fields from the authenticated
                                user when available.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <Field
                                label="Full name"
                                value={data.name}
                                error={errors.name}
                                onChange={(value) => setData('name', value)}
                                placeholder="Full name"
                                required
                            />
                            <Field
                                label="Email"
                                type="email"
                                value={data.email}
                                error={errors.email}
                                onChange={(value) => setData('email', value)}
                                placeholder="email@example.com"
                                required
                            />
                            <Field
                                label="Phone"
                                value={data.phone}
                                error={errors.phone}
                                onChange={(value) => setData('phone', value)}
                                placeholder="01XXXXXXXXX"
                                required
                            />
                            <div className="hidden md:block" />
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/80 bg-white/90 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <MapPin className="size-5 text-sky-600" />
                                Shipping address
                            </CardTitle>
                            <CardDescription>
                                Capture the address that will be used for
                                delivery and order records.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <Field
                                label="Address line 1"
                                value={data.address_line1}
                                error={errors.address_line1}
                                onChange={(value) =>
                                    setData('address_line1', value)
                                }
                                placeholder="House, street, area"
                                required
                            />
                            <div className="grid gap-4 md:grid-cols-3">
                                <Field
                                    label="City"
                                    value={data.city}
                                    error={errors.city}
                                    onChange={(value) => setData('city', value)}
                                    placeholder="City"
                                    required
                                />
                                <Field
                                    label="State"
                                    value={data.state}
                                    error={errors.state}
                                    onChange={(value) => setData('state', value)}
                                    placeholder="State"
                                    required
                                />
                                <Field
                                    label="Postal code"
                                    value={data.postal_code}
                                    error={errors.postal_code}
                                    onChange={(value) =>
                                        setData('postal_code', value)
                                    }
                                    placeholder="1230"
                                    required
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/80 bg-white/90 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="size-5 text-violet-600" />
                                Payment method
                            </CardTitle>
                            <CardDescription>
                                Choose a payment path that matches the order
                                flow.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <label className="flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-[var(--color-primary)] bg-[var(--color-primary-light)] p-4">
                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="cod"
                                    checked={true}
                                    readOnly
                                />
                                <span className="space-y-0.5">
                                    <span className="block font-semibold text-slate-900">Cash on Delivery</span>
                                    <span className="block text-sm text-slate-500">Pay with cash when your order arrives.</span>
                                </span>
                            </label>
                            {errors.payment_method ? (
                                <p className="text-sm text-red-600">{errors.payment_method}</p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Button type="submit" size="lg" disabled={processing}>
                        {processing ? 'Processing...' : 'Place order'}
                        <ArrowRight className="size-4" />
                    </Button>
                </form>

                <div className="space-y-4">
                    <Card className="border-slate-200/80 bg-slate-950 text-white shadow-xl shadow-slate-950/10">
                        <CardHeader>
                            <CardTitle className="text-white">
                                Order summary
                            </CardTitle>
                            <CardDescription className="text-slate-300">
                                Review the current cart contents before
                                submitting the order.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {items.length > 0 ? (
                                <div className="space-y-3">
                                    {items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/5 p-4"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {item.product.name}
                                                </p>
                                                <p className="text-sm text-slate-300">
                                                    Qty {item.quantity}
                                                </p>
                                            </div>
                                            <p className="font-medium">
                                                {formatCurrency.format(
                                                    item.price * item.quantity,
                                                )}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                                    Your cart is empty. Add products before
                                    checking out.
                                </div>
                            )}

                            <div className="space-y-3 text-sm text-slate-300">
                                <div className="flex items-center justify-between">
                                    <span>Subtotal</span>
                                    <span>{formatCurrency.format(subtotal)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span>Shipping</span>
                                    <span>Calculated later</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span>Discount</span>
                                    <span>Coupon-ready</span>
                                </div>
                            </div>

                            <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p className="text-sm text-slate-300">
                                    Estimated total
                                </p>
                                <p className="mt-1 text-3xl font-semibold text-white">
                                    {formatCurrency.format(subtotal)}
                                </p>
                            </div>

                            <div className="flex items-center gap-2 text-sm text-slate-300">
                                <ShieldCheck className="size-4 text-emerald-400" />
                                Secure, mobile-first, and ready for payment
                                verification.
                            </div>
                            <div className="flex items-center gap-2 text-sm text-slate-300">
                                <Truck className="size-4 text-sky-400" />
                                Shipping and address flow are laid out for
                                expansion.
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}

function Field({
    label,
    value,
    onChange,
    error,
    placeholder,
    type = 'text',
    required = false,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    type?: string;
    required?: boolean;
}) {
    return (
        <label className="grid gap-2">
            <span className="text-sm font-medium text-slate-700">{label}</span>
            <input
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                required={required}
                className="h-11 rounded-xl border border-slate-200/80 bg-white px-4 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
            />
            {error ? <span className="text-sm text-red-600">{error}</span> : null}
        </label>
    );
}
