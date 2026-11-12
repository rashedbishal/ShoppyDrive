import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle,
    ChevronDown,
    ChevronUp,
    Mail,
    MapPin,
    Phone,
    Play,
    ShoppingBag,
    Star,
    Truck,
} from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import HoneypotField from '@/components/honeypot-field';
import SocialChatWidget from '@/components/layout/SocialChatWidget';
import { Select } from '@/components/ui/select';
import { formatCurrency } from '@/lib/utils';
import type { SharedPageProps } from '@/types';

type ProductMedia = {
    id: number;
    path: string;
    type: 'image' | 'video';
    is_primary: boolean;
};
type Sections = Record<string, any>;
type PendingPayment = {
    token: string;
    amount: number;
    order_number: string;
    provider: 'bkash' | 'nagad';
};

type Variant = {
    id: number;
    sku: string | null;
    price: number;
    compare_price: number | null;
    stock_quantity: number;
    attributes: Record<string, string> | null;
    image: string | null;
};

/**
 * "Color: Red · Set: 12pcs" from a variant's attributes — any attributes, not
 * only colour and size, each named so "12pcs" is never shown without saying
 * what it is. Falls back to the SKU.
 */
function variantName(variant: Variant): string {
    const parts = Object.entries(variant.attributes ?? {}).map(
        ([name, value]) => `${name}: ${value}`,
    );

    return parts.length > 0 ? parts.join(' · ') : (variant.sku ?? 'Option');
}

type Props = {
    page: {
        id: number;
        title: string;
        slug: string;
        sections: Sections;
        max_order_quantity: number;
    };
    product: {
        id: number;
        name: string;
        description: string | null;
        short_description: string | null;
        price: number;
        compare_price: number | null;
        in_flash_sale?: boolean;
        flash_sale_price?: number | null;
        free_delivery: boolean;
        free_delivery_min_qty: number | null;
        stock_quantity?: number | null;
        media: ProductMedia[];
        variants: Variant[];
    };
    bkashNumber: string | null;
    nagadNumber: string | null;
    siteName: string;
    siteLogo: string | null;
    pendingPayment: PendingPayment | null;
    orderToken: string;
};

const BD_DISTRICTS = [
    'Bagerhat',
    'Bandarban',
    'Barguna',
    'Barisal',
    'Bhola',
    'Bogura',
    'Brahmanbaria',
    'Chandpur',
    'Chapainawabganj',
    'Chattogram',
    'Chuadanga',
    "Cox's Bazar",
    'Cumilla',
    'Dhaka',
    'Dinajpur',
    'Faridpur',
    'Feni',
    'Gaibandha',
    'Gazipur',
    'Gopalganj',
    'Habiganj',
    'Jamalpur',
    'Jashore',
    'Jhalokathi',
    'Jhenaidah',
    'Joypurhat',
    'Khagrachhari',
    'Khulna',
    'Kishoreganj',
    'Kurigram',
    'Kushtia',
    'Lakshmipur',
    'Lalmonirhat',
    'Madaripur',
    'Magura',
    'Manikganj',
    'Meherpur',
    'Moulvibazar',
    'Munshiganj',
    'Mymensingh',
    'Naogaon',
    'Narail',
    'Narayanganj',
    'Narsingdi',
    'Natore',
    'Netrokona',
    'Nilphamari',
    'Noakhali',
    'Pabna',
    'Panchagarh',
    'Patuakhali',
    'Pirojpur',
    'Rajbari',
    'Rajshahi',
    'Rangamati',
    'Rangpur',
    'Satkhira',
    'Shariatpur',
    'Sherpur',
    'Sirajganj',
    'Sunamganj',
    'Sylhet',
    'Tangail',
    'Thakurgaon',
];

function calcShipping(
    state: string,
    city: string,
    freeDelivery: boolean,
    insideDhakaRate: number,
    outsideDhakaRate: number,
): number {
    if (freeDelivery) {
        return 0;
    }

    if (state === 'Dhaka' && city === 'Inside Dhaka City Corporation') {
        return insideDhakaRate;
    }

    return outsideDhakaRate;
}

function StarRating({ rating }: { rating: number }) {
    return (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4, 5].map((s) => (
                <Star
                    key={s}
                    className={`size-4 ${s <= rating ? 'fill-amber-400 text-amber-400' : 'text-slate-200'}`}
                />
            ))}
        </div>
    );
}

function InlinePayment({
    pending,
    slug,
    bkashNumber,
    nagadNumber,
}: {
    pending: PendingPayment;
    slug: string;
    bkashNumber: string | null;
    nagadNumber: string | null;
}) {
    const { errors } = usePage<any>().props as {
        errors: Record<string, string>;
    };
    const providerNumber =
        pending.provider === 'bkash' ? bkashNumber : nagadNumber;
    const providerLabel = pending.provider === 'bkash' ? 'Bkash' : 'Nagad';
    const providerColor = pending.provider === 'bkash' ? '#e2136e' : '#f4821f';

    const { data, setData, post, processing } = useForm({ transaction_id: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('lp.payment.submit', { slug, token: pending.token }));
    };

    return (
        <div className="space-y-5 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-slate-200">
            <div className="flex items-center gap-3">
                <button
                    type="button"
                    onClick={() => router.get(route('lp.show', slug))}
                    className="flex items-center gap-1.5 text-xs text-slate-500 transition-colors hover:text-slate-800"
                >
                    <ArrowLeft className="size-3.5" /> Back to order form
                </button>
            </div>

            <div className="text-center">
                <div
                    className="mb-3 inline-flex size-14 items-center justify-center rounded-full"
                    style={{ backgroundColor: providerColor + '20' }}
                >
                    <span
                        className="text-xl font-extrabold"
                        style={{ color: providerColor }}
                    >
                        {providerLabel[0]}
                    </span>
                </div>
                <h2 className="text-lg font-bold text-slate-900">
                    Complete Payment via {providerLabel}
                </h2>
                <p className="mt-0.5 text-xs text-slate-400">
                    Order #{pending.order_number}
                </p>
            </div>

            <div
                className="space-y-1 rounded-xl p-4 text-center"
                style={{
                    backgroundColor: providerColor + '10',
                    border: `1px solid ${providerColor}30`,
                }}
            >
                <p className="text-xs text-slate-500">Send exactly</p>
                <p className="text-3xl font-extrabold text-slate-900">
                    {formatCurrency(pending.amount)}
                </p>
                <p className="mt-1 text-xs text-slate-500">
                    to this {providerLabel} number
                </p>
                <p
                    className="text-xl font-bold"
                    style={{ color: providerColor }}
                >
                    {providerNumber}
                </p>
                <p className="text-[11px] text-slate-400">
                    Send Money → Personal
                </p>
            </div>

            <ol className="space-y-1.5 text-sm text-slate-600">
                <li className="flex gap-2">
                    <span className="w-4 shrink-0 font-bold text-slate-800">
                        1.
                    </span>{' '}
                    Open your {providerLabel} app
                </li>
                <li className="flex gap-2">
                    <span className="w-4 shrink-0 font-bold text-slate-800">
                        2.
                    </span>{' '}
                    Go to <strong>Send Money</strong> and enter the number above
                </li>
                <li className="flex gap-2">
                    <span className="w-4 shrink-0 font-bold text-slate-800">
                        3.
                    </span>{' '}
                    Send exactly{' '}
                    <strong>{formatCurrency(pending.amount)}</strong>
                </li>
                <li className="flex gap-2">
                    <span className="w-4 shrink-0 font-bold text-slate-800">
                        4.
                    </span>{' '}
                    Copy the <strong>Transaction ID</strong> from the
                    confirmation SMS
                </li>
                <li className="flex gap-2">
                    <span className="w-4 shrink-0 font-bold text-slate-800">
                        5.
                    </span>{' '}
                    Paste it below and click Confirm
                </li>
            </ol>

            <form onSubmit={handleSubmit} className="space-y-3">
                {errors.ip && (
                    <p className="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">
                        {errors.ip}
                    </p>
                )}
                <div>
                    <label
                        htmlFor="lp-transaction-id"
                        className="mb-1 block text-sm font-medium text-slate-700"
                    >
                        Transaction ID
                    </label>
                    <input
                        id="lp-transaction-id"
                        className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 font-mono text-sm tracking-wider uppercase focus:border-[#c8102e] focus:ring-2 focus:ring-[#c8102e]/30 focus:outline-none"
                        value={data.transaction_id}
                        onChange={(e) =>
                            setData('transaction_id', e.target.value)
                        }
                        placeholder="Transaction ID from SMS…"
                        autoFocus
                    />
                    {errors.transaction_id && (
                        <p className="mt-1 text-xs font-medium text-red-600">
                            {errors.transaction_id}
                        </p>
                    )}
                </div>
                <button
                    type="submit"
                    disabled={processing || !data.transaction_id}
                    className="w-full rounded-xl py-3 text-sm font-bold text-white transition-colors disabled:opacity-60"
                    style={{ backgroundColor: providerColor }}
                >
                    {processing ? 'Verifying…' : 'Confirm Payment'}
                </button>
            </form>
        </div>
    );
}

/** A visible label tied to its input, so the field is still named once the placeholder is gone. */
function FieldLabel({
    htmlFor,
    required = false,
    children,
}: {
    htmlFor: string;
    required?: boolean;
    children: React.ReactNode;
}) {
    return (
        <label
            htmlFor={htmlFor}
            className="mb-1 block text-sm font-medium text-slate-700"
        >
            {children}
            {required && <span className="ml-1 text-red-500">*</span>}
        </label>
    );
}

function OrderForm({
    product,
    slug,
    maxQuantity,
    focusedVariantId,
    onFocusVariant,
    bkashNumber,
    nagadNumber,
    orderToken,
}: {
    product: Props['product'];
    slug: string;
    maxQuantity: number;
    focusedVariantId: number | null;
    onFocusVariant: (id: number | null) => void;
    bkashNumber: string | null;
    nagadNumber: string | null;
    orderToken: string;
}) {
    const { settings, mobile_banking_available: mobileBankingAvailable } =
        usePage<SharedPageProps>().props;
    const fieldId = useId();
    const insideDhakaRate = settings.shipping_rate_inside_dhaka ?? 60;
    const outsideDhakaRate = settings.shipping_rate_outside_dhaka ?? 120;

    const hasVariants = product.variants.length > 0;
    // Plain product: one quantity. Variant product: a quantity per variant.
    const [plainQty, setPlainQty] = useState(1);
    const [variantQty, setVariantQty] = useState<Record<number, number>>({});
    const chosenVariants = product.variants.filter(
        (v) => (variantQty[v.id] ?? 0) > 0,
    );
    const qty = hasVariants
        ? chosenVariants.reduce((sum, v) => sum + (variantQty[v.id] ?? 0), 0)
        : plainQty;
    const [state, setState] = useState('');
    const [city, setCity] = useState('');

    const shipping = calcShipping(
        state,
        city,
        product.free_delivery,
        insideDhakaRate,
        outsideDhakaRate,
    );
    const focusedVariant =
        product.variants.find((v) => v.id === focusedVariantId) ?? null;
    const lowestVariantPrice = hasVariants
        ? Math.min(...product.variants.map((v) => v.price))
        : product.price;
    const subtotal = hasVariants
        ? chosenVariants.reduce(
              (sum, v) => sum + v.price * (variantQty[v.id] ?? 0),
              0,
          )
        : product.price * qty;
    const total = subtotal + shipping;

    const [variantError, setVariantError] = useState<string | null>(null);

    const { data, setData, post, processing, errors, transform } = useForm({
        name: '',
        phone: '',
        email: '',
        address_line1: '',
        // No "Area / Landmark" field, same as the regular checkout: sent
        // empty so the request keeps the shape the server validates.
        address_line2: '',
        city: '',
        state: '',
        postal_code: '',
        quantity: 1,
        payment_method: 'cod' as 'cod' | 'mobile_banking',
        provider: '' as '' | 'bkash' | 'nagad',
        order_token: orderToken,
        // BIZ-011: honeypot, always empty for real customers.
        sd_confirm_ref: '',
    });
    // The server reports a bad variant list under `items` (not a form field here).
    const itemsError = (errors as Record<string, string | undefined>).items;

    // OPS-005: see Checkout.tsx's identical guard — the server enforces this
    // too (LandingPageController::placeOrder()), this is purely UX.
    useEffect(() => {
        if (
            !mobileBankingAvailable &&
            data.payment_method === 'mobile_banking'
        ) {
            setData('payment_method', 'cod');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mobileBankingAvailable]);

    const updateState = (v: string) => {
        setState(v);
        setData('state', v);
    };
    const updateCity = (v: string) => {
        setCity(v);
        setData('city', v);
    };
    const plainStock = product.stock_quantity ?? maxQuantity;
    const updateQty = (v: number) => {
        const q = Math.min(
            Math.max(1, v),
            maxQuantity,
            Math.max(1, plainStock),
        );
        setPlainQty(q);
        setData('quantity', q);
    };

    // One variant's quantity: never below 0, above its stock, or above what is
    // left of the page's per-order limit shared by all variants.
    const updateVariantQty = (variant: Variant, next: number) => {
        const others = qty - (variantQty[variant.id] ?? 0);
        const ceiling = Math.min(
            variant.stock_quantity,
            Math.max(0, maxQuantity - others),
        );
        setVariantQty((current) => ({
            ...current,
            [variant.id]: Math.min(Math.max(0, next), ceiling),
        }));
        onFocusVariant(variant.id);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (hasVariants && qty === 0) {
            setVariantError('Choose at least one option and a quantity.');

            return;
        }

        setVariantError(null);
        transform((formData) => {
            const { quantity, ...rest } = formData;

            return hasVariants
                ? {
                      ...rest,
                      items: chosenVariants.map((v) => ({
                          variant_id: v.id,
                          quantity: variantQty[v.id],
                      })),
                  }
                : { ...rest, quantity };
        });
        post(route('lp.order', slug));
    };

    const inputClass =
        'h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#c8102e]/30 focus:border-[#c8102e]';

    return (
        <div className="space-y-4 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-slate-200">
            <div className="space-y-1">
                <div className="flex items-baseline gap-2">
                    {hasVariants && !focusedVariant && (
                        <span className="text-sm text-slate-500">From</span>
                    )}
                    <span className="text-2xl font-bold text-slate-900">
                        {formatCurrency(
                            focusedVariant?.price ??
                                (hasVariants
                                    ? lowestVariantPrice
                                    : product.price),
                        )}
                    </span>
                    {!hasVariants &&
                        product.compare_price &&
                        product.compare_price > product.price && (
                            <span className="text-sm text-slate-400 line-through">
                                {formatCurrency(product.compare_price)}
                            </span>
                        )}
                    {focusedVariant?.compare_price && (
                        <span className="text-sm text-slate-400 line-through">
                            {formatCurrency(focusedVariant.compare_price)}
                        </span>
                    )}
                </div>
                <p className="text-xs text-slate-500">
                    {product.free_delivery
                        ? '🚚 Free delivery'
                        : `Delivery: ${formatCurrency(insideDhakaRate)} (Dhaka) / ${formatCurrency(outsideDhakaRate)} (Outside)`}
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-3">
                <HoneypotField
                    value={data.sd_confirm_ref}
                    onChange={(value) => setData('sd_confirm_ref', value)}
                />
                {hasVariants ? (
                    <div className="space-y-2">
                        <div className="flex items-baseline justify-between">
                            <span className="text-sm font-medium text-slate-700">
                                Choose your options
                            </span>
                            <span className="text-xs text-slate-500">
                                {qty} of {maxQuantity} max
                            </span>
                        </div>
                        <div className="space-y-2">
                            {product.variants.map((variant) => {
                                const count = variantQty[variant.id] ?? 0;
                                const soldOut = variant.stock_quantity < 1;

                                return (
                                    <div
                                        key={variant.id}
                                        onClick={() =>
                                            onFocusVariant(variant.id)
                                        }
                                        className={`flex cursor-pointer items-center gap-3 rounded-xl border p-2 transition-colors ${
                                            focusedVariantId === variant.id
                                                ? 'border-[#c8102e] bg-red-50/40'
                                                : 'border-slate-200 hover:border-slate-300'
                                        } ${soldOut ? 'opacity-60' : ''}`}
                                    >
                                        {variant.image ? (
                                            <img
                                                src={variant.image}
                                                alt={variantName(variant)}
                                                className="size-12 shrink-0 rounded-lg object-cover"
                                            />
                                        ) : (
                                            <div className="size-12 shrink-0 rounded-lg bg-slate-100" />
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-slate-800">
                                                {variantName(variant)}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {formatCurrency(variant.price)}
                                                {variant.compare_price && (
                                                    <span className="ml-1 line-through">
                                                        {formatCurrency(
                                                            variant.compare_price,
                                                        )}
                                                    </span>
                                                )}
                                                {soldOut ? (
                                                    <span className="ml-2 font-medium text-red-500">
                                                        Out of stock
                                                    </span>
                                                ) : variant.stock_quantity <=
                                                  5 ? (
                                                    <span className="ml-2 font-medium text-amber-600">
                                                        Only{' '}
                                                        {variant.stock_quantity}{' '}
                                                        left
                                                    </span>
                                                ) : null}
                                            </p>
                                        </div>
                                        <div
                                            className="flex items-center gap-1.5"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <button
                                                type="button"
                                                disabled={count === 0}
                                                onClick={() =>
                                                    updateVariantQty(
                                                        variant,
                                                        count - 1,
                                                    )
                                                }
                                                aria-label={`Decrease ${variantName(variant)}`}
                                                className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                            >
                                                −
                                            </button>
                                            <span className="w-6 text-center text-sm font-semibold">
                                                {count}
                                            </span>
                                            <button
                                                type="button"
                                                disabled={
                                                    soldOut ||
                                                    count >=
                                                        variant.stock_quantity ||
                                                    qty >= maxQuantity
                                                }
                                                onClick={() =>
                                                    updateVariantQty(
                                                        variant,
                                                        count + 1,
                                                    )
                                                }
                                                aria-label={`Increase ${variantName(variant)}`}
                                                className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        {(variantError || itemsError || errors.quantity) && (
                            <p className="text-xs text-red-500">
                                {variantError ?? itemsError ?? errors.quantity}
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="flex items-center gap-3">
                        <span className="w-16 text-sm font-medium text-slate-700">
                            Qty
                        </span>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => updateQty(qty - 1)}
                                aria-label="Decrease quantity"
                                className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                            >
                                −
                            </button>
                            <span className="w-8 text-center text-sm font-semibold">
                                {qty}
                            </span>
                            <button
                                type="button"
                                onClick={() => updateQty(qty + 1)}
                                aria-label="Increase quantity"
                                className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                            >
                                +
                            </button>
                        </div>
                        {errors.quantity && (
                            <p className="text-xs text-red-500">
                                {errors.quantity}
                            </p>
                        )}
                    </div>
                )}

                {/* Personal info */}
                <div>
                    <FieldLabel htmlFor={`${fieldId}-name`} required>
                        Full Name
                    </FieldLabel>
                    <input
                        id={`${fieldId}-name`}
                        className={inputClass}
                        placeholder="Your full name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    {errors.name && (
                        <p className="mt-0.5 text-xs text-red-500">
                            {errors.name}
                        </p>
                    )}
                </div>
                <div>
                    <FieldLabel htmlFor={`${fieldId}-phone`} required>
                        Phone Number
                    </FieldLabel>
                    <input
                        id={`${fieldId}-phone`}
                        type="tel"
                        inputMode="tel"
                        maxLength={20}
                        className={inputClass}
                        placeholder="017XXXXXXXX"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                    <p className="mt-0.5 text-xs text-slate-400">
                        Enter your mobile number (e.g. 017XXXXXXXX or
                        +88017XXXXXXXX)
                    </p>
                    {errors.phone && (
                        <p className="mt-0.5 text-xs text-red-500">
                            {errors.phone}
                        </p>
                    )}
                </div>
                <div>
                    <FieldLabel htmlFor={`${fieldId}-email`}>
                        Email{' '}
                        <span className="font-normal text-slate-400">
                            (optional)
                        </span>
                    </FieldLabel>
                    <input
                        id={`${fieldId}-email`}
                        className={inputClass}
                        placeholder="you@example.com"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </div>

                {/* Address */}
                <div>
                    <FieldLabel htmlFor={`${fieldId}-address`} required>
                        Address
                    </FieldLabel>
                    <input
                        id={`${fieldId}-address`}
                        className={inputClass}
                        placeholder="House, road, area"
                        value={data.address_line1}
                        onChange={(e) =>
                            setData('address_line1', e.target.value)
                        }
                    />
                    {errors.address_line1 && (
                        <p className="mt-0.5 text-xs text-red-500">
                            {errors.address_line1}
                        </p>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <div>
                        <Select
                            label="District"
                            required
                            labelClassName="text-slate-700"
                            containerClassName="space-y-0 [&>label]:mb-1 [&>label]:block"
                            selectClassName={inputClass}
                            value={data.state}
                            onChange={(e) => updateState(e.target.value)}
                        >
                            <option value="">Select district</option>
                            {BD_DISTRICTS.map((d) => (
                                <option key={d} value={d}>
                                    {d}
                                </option>
                            ))}
                        </Select>
                        {errors.state && (
                            <p className="mt-0.5 text-xs text-red-500">
                                {errors.state}
                            </p>
                        )}
                    </div>
                    <div>
                        {data.state === 'Dhaka' ? (
                            <Select
                                label="Area"
                                required
                                labelClassName="text-slate-700"
                                containerClassName="space-y-0 [&>label]:mb-1 [&>label]:block"
                                selectClassName={inputClass}
                                value={data.city}
                                onChange={(e) => updateCity(e.target.value)}
                            >
                                <option value="">Select area</option>
                                <option value="Inside Dhaka City Corporation">
                                    Inside Dhaka City
                                </option>
                                <option value="Outside Dhaka City Corporation">
                                    Outside Dhaka City
                                </option>
                            </Select>
                        ) : (
                            <>
                                <FieldLabel
                                    htmlFor={`${fieldId}-city`}
                                    required
                                >
                                    City / Upazila
                                </FieldLabel>
                                <input
                                    id={`${fieldId}-city`}
                                    className={inputClass}
                                    placeholder="City or upazila"
                                    value={data.city}
                                    onChange={(e) => updateCity(e.target.value)}
                                />
                            </>
                        )}
                        {errors.city && (
                            <p className="mt-0.5 text-xs text-red-500">
                                {errors.city}
                            </p>
                        )}
                    </div>
                </div>

                {/* Payment method */}
                <div className="space-y-2">
                    <p className="text-sm font-medium text-slate-700">
                        Payment Method
                    </p>
                    <label className="flex cursor-pointer items-center gap-2">
                        <input
                            type="radio"
                            name="pm"
                            value="cod"
                            checked={data.payment_method === 'cod'}
                            onChange={() => setData('payment_method', 'cod')}
                            className="accent-[#c8102e]"
                        />
                        <span className="text-sm">Cash on Delivery</span>
                    </label>
                    {(bkashNumber || nagadNumber) && (
                        <label
                            className={`flex items-center gap-2 ${mobileBankingAvailable ? 'cursor-pointer' : 'cursor-not-allowed opacity-50'}`}
                        >
                            <input
                                type="radio"
                                name="pm"
                                value="mobile_banking"
                                disabled={!mobileBankingAvailable}
                                checked={
                                    data.payment_method === 'mobile_banking'
                                }
                                onChange={() =>
                                    setData('payment_method', 'mobile_banking')
                                }
                                className="accent-[#c8102e]"
                            />
                            <span className="text-sm">
                                Mobile Banking (Bkash / Nagad)
                                {!mobileBankingAvailable && (
                                    <span className="ml-1 text-slate-500">
                                        — temporarily unavailable
                                    </span>
                                )}
                            </span>
                        </label>
                    )}
                    {data.payment_method === 'mobile_banking' && (
                        <div className="flex gap-2 pl-6">
                            {bkashNumber && (
                                <label className="flex cursor-pointer items-center gap-1">
                                    <input
                                        type="radio"
                                        name="provider"
                                        value="bkash"
                                        checked={data.provider === 'bkash'}
                                        onChange={() =>
                                            setData('provider', 'bkash')
                                        }
                                        className="[--control-color:var(--color-pink-600)]"
                                    />
                                    <span className="text-sm font-semibold text-pink-700">
                                        Bkash
                                    </span>
                                </label>
                            )}
                            {nagadNumber && (
                                <label className="flex cursor-pointer items-center gap-1">
                                    <input
                                        type="radio"
                                        name="provider"
                                        value="nagad"
                                        checked={data.provider === 'nagad'}
                                        onChange={() =>
                                            setData('provider', 'nagad')
                                        }
                                        className="[--control-color:var(--color-orange-600)]"
                                    />
                                    <span className="text-sm font-semibold text-orange-700">
                                        Nagad
                                    </span>
                                </label>
                            )}
                            {errors.provider && (
                                <p className="text-xs text-red-500">
                                    {errors.provider}
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {/* Order summary */}
                {data.state && data.city && (
                    <div className="space-y-1 rounded-xl bg-slate-50 p-3 text-sm">
                        <div className="flex justify-between text-slate-600">
                            <span>Subtotal ({qty}×)</span>
                            <span>{formatCurrency(subtotal)}</span>
                        </div>
                        <div className="flex justify-between text-slate-600">
                            <span>Delivery</span>
                            <span>
                                {shipping === 0
                                    ? 'Free'
                                    : formatCurrency(shipping)}
                            </span>
                        </div>
                        <div className="flex justify-between border-t border-slate-200 pt-1 font-bold text-slate-900">
                            <span>Total</span>
                            <span>{formatCurrency(total)}</span>
                        </div>
                    </div>
                )}

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-xl bg-[#c8102e] py-3 text-sm font-bold text-white transition-colors hover:bg-[#a50d25] disabled:opacity-60"
                >
                    {processing ? 'Placing Order…' : 'Place Order'}
                </button>
            </form>
        </div>
    );
}

export default function LandingPageShow({
    page,
    product,
    bkashNumber,
    nagadNumber,
    siteName,
    siteLogo,
    pendingPayment,
    orderToken,
}: Props) {
    const sec = page.sections;
    const { settings } = usePage<SharedPageProps>().props;
    const insideDhakaRate = settings.shipping_rate_inside_dhaka ?? 60;
    const outsideDhakaRate = settings.shipping_rate_outside_dhaka ?? 120;

    // Scroll payment section into view when pending payment is active
    useEffect(() => {
        if (pendingPayment) {
            setTimeout(() => {
                document
                    .getElementById('order-form')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }, [!!pendingPayment]);
    const [openFaq, setOpenFaq] = useState<number | null>(null);
    const [activeMediaIdx, setActiveMediaIdx] = useState(0);
    // The variant the customer is looking at (picked in the order form): its
    // photo and price replace the product's in the hero and the gallery.
    const [focusedVariantId, setFocusedVariantId] = useState<number | null>(
        null,
    );
    const [showVariantImage, setShowVariantImage] = useState(false);
    const focusedVariant =
        product.variants.find((v) => v.id === focusedVariantId) ?? null;
    const hasVariants = product.variants.length > 0;
    const focusVariant = (id: number | null) => {
        setFocusedVariantId(id);
        setShowVariantImage(true);
    };
    const allMedia = product.media ?? [];
    const primaryIdx = allMedia.findIndex(
        (m) => m.is_primary && m.type === 'image',
    );
    const orderedMedia =
        primaryIdx >= 0
            ? [
                  allMedia[primaryIdx],
                  ...allMedia.filter((_, i) => i !== primaryIdx),
              ]
            : allMedia;
    const heroImage = orderedMedia.find((m) => m.type === 'image');
    const heroImageSrc = focusedVariant?.image ?? heroImage?.path;
    const heroPrice =
        focusedVariant?.price ??
        (hasVariants
            ? Math.min(...product.variants.map((v) => v.price))
            : product.price);
    const heroCompare = focusedVariant
        ? focusedVariant.compare_price
        : hasVariants
          ? null
          : product.compare_price;

    return (
        <>
            <Head title={page.title}>
                <style>{`
                    @font-face {
                        font-family: 'Kalpurush';
                        src: url('/fonts/Kalpurush.ttf') format('truetype');
                        font-weight: normal;
                        font-style: normal;
                        font-display: swap;
                        unicode-range: U+0980-U+09FF;
                    }
                `}</style>
            </Head>
            <div
                className="min-h-screen bg-white"
                style={{
                    fontFamily:
                        "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif, 'Kalpurush'",
                }}
            >
                {/* Minimal header */}
                <header className="sticky top-0 z-30 border-b border-slate-100 bg-white/95 px-4 py-3 backdrop-blur">
                    <div className="mx-auto flex max-w-6xl items-center justify-between">
                        <div className="flex items-center gap-2">
                            {siteLogo ? (
                                <img
                                    src={siteLogo}
                                    alt={siteName}
                                    className="h-14 w-auto object-contain"
                                />
                            ) : (
                                <span className="flex size-8 items-center justify-center rounded-lg bg-[#c8102e]">
                                    <ShoppingBag className="size-4 text-white" />
                                </span>
                            )}
                            <span className="text-sm font-bold text-slate-800">
                                {siteName}
                            </span>
                        </div>
                        <a
                            href="#order-form"
                            className="rounded-lg bg-[#c8102e] px-4 py-1.5 text-xs font-bold text-white transition-colors hover:bg-[#a50d25]"
                        >
                            {sec.hero?.cta_text || 'Order Now'}
                        </a>
                    </div>
                </header>

                {/* Hero */}
                {sec.hero?.enabled !== false && (
                    <section
                        style={{
                            backgroundColor: sec.hero?.bg_color || '#0f172a',
                        }}
                        className="px-4 py-12"
                    >
                        <div className="mx-auto grid max-w-6xl grid-cols-1 items-center gap-10 lg:grid-cols-2">
                            <div className="order-2 space-y-4 text-white lg:order-1">
                                <h1 className="text-3xl leading-tight font-extrabold lg:text-4xl">
                                    {sec.hero?.headline || product.name}
                                </h1>
                                {sec.hero?.subheadline && (
                                    <p className="text-lg text-white/80">
                                        {sec.hero.subheadline}
                                    </p>
                                )}
                                {product.short_description && (
                                    <p className="text-sm leading-relaxed text-white/70">
                                        {product.short_description}
                                    </p>
                                )}
                                <div className="flex items-center gap-3 pt-2">
                                    {hasVariants && !focusedVariant && (
                                        <span className="text-sm text-white/70">
                                            From
                                        </span>
                                    )}
                                    <span className="text-3xl font-bold">
                                        {formatCurrency(heroPrice)}
                                    </span>
                                    {heroCompare && heroCompare > heroPrice && (
                                        <>
                                            <span className="text-lg text-white/50 line-through">
                                                {formatCurrency(heroCompare)}
                                            </span>
                                            <span className="rounded-full bg-red-500 px-2.5 py-0.5 text-xs font-bold text-white">
                                                {Math.round(
                                                    (1 -
                                                        heroPrice /
                                                            heroCompare) *
                                                        100,
                                                )}
                                                % OFF
                                            </span>
                                        </>
                                    )}
                                </div>
                                <a
                                    href="#order-form"
                                    className="inline-block rounded-xl bg-white px-6 py-3 font-bold text-[#c8102e] transition-colors hover:bg-slate-100"
                                >
                                    {sec.hero?.cta_text || 'Order Now'}
                                </a>
                            </div>
                            {heroImageSrc && (
                                <div className="order-1 lg:order-2">
                                    <img
                                        src={heroImageSrc}
                                        alt={product.name}
                                        className="max-h-96 w-full rounded-2xl object-contain"
                                    />
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {/* Benefits */}
                {sec.benefits?.enabled && (
                    <section className="bg-slate-50 px-4 py-12">
                        <div className="mx-auto max-w-3xl text-center">
                            <h2 className="mb-8 text-2xl font-bold text-slate-900">
                                {sec.benefits.title}
                            </h2>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {(sec.benefits.items as string[]).map(
                                    (item, i) => (
                                        <div
                                            key={i}
                                            className="flex items-start gap-3 rounded-xl bg-white p-4 text-left shadow-sm"
                                        >
                                            <CheckCircle className="mt-0.5 size-5 shrink-0 text-[#c8102e]" />
                                            <span className="text-sm text-slate-700">
                                                {item}
                                            </span>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {/* Main content + Order form */}
                <section id="order-form" className="px-4 py-12">
                    <div className="mx-auto grid max-w-6xl grid-cols-1 items-start gap-10 lg:grid-cols-2">
                        <div className="space-y-8">
                            {/* Gallery */}
                            {sec.gallery?.enabled &&
                                orderedMedia.length > 0 && (
                                    <div>
                                        <h2 className="mb-4 text-xl font-bold text-slate-900">
                                            {sec.gallery.title}
                                        </h2>
                                        <div className="space-y-3">
                                            {/* Active media viewer */}
                                            {showVariantImage &&
                                            focusedVariant?.image ? (
                                                <img
                                                    src={focusedVariant.image}
                                                    alt={`${product.name} – ${variantName(focusedVariant)}`}
                                                    className="max-h-80 w-full rounded-2xl bg-slate-50 object-contain"
                                                />
                                            ) : orderedMedia[activeMediaIdx]
                                                  ?.type === 'video' ? (
                                                <video
                                                    key={
                                                        orderedMedia[
                                                            activeMediaIdx
                                                        ].path
                                                    }
                                                    src={
                                                        orderedMedia[
                                                            activeMediaIdx
                                                        ].path
                                                    }
                                                    controls
                                                    className="max-h-80 w-full rounded-2xl bg-slate-900"
                                                />
                                            ) : (
                                                <img
                                                    src={
                                                        orderedMedia[
                                                            activeMediaIdx
                                                        ]?.path
                                                    }
                                                    alt={product.name}
                                                    className="max-h-80 w-full rounded-2xl bg-slate-50 object-contain"
                                                />
                                            )}

                                            {/* Thumbnail strip */}
                                            {orderedMedia.length > 1 && (
                                                <div className="flex gap-2 overflow-x-auto pb-1">
                                                    {orderedMedia.map(
                                                        (item, i) => (
                                                            <button
                                                                key={item.id}
                                                                type="button"
                                                                onClick={() => {
                                                                    setShowVariantImage(
                                                                        false,
                                                                    );
                                                                    setActiveMediaIdx(
                                                                        i,
                                                                    );
                                                                }}
                                                                className={`relative size-16 shrink-0 overflow-hidden rounded-xl border-2 bg-slate-100 transition-colors ${i === activeMediaIdx ? 'border-[#c8102e]' : 'border-transparent'}`}
                                                            >
                                                                {item.type ===
                                                                'video' ? (
                                                                    <>
                                                                        <video
                                                                            src={
                                                                                item.path
                                                                            }
                                                                            className="size-full object-cover"
                                                                            muted
                                                                        />
                                                                        <div className="absolute inset-0 flex items-center justify-center bg-black/30">
                                                                            <Play className="size-5 fill-white text-white" />
                                                                        </div>
                                                                    </>
                                                                ) : (
                                                                    <img
                                                                        src={
                                                                            item.path
                                                                        }
                                                                        alt=""
                                                                        className="size-full object-cover"
                                                                    />
                                                                )}
                                                            </button>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                            {/* Description */}
                            {sec.description?.enabled && (
                                <div>
                                    <h2 className="mb-3 text-xl font-bold whitespace-pre-line text-slate-900">
                                        {sec.description.title}
                                    </h2>
                                    {sec.description.use_product_description ? (
                                        <div
                                            className="prose prose-sm max-w-none text-slate-600"
                                            dangerouslySetInnerHTML={{
                                                __html:
                                                    product.description ?? '',
                                            }}
                                        />
                                    ) : (
                                        <div
                                            className="prose prose-sm max-w-none text-slate-600"
                                            dangerouslySetInnerHTML={{
                                                __html: sec.description
                                                    .custom_content,
                                            }}
                                        />
                                    )}
                                </div>
                            )}

                            {/* Delivery info */}
                            <div className="space-y-2 rounded-xl border border-slate-100 p-4">
                                <div className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <Truck className="size-4 text-[#c8102e]" />{' '}
                                    Delivery Info
                                </div>
                                {product.free_delivery ? (
                                    <p className="text-sm font-medium text-emerald-600">
                                        ✓ Free delivery on this product
                                    </p>
                                ) : (
                                    <div className="space-y-1 text-sm text-slate-600">
                                        <p>
                                            Inside Dhaka City Corporation:{' '}
                                            <strong>
                                                {formatCurrency(
                                                    insideDhakaRate,
                                                )}
                                            </strong>
                                        </p>
                                        <p>
                                            Outside Dhaka / Rest of Bangladesh:{' '}
                                            <strong>
                                                {formatCurrency(
                                                    outsideDhakaRate,
                                                )}
                                            </strong>
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Order form / inline payment */}
                        <div className="lg:sticky lg:top-20">
                            {pendingPayment ? (
                                <InlinePayment
                                    pending={pendingPayment}
                                    slug={page.slug}
                                    bkashNumber={bkashNumber}
                                    nagadNumber={nagadNumber}
                                />
                            ) : (
                                <OrderForm
                                    product={product}
                                    slug={page.slug}
                                    maxQuantity={page.max_order_quantity}
                                    focusedVariantId={focusedVariantId}
                                    onFocusVariant={focusVariant}
                                    bkashNumber={bkashNumber}
                                    nagadNumber={nagadNumber}
                                    orderToken={orderToken}
                                />
                            )}
                        </div>
                    </div>
                </section>

                {/* Testimonials */}
                {sec.testimonials?.enabled &&
                    sec.testimonials.items?.length > 0 && (
                        <section className="bg-slate-50 px-4 py-12">
                            <div className="mx-auto max-w-4xl">
                                <h2 className="mb-8 text-center text-2xl font-bold text-slate-900">
                                    {sec.testimonials.title}
                                </h2>
                                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    {sec.testimonials.items.map(
                                        (item: any, i: number) =>
                                            item.name && (
                                                <div
                                                    key={i}
                                                    className="rounded-2xl bg-white p-5 shadow-sm"
                                                >
                                                    <StarRating
                                                        rating={item.rating}
                                                    />
                                                    <p className="mt-2 text-sm leading-relaxed text-slate-600">
                                                        "{item.text}"
                                                    </p>
                                                    <p className="mt-3 text-xs font-semibold text-slate-800">
                                                        — {item.name}
                                                    </p>
                                                </div>
                                            ),
                                    )}
                                </div>
                            </div>
                        </section>
                    )}

                {/* FAQ */}
                {sec.faq?.enabled && sec.faq.items?.length > 0 && (
                    <section className="px-4 py-12">
                        <div className="mx-auto max-w-2xl">
                            <h2 className="mb-6 text-center text-2xl font-bold text-slate-900">
                                {sec.faq.title}
                            </h2>
                            <div className="space-y-2">
                                {sec.faq.items.map(
                                    (item: any, i: number) =>
                                        item.question && (
                                            <div
                                                key={i}
                                                className="overflow-hidden rounded-xl border border-slate-100"
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setOpenFaq(
                                                            openFaq === i
                                                                ? null
                                                                : i,
                                                        )
                                                    }
                                                    className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-800 hover:bg-slate-50"
                                                >
                                                    {item.question}
                                                    {openFaq === i ? (
                                                        <ChevronUp className="size-4 shrink-0 text-slate-400" />
                                                    ) : (
                                                        <ChevronDown className="size-4 shrink-0 text-slate-400" />
                                                    )}
                                                </button>
                                                {openFaq === i && (
                                                    <div className="px-4 pb-3 text-sm leading-relaxed text-slate-600">
                                                        {item.answer}
                                                    </div>
                                                )}
                                            </div>
                                        ),
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {/* Footer */}
                {sec.footer?.enabled && (
                    <footer className="bg-slate-900 px-4 pt-10 pb-6">
                        <div className="mx-auto max-w-6xl">
                            {/* Top row: brand + contact */}
                            <div className="flex flex-col items-center gap-8 sm:flex-row sm:items-start sm:justify-between">
                                {/* Brand */}
                                <div className="flex flex-col items-center gap-3 sm:items-start">
                                    <div className="flex items-center gap-2">
                                        {siteLogo ? (
                                            <img
                                                src={siteLogo}
                                                alt={siteName}
                                                className="h-14 w-auto object-contain"
                                            />
                                        ) : (
                                            <ShoppingBag className="size-6 text-white" />
                                        )}
                                        <span className="text-base font-bold text-white">
                                            {siteName}
                                        </span>
                                    </div>
                                    {sec.footer.text && (
                                        <p className="max-w-xs text-center text-xs whitespace-pre-line text-slate-400 sm:text-left">
                                            {sec.footer.text}
                                        </p>
                                    )}
                                </div>

                                {/* Contact info */}
                                <div className="flex flex-col items-center gap-3 sm:items-end">
                                    <p className="text-xs font-semibold tracking-widest text-slate-500 uppercase">
                                        Contact Us
                                    </p>
                                    {settings.contact_phone && (
                                        <a
                                            href={`tel:${settings.contact_phone}`}
                                            className="flex items-center gap-2 text-sm text-slate-300 transition-colors hover:text-white"
                                        >
                                            <Phone
                                                size={14}
                                                className="shrink-0 text-slate-500"
                                            />
                                            {settings.contact_phone}
                                        </a>
                                    )}
                                    {settings.contact_email && (
                                        <a
                                            href={`mailto:${settings.contact_email}`}
                                            className="flex items-center gap-2 text-sm text-slate-300 transition-colors hover:text-white"
                                        >
                                            <Mail
                                                size={14}
                                                className="shrink-0 text-slate-500"
                                            />
                                            {settings.contact_email}
                                        </a>
                                    )}
                                    {settings.contact_address && (
                                        <p className="flex max-w-xs items-start gap-2 text-right text-sm text-slate-300">
                                            <MapPin
                                                size={14}
                                                className="mt-0.5 shrink-0 text-slate-500"
                                            />
                                            {settings.contact_address}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Divider + copyright */}
                            <div className="mt-8 border-t border-slate-800 pt-5 text-center">
                                <p className="text-xs text-slate-500">
                                    &copy; {new Date().getFullYear()} {siteName}
                                    . All rights reserved.
                                </p>
                            </div>
                        </div>
                    </footer>
                )}
            </div>

            <SocialChatWidget />
        </>
    );
}
