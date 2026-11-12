import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    ChevronUp,
    Mail,
    MapPin,
    Minus,
    Phone,
    Play,
    Plus,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
    Star,
    Truck,
    RotateCcw,
} from 'lucide-react';
import { useEffect, useId, useMemo, useState } from 'react';
import HoneypotField from '@/components/honeypot-field';
import GlowButton from '@/components/landing/GlowButton';
import HeroCarousel from '@/components/landing/HeroCarousel';
import type { CarouselSettings } from '@/components/landing/HeroCarousel';
import { TestimonialCarousel } from '@/components/landing/TestimonialCarousel';
import SocialChatWidget from '@/components/layout/SocialChatWidget';
import { SiteLogo } from '@/components/shared/SiteLogo';
import { CharCount } from '@/components/ui/char-count';
import { Select } from '@/components/ui/select';
import { cn, formatCurrency } from '@/lib/utils';
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

type Combo = {
    id: string;
    name: string;
    description: string;
    badge: string;
    price: number;
    compare_price: number | null;
    stock_quantity: number;
    image: string | null;
    items: {
        variant_id: number;
        quantity: number;
        attributes: Record<string, string> | null;
        image: string | null;
    }[];
};

/**
 * One thing a customer can pick: a single variant, or a combo of several.
 * `parts` is what it takes from stock, `units` how many items it counts as
 * toward the page's per-order limit.
 */
type Option = {
    key: string;
    kind: 'variant' | 'combo';
    id: number | string;
    name: string;
    detail: string;
    badge: string;
    price: number;
    compare: number | null;
    stock: number;
    image: string | null;
    parts: { variant_id: number; quantity: number }[];
    units: number;
};

type Selection = Record<string, number>;

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

/** Just the values ("Bubu", "Red · M") — the short name a pick-list card leads with. */
function variantShortName(attributes: Record<string, string> | null): string {
    const values = Object.values(attributes ?? {});

    return values.length > 0 ? values.join(' · ') : 'Option';
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
    combos: Combo[];
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
                        maxLength={40}
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

const cardBase =
    'rounded-3xl bg-white shadow-[0_20px_60px_-24px_rgba(11,16,32,0.35)] ring-1 ring-slate-900/5';

/** One pickable option (a single variant or a combo), as a toggle tile or a quantity stepper. */
function OptionCard({
    option,
    qty,
    ceiling,
    mode,
    onChange,
    onFocus,
}: {
    option: Option;
    qty: number;
    ceiling: number;
    mode: 'pick' | 'stepper';
    onChange: (next: number) => void;
    onFocus?: () => void;
}) {
    const soldOut = option.stock < 1;
    const selected = qty > 0;
    const savings =
        option.compare && option.compare > option.price
            ? option.compare - option.price
            : 0;

    const body = (
        <>
            {option.image ? (
                <img
                    src={option.image}
                    alt=""
                    className="size-16 shrink-0 rounded-2xl bg-slate-100 object-cover"
                />
            ) : (
                <div className="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <ShoppingBag className="size-6" />
                </div>
            )}
            <div className="min-w-0 flex-1 text-left">
                <div className="flex flex-wrap items-center gap-1.5">
                    <p className="text-[15px] font-bold text-slate-900">
                        {option.name}
                    </p>
                    {option.badge && (
                        <span className="rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-extrabold tracking-wide text-slate-900 uppercase">
                            {option.badge}
                        </span>
                    )}
                    {option.kind === 'combo' && !option.badge && (
                        <span className="rounded-full bg-[#c8102e]/10 px-2 py-0.5 text-[10px] font-extrabold tracking-wide text-[#c8102e] uppercase">
                            Combo
                        </span>
                    )}
                </div>
                {option.detail && (
                    <p className="mt-0.5 line-clamp-2 text-xs text-slate-500">
                        {option.detail}
                    </p>
                )}
                <p className="mt-1 flex flex-wrap items-baseline gap-x-2 text-sm">
                    <span className="font-extrabold text-[#c8102e]">
                        {formatCurrency(option.price)}
                    </span>
                    {option.compare && option.compare > option.price && (
                        <span className="text-xs text-slate-400 line-through">
                            {formatCurrency(option.compare)}
                        </span>
                    )}
                    {savings > 0 && (
                        <span className="text-xs font-semibold text-emerald-600">
                            Save {formatCurrency(savings)}
                        </span>
                    )}
                    {soldOut ? (
                        <span className="text-xs font-semibold text-red-500">
                            Out of stock
                        </span>
                    ) : option.stock <= 5 ? (
                        <span className="text-xs font-semibold text-amber-600">
                            Only {option.stock} left
                        </span>
                    ) : null}
                </p>
            </div>
        </>
    );

    if (mode === 'pick') {
        return (
            <button
                type="button"
                disabled={soldOut}
                onClick={() => {
                    onFocus?.();
                    onChange(selected ? 0 : 1);
                }}
                aria-pressed={selected}
                className={cn(
                    'group relative flex flex-col overflow-hidden rounded-2xl border-2 bg-white text-left transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50',
                    selected
                        ? 'border-[#c8102e] shadow-[0_14px_34px_-14px_rgba(200,16,46,0.55)]'
                        : 'border-slate-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md',
                )}
            >
                <div className="relative aspect-square w-full bg-slate-100">
                    {option.image ? (
                        <img
                            src={option.image}
                            alt={option.name}
                            className="size-full object-cover transition duration-300 group-hover:scale-105"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center text-slate-300">
                            <ShoppingBag className="size-10" />
                        </div>
                    )}
                    {(option.badge || option.kind === 'combo') && (
                        <span className="absolute top-2 left-2 rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-extrabold tracking-wide text-slate-900 uppercase shadow">
                            {option.badge || 'Combo'}
                        </span>
                    )}
                    <span
                        className={cn(
                            'absolute top-2 right-2 flex size-7 items-center justify-center rounded-full border-2 shadow transition-colors',
                            selected
                                ? 'border-[#c8102e] bg-[#c8102e] text-white'
                                : 'border-white bg-white/80 text-transparent',
                        )}
                    >
                        {qty > 1 ? (
                            <span className="text-[11px] font-bold">{qty}</span>
                        ) : (
                            <Check className="size-4" />
                        )}
                    </span>
                    {soldOut && (
                        <span className="absolute inset-x-0 bottom-0 bg-black/60 py-1 text-center text-[11px] font-bold text-white">
                            Out of stock
                        </span>
                    )}
                </div>
                <div className="space-y-0.5 p-3">
                    <p className="truncate text-sm font-bold text-slate-900">
                        {option.name}
                    </p>
                    {option.detail && (
                        <p className="line-clamp-1 text-[11px] text-slate-500">
                            {option.detail}
                        </p>
                    )}
                    <p className="flex flex-wrap items-baseline gap-x-1.5">
                        <span className="text-sm font-extrabold text-[#c8102e]">
                            {formatCurrency(option.price)}
                        </span>
                        {option.compare && option.compare > option.price && (
                            <span className="text-[11px] text-slate-400 line-through">
                                {formatCurrency(option.compare)}
                            </span>
                        )}
                    </p>
                    {!soldOut && option.stock <= 5 && (
                        <p className="text-[11px] font-semibold text-amber-600">
                            Only {option.stock} left
                        </p>
                    )}
                </div>
            </button>
        );
    }

    return (
        <div
            onClick={onFocus}
            className={cn(
                'flex flex-col gap-3 rounded-2xl border-2 bg-white p-3 transition-all duration-200 sm:flex-row sm:items-center',
                selected
                    ? 'border-[#c8102e] shadow-[0_10px_30px_-12px_rgba(200,16,46,0.5)]'
                    : 'border-slate-200',
                soldOut && 'opacity-50',
            )}
        >
            {body}
            <div className="flex items-center gap-1 self-end rounded-full bg-slate-100 p-1 sm:self-auto">
                <button
                    type="button"
                    disabled={qty === 0}
                    onClick={(e) => {
                        e.stopPropagation();
                        onChange(qty - 1);
                    }}
                    aria-label={`Decrease ${option.name}`}
                    className="flex size-9 items-center justify-center rounded-full bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-40"
                >
                    <Minus className="size-4" />
                </button>
                <span className="w-8 text-center text-sm font-extrabold text-slate-900">
                    {qty}
                </span>
                <button
                    type="button"
                    disabled={soldOut || qty >= ceiling}
                    onClick={(e) => {
                        e.stopPropagation();
                        onChange(qty + 1);
                    }}
                    aria-label={`Increase ${option.name}`}
                    className="flex size-9 items-center justify-center rounded-full bg-[#c8102e] text-white shadow-sm transition hover:bg-[#a50d25] disabled:opacity-40"
                >
                    <Plus className="size-4" />
                </button>
            </div>
        </div>
    );
}

function SectionHeading({
    eyebrow,
    title,
    subtitle,
    light = false,
}: {
    eyebrow?: string;
    title: string;
    subtitle?: string;
    light?: boolean;
}) {
    return (
        <div className="mx-auto mb-10 max-w-2xl text-center">
            {eyebrow && (
                <p className="mb-2 text-xs font-extrabold tracking-[0.2em] text-amber-500 uppercase">
                    {eyebrow}
                </p>
            )}
            <h2
                className={cn(
                    'text-3xl font-extrabold tracking-tight whitespace-pre-line sm:text-4xl',
                    light ? 'text-white' : 'text-slate-900',
                )}
            >
                {title}
            </h2>
            {subtitle && (
                <p
                    className={cn(
                        'mt-3 text-base',
                        light ? 'text-white/70' : 'text-slate-500',
                    )}
                >
                    {subtitle}
                </p>
            )}
        </div>
    );
}

const fieldClass =
    'h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-[#c8102e] focus:ring-4 focus:ring-[#c8102e]/10 focus:outline-none';

function OrderSection({
    product,
    sec,
    slug,
    maxQuantity,
    options,
    selection,
    ceilingFor,
    setOptionQty,
    onFocusVariant,
    bkashNumber,
    nagadNumber,
    orderToken,
    pendingPayment,
}: {
    product: Props['product'];
    sec: Sections;
    slug: string;
    maxQuantity: number;
    options: Option[];
    selection: Selection;
    ceilingFor: (option: Option) => number;
    setOptionQty: (option: Option, next: number) => void;
    onFocusVariant: (id: number | null) => void;
    bkashNumber: string | null;
    nagadNumber: string | null;
    orderToken: string;
    pendingPayment: PendingPayment | null;
}) {
    const { settings, mobile_banking_available: mobileBankingAvailable } =
        usePage<SharedPageProps>().props;
    const fieldId = useId();
    const insideDhakaRate = settings.shipping_rate_inside_dhaka ?? 60;
    const outsideDhakaRate = settings.shipping_rate_outside_dhaka ?? 120;

    const hasVariants = product.variants.length > 0;
    const [plainQty, setPlainQty] = useState(1);
    const chosen = options.filter((o) => (selection[o.key] ?? 0) > 0);
    const qty = hasVariants
        ? chosen.reduce((sum, o) => sum + (selection[o.key] ?? 0) * o.units, 0)
        : plainQty;
    const [state, setState] = useState('');
    const [city, setCity] = useState('');
    const [variantError, setVariantError] = useState<string | null>(null);

    const shipping = calcShipping(
        state,
        city,
        product.free_delivery,
        insideDhakaRate,
        outsideDhakaRate,
    );
    const subtotal = hasVariants
        ? chosen.reduce((sum, o) => sum + o.price * (selection[o.key] ?? 0), 0)
        : product.price * qty;
    const regularTotal = hasVariants
        ? chosen.reduce(
              (sum, o) =>
                  sum + (o.compare ?? o.price) * (selection[o.key] ?? 0),
              0,
          )
        : (product.compare_price ?? product.price) * qty;
    const savings = Math.max(0, regularTotal - subtotal);
    const total = subtotal + shipping;
    const locationChosen = Boolean(state && city);

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
        notes: '',
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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (hasVariants && chosen.length === 0) {
            setVariantError('Choose at least one option and a quantity.');

            return;
        }

        setVariantError(null);
        transform((formData) => {
            const { quantity, ...rest } = formData;

            if (!hasVariants) {
                return { ...rest, quantity };
            }

            return {
                ...rest,
                items: chosen
                    .filter((o) => o.kind === 'variant')
                    .map((o) => ({
                        variant_id: o.id,
                        quantity: selection[o.key],
                    })),
                combos: chosen
                    .filter((o) => o.kind === 'combo')
                    .map((o) => ({
                        combo_id: o.id,
                        quantity: selection[o.key],
                    })),
            };
        });
        post(route('lp.order', slug));
    };

    const sel = sec.selection ?? {};

    return (
        <section
            id="order"
            className="relative scroll-mt-20 overflow-hidden bg-[#faf7f2] px-4 py-20"
        >
            <div className="pointer-events-none absolute -top-40 left-1/2 size-[40rem] -translate-x-1/2 rounded-full bg-[#c8102e]/10 blur-3xl" />
            <div className="relative mx-auto max-w-6xl">
                <SectionHeading
                    eyebrow="Order now"
                    title={sel.title || 'Select your order'}
                    subtitle={sel.subtitle}
                />

                {pendingPayment ? (
                    <div className="mx-auto max-w-lg">
                        <InlinePayment
                            pending={pendingPayment}
                            slug={slug}
                            bkashNumber={bkashNumber}
                            nagadNumber={nagadNumber}
                        />
                    </div>
                ) : (
                    <form
                        onSubmit={handleSubmit}
                        className="grid grid-cols-1 items-start gap-8 lg:grid-cols-[1.05fr_1fr]"
                    >
                        {/* Left: what to buy */}
                        <div className={cn(cardBase, 'space-y-4 p-5 sm:p-7')}>
                            <div className="flex items-baseline justify-between">
                                <h3 className="text-lg font-extrabold text-slate-900">
                                    {hasVariants
                                        ? 'Choose your options'
                                        : 'Quantity'}
                                </h3>
                                {hasVariants && (
                                    <span className="text-xs font-medium text-slate-500">
                                        {qty} of {maxQuantity} max
                                    </span>
                                )}
                            </div>

                            {hasVariants ? (
                                <div className="space-y-3">
                                    {options.map((option) => (
                                        <OptionCard
                                            key={option.key}
                                            option={option}
                                            mode="stepper"
                                            qty={selection[option.key] ?? 0}
                                            ceiling={ceilingFor(option)}
                                            onChange={(next) =>
                                                setOptionQty(option, next)
                                            }
                                            onFocus={() =>
                                                onFocusVariant(
                                                    option.kind === 'variant'
                                                        ? (option.id as number)
                                                        : null,
                                                )
                                            }
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
                                    <div>
                                        <p className="font-bold text-slate-900">
                                            {product.name}
                                        </p>
                                        <p className="text-sm font-extrabold text-[#c8102e]">
                                            {formatCurrency(product.price)}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1 rounded-full bg-slate-100 p-1">
                                        <button
                                            type="button"
                                            onClick={() => updateQty(qty - 1)}
                                            aria-label="Decrease quantity"
                                            className="flex size-9 items-center justify-center rounded-full bg-white shadow-sm hover:bg-slate-50"
                                        >
                                            <Minus className="size-4" />
                                        </button>
                                        <span className="w-8 text-center text-sm font-extrabold">
                                            {qty}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => updateQty(qty + 1)}
                                            aria-label="Increase quantity"
                                            className="flex size-9 items-center justify-center rounded-full bg-[#c8102e] text-white shadow-sm hover:bg-[#a50d25]"
                                        >
                                            <Plus className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            )}

                            {(variantError ||
                                itemsError ||
                                errors.quantity) && (
                                <p className="rounded-xl bg-red-50 px-3 py-2 text-sm font-medium text-red-600">
                                    {variantError ??
                                        itemsError ??
                                        errors.quantity}
                                </p>
                            )}
                        </div>

                        {/* Right: price summary, then the form */}
                        <div className="space-y-6">
                            <div className="overflow-hidden rounded-3xl bg-[#0b1020] text-white shadow-[0_24px_70px_-24px_rgba(11,16,32,0.7)]">
                                <div className="bg-gradient-to-r from-[#c8102e] to-[#ff3a55] px-6 py-3 text-sm font-extrabold tracking-wide uppercase">
                                    Price summary
                                </div>
                                <div className="space-y-3 p-6 text-sm">
                                    {hasVariants && chosen.length === 0 ? (
                                        <p className="text-white/60">
                                            Pick an option on the left to see
                                            your total.
                                        </p>
                                    ) : hasVariants ? (
                                        chosen.map((o) => (
                                            <div
                                                key={o.key}
                                                className="flex justify-between gap-3"
                                            >
                                                <span className="text-white/80">
                                                    {o.name}{' '}
                                                    <span className="text-white/50">
                                                        ×{selection[o.key]}
                                                    </span>
                                                </span>
                                                <span className="font-semibold">
                                                    {formatCurrency(
                                                        o.price *
                                                            selection[o.key],
                                                    )}
                                                </span>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="flex justify-between gap-3">
                                            <span className="text-white/80">
                                                {product.name}{' '}
                                                <span className="text-white/50">
                                                    ×{qty}
                                                </span>
                                            </span>
                                            <span className="font-semibold">
                                                {formatCurrency(subtotal)}
                                            </span>
                                        </div>
                                    )}
                                    <div className="space-y-2 border-t border-white/10 pt-3">
                                        <div className="flex justify-between text-white/70">
                                            <span>Subtotal</span>
                                            <span>
                                                {formatCurrency(subtotal)}
                                            </span>
                                        </div>
                                        {savings > 0 && (
                                            <div className="flex justify-between font-semibold text-emerald-400">
                                                <span>You save</span>
                                                <span>
                                                    −{formatCurrency(savings)}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex justify-between text-white/70">
                                            <span>Delivery</span>
                                            <span>
                                                {product.free_delivery
                                                    ? 'Free'
                                                    : locationChosen
                                                      ? shipping === 0
                                                          ? 'Free'
                                                          : formatCurrency(
                                                                shipping,
                                                            )
                                                      : 'Pick your area below'}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex items-baseline justify-between border-t border-white/10 pt-4">
                                        <span className="text-base font-bold">
                                            Total
                                        </span>
                                        <span className="text-3xl font-extrabold text-amber-400">
                                            {formatCurrency(
                                                product.free_delivery ||
                                                    locationChosen
                                                    ? total
                                                    : subtotal,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                className={cn(cardBase, 'space-y-3 p-4 sm:p-5')}
                            >
                                <h3 className="text-base font-extrabold text-slate-900">
                                    Delivery details
                                </h3>
                                <HoneypotField
                                    value={data.sd_confirm_ref}
                                    onChange={(value) =>
                                        setData('sd_confirm_ref', value)
                                    }
                                />

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <FieldLabel
                                            htmlFor={`${fieldId}-name`}
                                            required
                                        >
                                            Full Name
                                        </FieldLabel>
                                        <input
                                            maxLength={100}
                                            id={`${fieldId}-name`}
                                            className={fieldClass}
                                            placeholder="Your full name"
                                            value={data.name}
                                            onChange={(e) =>
                                                setData('name', e.target.value)
                                            }
                                        />
                                        {errors.name && (
                                            <p className="mt-1 text-xs text-red-500">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <FieldLabel
                                            htmlFor={`${fieldId}-phone`}
                                            required
                                        >
                                            Phone Number
                                        </FieldLabel>
                                        <input
                                            id={`${fieldId}-phone`}
                                            type="tel"
                                            inputMode="tel"
                                            maxLength={20}
                                            className={fieldClass}
                                            placeholder="017XXXXXXXX"
                                            value={data.phone}
                                            onChange={(e) =>
                                                setData('phone', e.target.value)
                                            }
                                        />
                                        {errors.phone && (
                                            <p className="mt-1 text-xs text-red-500">
                                                {errors.phone}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <FieldLabel htmlFor={`${fieldId}-email`}>
                                        Email{' '}
                                        <span className="font-normal text-slate-400">
                                            (optional)
                                        </span>
                                    </FieldLabel>
                                    <input
                                        maxLength={150}
                                        id={`${fieldId}-email`}
                                        className={fieldClass}
                                        placeholder="you@example.com"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                    />
                                </div>
                                <div>
                                    <FieldLabel
                                        htmlFor={`${fieldId}-address`}
                                        required
                                    >
                                        Address
                                    </FieldLabel>
                                    <input
                                        maxLength={200}
                                        id={`${fieldId}-address`}
                                        className={fieldClass}
                                        placeholder="House, road, area"
                                        value={data.address_line1}
                                        onChange={(e) =>
                                            setData(
                                                'address_line1',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.address_line1 && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.address_line1}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <Select
                                            label="District"
                                            required
                                            labelClassName="text-slate-700"
                                            containerClassName="space-y-0 [&>label]:mb-1 [&>label]:block"
                                            selectClassName={fieldClass}
                                            value={data.state}
                                            onChange={(e) =>
                                                updateState(e.target.value)
                                            }
                                        >
                                            <option value="">
                                                Select district
                                            </option>
                                            {BD_DISTRICTS.map((d) => (
                                                <option key={d} value={d}>
                                                    {d}
                                                </option>
                                            ))}
                                        </Select>
                                        {errors.state && (
                                            <p className="mt-1 text-xs text-red-500">
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
                                                selectClassName={fieldClass}
                                                value={data.city}
                                                onChange={(e) =>
                                                    updateCity(e.target.value)
                                                }
                                            >
                                                <option value="">
                                                    Select area
                                                </option>
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
                                                    maxLength={60}
                                                    id={`${fieldId}-city`}
                                                    className={fieldClass}
                                                    placeholder="City or upazila"
                                                    value={data.city}
                                                    onChange={(e) =>
                                                        updateCity(
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </>
                                        )}
                                        {errors.city && (
                                            <p className="mt-1 text-xs text-red-500">
                                                {errors.city}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <FieldLabel htmlFor={`${fieldId}-notes`}>
                                        {sel.notes_label || 'Order notes'}{' '}
                                        <span className="font-normal text-slate-400">
                                            (optional)
                                        </span>
                                    </FieldLabel>
                                    <textarea
                                        id={`${fieldId}-notes`}
                                        rows={2}
                                        maxLength={500}
                                        className="w-full resize-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-[#c8102e] focus:ring-4 focus:ring-[#c8102e]/10 focus:outline-none"
                                        placeholder={
                                            sel.notes_placeholder ||
                                            'Anything we should know? (optional)'
                                        }
                                        value={data.notes}
                                        onChange={(e) =>
                                            setData('notes', e.target.value)
                                        }
                                    />
                                    <CharCount
                                        value={data.notes}
                                        max={500}
                                        className="mt-0.5 block text-right"
                                    />
                                    {errors.notes && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.notes}
                                        </p>
                                    )}
                                </div>

                                {/* Payment method */}
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-slate-700">
                                        Payment Method
                                    </p>
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <label
                                            className={cn(
                                                'flex cursor-pointer items-center gap-3 rounded-xl border-2 p-2.5 transition',
                                                data.payment_method === 'cod'
                                                    ? 'border-[#c8102e] bg-[#c8102e]/5'
                                                    : 'border-slate-200 hover:border-slate-300',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="pm"
                                                value="cod"
                                                checked={
                                                    data.payment_method ===
                                                    'cod'
                                                }
                                                onChange={() =>
                                                    setData(
                                                        'payment_method',
                                                        'cod',
                                                    )
                                                }
                                                className="accent-[#c8102e]"
                                            />
                                            <span className="text-sm font-semibold">
                                                Cash on Delivery
                                            </span>
                                        </label>
                                        {(bkashNumber || nagadNumber) && (
                                            <label
                                                className={cn(
                                                    'flex items-center gap-3 rounded-xl border-2 p-2.5 transition',
                                                    mobileBankingAvailable
                                                        ? 'cursor-pointer'
                                                        : 'cursor-not-allowed opacity-50',
                                                    data.payment_method ===
                                                        'mobile_banking'
                                                        ? 'border-[#c8102e] bg-[#c8102e]/5'
                                                        : 'border-slate-200 hover:border-slate-300',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="pm"
                                                    value="mobile_banking"
                                                    disabled={
                                                        !mobileBankingAvailable
                                                    }
                                                    checked={
                                                        data.payment_method ===
                                                        'mobile_banking'
                                                    }
                                                    onChange={() =>
                                                        setData(
                                                            'payment_method',
                                                            'mobile_banking',
                                                        )
                                                    }
                                                    className="accent-[#c8102e]"
                                                />
                                                <span className="text-sm font-semibold">
                                                    Mobile Banking (Bkash /
                                                    Nagad)
                                                    {!mobileBankingAvailable && (
                                                        <span className="ml-1 font-normal text-slate-500">
                                                            — temporarily
                                                            unavailable
                                                        </span>
                                                    )}
                                                </span>
                                            </label>
                                        )}
                                    </div>
                                    {data.payment_method ===
                                        'mobile_banking' && (
                                        <div className="flex gap-4 pl-2">
                                            {bkashNumber && (
                                                <label className="flex cursor-pointer items-center gap-1.5">
                                                    <input
                                                        type="radio"
                                                        name="provider"
                                                        value="bkash"
                                                        checked={
                                                            data.provider ===
                                                            'bkash'
                                                        }
                                                        onChange={() =>
                                                            setData(
                                                                'provider',
                                                                'bkash',
                                                            )
                                                        }
                                                        className="[--control-color:var(--color-pink-600)]"
                                                    />
                                                    <span className="text-sm font-semibold text-pink-700">
                                                        Bkash
                                                    </span>
                                                </label>
                                            )}
                                            {nagadNumber && (
                                                <label className="flex cursor-pointer items-center gap-1.5">
                                                    <input
                                                        type="radio"
                                                        name="provider"
                                                        value="nagad"
                                                        checked={
                                                            data.provider ===
                                                            'nagad'
                                                        }
                                                        onChange={() =>
                                                            setData(
                                                                'provider',
                                                                'nagad',
                                                            )
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

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="lp-glow relative flex h-14 w-full items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-b from-[#ff3a55] to-[#c8102e] text-lg font-extrabold tracking-wide text-white ring-1 ring-white/25 transition-transform ring-inset hover:scale-[1.02] active:scale-[0.99] disabled:opacity-60"
                                >
                                    <span
                                        aria-hidden
                                        className="lp-shine pointer-events-none absolute inset-y-0 -left-1/3 w-1/3 -skew-x-12 bg-gradient-to-r from-transparent via-white/45 to-transparent"
                                    />
                                    <span className="relative">
                                        {processing
                                            ? 'Placing Order…'
                                            : `Place Order · ${formatCurrency(
                                                  product.free_delivery ||
                                                      locationChosen
                                                      ? total
                                                      : subtotal,
                                              )}`}
                                    </span>
                                </button>
                                <p className="text-center text-xs text-slate-500">
                                    🔒 Your details are only used to deliver
                                    this order.
                                </p>
                            </div>
                        </div>
                    </form>
                )}
            </div>
        </section>
    );
}

const landingCss = `
    @font-face {
        font-family: 'Kalpurush';
        src: url('/fonts/Kalpurush.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
        font-display: swap;
        unicode-range: U+0980-U+09FF;
    }
    @keyframes lp-glow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(232, 17, 42, 0.55), 0 10px 30px -6px rgba(232, 17, 42, 0.65); }
        50% { box-shadow: 0 0 0 14px rgba(232, 17, 42, 0), 0 14px 46px -4px rgba(255, 58, 85, 0.95); }
    }
    @keyframes lp-shine { 0% { left: -40%; } 60%, 100% { left: 140%; } }
    @keyframes lp-progress { from { transform: scaleX(0); } to { transform: scaleX(1); } }
    .lp-glow { animation: lp-glow 2s ease-in-out infinite; }
    .lp-shine { animation: lp-shine 3.2s ease-in-out infinite; }
    .lp-progress { animation-name: lp-progress; animation-timing-function: linear; animation-fill-mode: forwards; }
    @media (prefers-reduced-motion: reduce) {
        .lp-glow, .lp-shine, .lp-progress { animation: none; }
    }
`;

const featureIcons = [Truck, ShieldCheck, RotateCcw];

export default function LandingPageShow({
    page,
    product,
    combos,
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
    const maxQuantity = page.max_order_quantity;
    const contactPhone = sec.contact?.phone || settings.contact_phone || '';

    // Scroll payment section into view when pending payment is active
    useEffect(() => {
        if (pendingPayment) {
            setTimeout(() => {
                document
                    .getElementById('order')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [!!pendingPayment]);

    const [openFaq, setOpenFaq] = useState<number | null>(null);
    const [activeMediaIdx, setActiveMediaIdx] = useState(0);
    // The single variant the customer last touched: its photo replaces the
    // product's in the gallery.
    const [focusedVariantId, setFocusedVariantId] = useState<number | null>(
        null,
    );
    const [showVariantImage, setShowVariantImage] = useState(false);
    const focusedVariant =
        product.variants.find((v) => v.id === focusedVariantId) ?? null;
    const hasVariants = product.variants.length > 0;
    const focusVariant = (id: number | null) => {
        if (id === null) {
            return;
        }

        setFocusedVariantId(id);
        setShowVariantImage(true);
    };

    // Everything a customer can pick: the page's combos, then (unless the page
    // hides them) each variant on its own. One shared selection drives both the
    // pick-list beside the description and the order section.
    const options = useMemo<Option[]>(() => {
        const variantById = new Map(product.variants.map((v) => [v.id, v]));
        const comboOptions: Option[] = combos.map((combo) => ({
            key: `c:${combo.id}`,
            kind: 'combo',
            id: combo.id,
            name: combo.name,
            detail:
                combo.description ||
                combo.items
                    .map(
                        (item) =>
                            `${item.quantity > 1 ? `${item.quantity}× ` : ''}${variantShortName(item.attributes)}`,
                    )
                    .join(' + '),
            badge: combo.badge,
            price: combo.price,
            compare: combo.compare_price,
            stock: combo.stock_quantity,
            image: combo.image,
            parts: combo.items.map((item) => ({
                variant_id: item.variant_id,
                quantity: item.quantity,
            })),
            units: combo.items.reduce((sum, item) => sum + item.quantity, 0),
        }));
        const singleOptions: Option[] =
            sec.selection?.show_single_options === false
                ? []
                : product.variants.map((v) => ({
                      key: `v:${v.id}`,
                      kind: 'variant',
                      id: v.id,
                      name: variantShortName(v.attributes),
                      detail: '',
                      badge: '',
                      price: v.price,
                      compare: v.compare_price,
                      stock: v.stock_quantity,
                      image: variantById.get(v.id)?.image ?? null,
                      parts: [{ variant_id: v.id, quantity: 1 }],
                      units: 1,
                  }));

        return [...comboOptions, ...singleOptions];
    }, [combos, product.variants, sec.selection?.show_single_options]);

    const [selection, setSelection] = useState<Selection>({});
    const selectedUnits = options.reduce(
        (sum, o) => sum + (selection[o.key] ?? 0) * o.units,
        0,
    );

    /** Most of this option the customer can still add: stock left after the other picks, and the per-order limit. */
    const ceilingFor = (option: Option): number => {
        const used = new Map<number, number>();

        for (const other of options) {
            if (other.key === option.key) {
                continue;
            }

            for (const part of other.parts) {
                used.set(
                    part.variant_id,
                    (used.get(part.variant_id) ?? 0) +
                        part.quantity * (selection[other.key] ?? 0),
                );
            }
        }

        let ceiling = Math.floor(
            (maxQuantity -
                (selectedUnits - (selection[option.key] ?? 0) * option.units)) /
                option.units,
        );

        for (const part of option.parts) {
            const stock =
                product.variants.find((v) => v.id === part.variant_id)
                    ?.stock_quantity ?? 0;
            ceiling = Math.min(
                ceiling,
                Math.floor(
                    (stock - (used.get(part.variant_id) ?? 0)) / part.quantity,
                ),
            );
        }

        return Math.max(0, ceiling);
    };

    const setOptionQty = (option: Option, next: number) => {
        const clamped = Math.min(Math.max(0, next), ceilingFor(option));
        setSelection((current) => ({ ...current, [option.key]: clamped }));
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

    const carousel = sec.hero?.carousel ?? {};
    const heroSlides: string[] = [
        ...(carousel.use_product_images === false
            ? []
            : orderedMedia
                  .filter(
                      (m) =>
                          m.type === 'image' &&
                          !(carousel.hidden_media_ids ?? []).includes(m.id),
                  )
                  .map((m) => m.path)),
        ...((carousel.extra_slides ?? []) as string[]),
    ];
    const heroFallback = orderedMedia.find((m) => m.type === 'image')?.path;
    const slides =
        heroSlides.length > 0 ? heroSlides : heroFallback ? [heroFallback] : [];

    const heroPrice = hasVariants
        ? Math.min(...product.variants.map((v) => v.price))
        : product.price;
    const heroCompare = hasVariants ? null : product.compare_price;

    // Mobile: a sticky order bar once the visitor is past the hero, hidden
    // while the order section itself is on screen.
    const [pastHero, setPastHero] = useState(false);
    const [orderInView, setOrderInView] = useState(false);
    useEffect(() => {
        const onScroll = () => setPastHero(window.scrollY > 520);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        const target = document.getElementById('order');
        const observer = target
            ? new IntersectionObserver(([entry]) =>
                  setOrderInView(entry.isIntersecting),
              )
            : null;

        if (target && observer) {
            observer.observe(target);
        }

        return () => {
            window.removeEventListener('scroll', onScroll);
            observer?.disconnect();
        };
    }, []);

    const ctaText = sec.hero?.cta_text || 'Order Now';
    const features = (
        (sec.hero?.features ?? []) as {
            title: string;
            text: string;
        }[]
    ).filter((f) => f.title);
    const delivery = sec.delivery ?? {};
    const description = sec.description ?? {};

    return (
        <>
            <Head title={page.title}>
                <style>{landingCss}</style>
            </Head>
            <div
                className="min-h-screen bg-[#faf7f2] pb-24 lg:pb-0"
                style={{
                    fontFamily:
                        "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif, 'Kalpurush'",
                }}
            >
                {/* Header */}
                <header className="sticky top-0 z-30 border-b border-white/10 bg-[#0b1020]/90 px-4 py-2.5 backdrop-blur-xl">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
                        <div className="flex min-w-0 items-center">
                            <SiteLogo
                                src={siteLogo}
                                name={siteName}
                                plate="always"
                                tone="onDark"
                                className="h-9 sm:h-10"
                            />
                        </div>
                        <GlowButton href="#order" size="md">
                            {ctaText}
                        </GlowButton>
                    </div>
                </header>

                {/* Hero */}
                {sec.hero?.enabled !== false && (
                    <section
                        style={{
                            backgroundColor: sec.hero?.bg_color || '#0b1020',
                        }}
                        className="relative overflow-hidden px-4 pt-14 pb-20 text-white sm:pt-20"
                    >
                        <div className="pointer-events-none absolute -top-32 -left-24 size-[30rem] rounded-full bg-[#c8102e]/35 blur-[110px]" />
                        <div className="pointer-events-none absolute -right-24 bottom-0 size-[28rem] rounded-full bg-amber-400/20 blur-[110px]" />
                        <div className="relative mx-auto max-w-6xl">
                            <div className="grid grid-cols-1 items-center gap-14 lg:grid-cols-2">
                                <div className="order-2 space-y-6 lg:order-1">
                                    {sec.hero?.badge && (
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-400/40 bg-amber-400/10 px-3.5 py-1 text-xs font-bold tracking-wide text-amber-300 uppercase">
                                            <Sparkles className="size-3.5" />
                                            {sec.hero.badge}
                                        </span>
                                    )}
                                    <h1 className="text-4xl leading-[1.08] font-extrabold tracking-tight whitespace-pre-line sm:text-5xl lg:text-6xl">
                                        {sec.hero?.headline || product.name}
                                    </h1>
                                    {sec.hero?.subheadline && (
                                        <p className="max-w-xl text-lg whitespace-pre-line text-white/75">
                                            {sec.hero.subheadline}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        {hasVariants && (
                                            <span className="text-sm text-white/60">
                                                From
                                            </span>
                                        )}
                                        <span className="text-4xl font-extrabold text-amber-400">
                                            {formatCurrency(heroPrice)}
                                        </span>
                                        {heroCompare &&
                                            heroCompare > heroPrice && (
                                                <>
                                                    <span className="text-lg text-white/45 line-through">
                                                        {formatCurrency(
                                                            heroCompare,
                                                        )}
                                                    </span>
                                                    <span className="rounded-full bg-[#c8102e] px-2.5 py-0.5 text-xs font-extrabold">
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
                                    <div className="flex flex-wrap items-center gap-4 pt-2">
                                        <GlowButton href="#order" size="xl">
                                            {ctaText}
                                        </GlowButton>
                                        {sec.hero?.secondary_cta_text && (
                                            <a
                                                href="#details"
                                                className="inline-flex h-14 items-center rounded-full border border-white/25 px-7 text-base font-bold text-white transition hover:bg-white/10"
                                            >
                                                {sec.hero.secondary_cta_text}
                                            </a>
                                        )}
                                    </div>
                                </div>
                                <div className="order-1 mx-auto w-full max-w-md pb-6 lg:order-2 lg:max-w-lg">
                                    <HeroCarousel
                                        slides={slides}
                                        alt={product.name}
                                        settings={carousel as CarouselSettings}
                                    />
                                </div>
                            </div>

                            {features.length > 0 && (
                                <div className="mt-16 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    {features.slice(0, 3).map((feature, i) => {
                                        const Icon =
                                            featureIcons[
                                                i % featureIcons.length
                                            ];

                                        return (
                                            <div
                                                key={i}
                                                className="group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-b from-white/[0.11] to-white/[0.03] p-6 backdrop-blur-md transition duration-300 hover:-translate-y-1.5 hover:border-amber-300/40 hover:shadow-[0_24px_60px_-20px_rgba(245,166,35,0.4)]"
                                            >
                                                <span className="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-amber-300/80 to-transparent" />
                                                <span className="pointer-events-none absolute -top-12 -right-12 size-36 rounded-full bg-amber-400/15 blur-2xl transition duration-300 group-hover:bg-amber-400/35" />
                                                <span className="absolute top-3 right-5 text-6xl leading-none font-black text-white/[0.06] select-none">
                                                    {String(i + 1).padStart(
                                                        2,
                                                        '0',
                                                    )}
                                                </span>
                                                <span className="relative flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-200 via-amber-300 to-amber-500 text-slate-900 shadow-[0_12px_30px_-8px_rgba(245,166,35,0.75)] ring-4 ring-amber-300/15 transition duration-300 group-hover:scale-110 group-hover:-rotate-6">
                                                    <Icon className="size-6" />
                                                </span>
                                                <p className="relative mt-5 text-lg font-extrabold tracking-tight">
                                                    {feature.title}
                                                </p>
                                                {feature.text && (
                                                    <p className="relative mt-1.5 text-sm leading-relaxed text-white/65">
                                                        {feature.text}
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {/* Product description: gallery + details + option picker */}
                {(sec.description?.enabled !== false ||
                    sec.gallery?.enabled !== false) && (
                    <section id="details" className="scroll-mt-20 px-4 py-20">
                        <div className="mx-auto grid max-w-6xl grid-cols-1 items-start gap-12 lg:grid-cols-2">
                            {/* Gallery */}
                            {sec.gallery?.enabled !== false &&
                                orderedMedia.length > 0 && (
                                    <div className="space-y-4 lg:sticky lg:top-24">
                                        <div
                                            className={cn(
                                                cardBase,
                                                'overflow-hidden p-3',
                                            )}
                                        >
                                            {showVariantImage &&
                                            focusedVariant?.image ? (
                                                <img
                                                    src={focusedVariant.image}
                                                    alt={`${product.name} – ${variantName(focusedVariant)}`}
                                                    className="aspect-square w-full rounded-2xl bg-slate-50 object-contain"
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
                                                    className="aspect-square w-full rounded-2xl bg-slate-900 object-contain"
                                                />
                                            ) : (
                                                <img
                                                    src={
                                                        orderedMedia[
                                                            activeMediaIdx
                                                        ]?.path
                                                    }
                                                    alt={product.name}
                                                    className="aspect-square w-full rounded-2xl bg-slate-50 object-contain"
                                                />
                                            )}
                                        </div>
                                        {orderedMedia.length > 1 && (
                                            <div className="flex gap-2.5 overflow-x-auto pb-1">
                                                {orderedMedia.map((item, i) => (
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
                                                        aria-label={`Show ${item.type} ${i + 1}`}
                                                        className={cn(
                                                            'relative size-[4.5rem] shrink-0 overflow-hidden rounded-2xl border-2 bg-white transition',
                                                            i ===
                                                                activeMediaIdx &&
                                                                !showVariantImage
                                                                ? 'border-[#c8102e] shadow-md'
                                                                : 'border-transparent opacity-70 hover:opacity-100',
                                                        )}
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
                                                                <div className="absolute inset-0 flex items-center justify-center bg-black/35">
                                                                    <Play className="size-5 fill-white text-white" />
                                                                </div>
                                                            </>
                                                        ) : (
                                                            <img
                                                                src={item.path}
                                                                alt=""
                                                                className="size-full object-cover"
                                                            />
                                                        )}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}

                            {/* Details */}
                            <div
                                className={cn(
                                    'space-y-6',
                                    (sec.gallery?.enabled === false ||
                                        orderedMedia.length === 0) &&
                                        'lg:col-span-2 lg:mx-auto lg:max-w-3xl',
                                )}
                            >
                                {description.enabled !== false && (
                                    <div className="space-y-4">
                                        {description.badge && (
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-[#c8102e] px-3.5 py-1 text-xs font-extrabold tracking-wide text-white uppercase">
                                                <Sparkles className="size-3.5" />
                                                {description.badge}
                                            </span>
                                        )}
                                        <h2 className="text-3xl font-extrabold tracking-tight whitespace-pre-line text-slate-900 sm:text-4xl">
                                            {description.title}
                                        </h2>
                                        <div
                                            className="prose max-w-none text-slate-600 prose-slate"
                                            dangerouslySetInnerHTML={{
                                                __html:
                                                    (description.use_product_description
                                                        ? product.description
                                                        : description.custom_content) ??
                                                    '',
                                            }}
                                        />
                                    </div>
                                )}

                                {hasVariants && options.length > 0 && (
                                    <div
                                        className={cn(
                                            cardBase,
                                            'space-y-4 p-5 sm:p-6',
                                        )}
                                    >
                                        <div>
                                            <h3 className="text-lg font-extrabold text-slate-900">
                                                {description.selection_title ||
                                                    'Choose your set'}
                                            </h3>
                                            {description.selection_hint && (
                                                <p className="mt-0.5 text-sm text-slate-500">
                                                    {description.selection_hint}
                                                </p>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                            {options.map((option) => (
                                                <OptionCard
                                                    key={option.key}
                                                    option={option}
                                                    mode="pick"
                                                    qty={
                                                        selection[option.key] ??
                                                        0
                                                    }
                                                    ceiling={ceilingFor(option)}
                                                    onChange={(next) =>
                                                        setOptionQty(
                                                            option,
                                                            next,
                                                        )
                                                    }
                                                    onFocus={() =>
                                                        focusVariant(
                                                            option.kind ===
                                                                'variant'
                                                                ? (option.id as number)
                                                                : null,
                                                        )
                                                    }
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <GlowButton
                                    href="#order"
                                    size="lg"
                                    className="w-full sm:w-auto"
                                >
                                    {selectedUnits > 0
                                        ? `Continue to order (${selectedUnits})`
                                        : ctaText}
                                </GlowButton>
                            </div>
                        </div>
                    </section>
                )}

                {/* Delivery info */}
                {delivery.enabled !== false && (
                    <section
                        id="delivery"
                        className="relative scroll-mt-20 overflow-hidden bg-gradient-to-br from-[#0b1020] via-[#1a1a2e] to-[#5a0a18] px-4 py-20 text-white"
                    >
                        <div className="pointer-events-none absolute -top-24 right-0 size-96 rounded-full bg-amber-400/15 blur-[100px]" />
                        <div className="relative mx-auto max-w-3xl text-center">
                            <span className="mx-auto mb-5 flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-300 to-amber-500 text-slate-900">
                                <Truck className="size-7" />
                            </span>
                            <h2 className="text-3xl font-extrabold tracking-tight whitespace-pre-line sm:text-4xl">
                                {delivery.title}
                            </h2>
                            {delivery.text && (
                                <p className="mx-auto mt-4 max-w-2xl text-base whitespace-pre-line text-white/75">
                                    {delivery.text}
                                </p>
                            )}
                            {(delivery.areas ?? []).filter(Boolean).length >
                                0 && (
                                <div className="mt-6 flex flex-wrap justify-center gap-2">
                                    {(delivery.areas as string[])
                                        .filter(Boolean)
                                        .map((area, i) => (
                                            <span
                                                key={i}
                                                className="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/[0.07] px-4 py-1.5 text-sm font-medium backdrop-blur"
                                            >
                                                <MapPin className="size-3.5 text-amber-300" />
                                                {area}
                                            </span>
                                        ))}
                                </div>
                            )}
                            <div className="mx-auto mt-7 inline-flex flex-col gap-1 rounded-2xl border border-white/10 bg-white/[0.06] px-6 py-4 text-sm">
                                {product.free_delivery ? (
                                    <span className="font-bold text-emerald-300">
                                        ✓ Free delivery on this product
                                    </span>
                                ) : (
                                    <>
                                        <span>
                                            Inside Dhaka City:{' '}
                                            <strong className="text-amber-300">
                                                {formatCurrency(
                                                    insideDhakaRate,
                                                )}
                                            </strong>
                                        </span>
                                        <span>
                                            Outside Dhaka:{' '}
                                            <strong className="text-amber-300">
                                                {formatCurrency(
                                                    outsideDhakaRate,
                                                )}
                                            </strong>
                                        </span>
                                    </>
                                )}
                            </div>
                            <div className="mt-9 flex flex-col items-center gap-6">
                                <GlowButton href="#order" size="xl">
                                    {delivery.cta_text || ctaText}
                                </GlowButton>
                                {contactPhone && (
                                    <div className="space-y-1">
                                        {delivery.contact_label && (
                                            <p className="text-sm text-white/60">
                                                {delivery.contact_label}
                                            </p>
                                        )}
                                        <a
                                            href={`tel:${contactPhone}`}
                                            className="inline-flex items-center gap-3 text-2xl font-extrabold text-amber-300 transition hover:text-amber-200 sm:text-3xl"
                                        >
                                            <Phone className="size-6 sm:size-7" />
                                            {contactPhone}
                                        </a>
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {/* Product selection + order */}
                <OrderSection
                    product={product}
                    sec={sec}
                    slug={page.slug}
                    maxQuantity={maxQuantity}
                    options={options}
                    selection={selection}
                    ceilingFor={ceilingFor}
                    setOptionQty={setOptionQty}
                    onFocusVariant={focusVariant}
                    bkashNumber={bkashNumber}
                    nagadNumber={nagadNumber}
                    orderToken={orderToken}
                    pendingPayment={pendingPayment}
                />

                {/* Testimonials: written reviews, or a carousel of pictures */}
                {sec.testimonials?.enabled &&
                    (sec.testimonials.mode === 'image'
                        ? (sec.testimonials.images?.length ?? 0) > 0
                        : sec.testimonials.items?.length > 0) && (
                        <section className="bg-slate-50 px-4 py-12">
                            <div
                                className={`mx-auto ${sec.testimonials.mode === 'image' ? 'max-w-6xl' : 'max-w-4xl'}`}
                            >
                                <h2 className="mb-8 text-center text-2xl font-bold text-slate-900">
                                    {sec.testimonials.title}
                                </h2>
                                {sec.testimonials.mode === 'image' ? (
                                    <TestimonialCarousel
                                        images={sec.testimonials.images}
                                        label={
                                            sec.testimonials.title ||
                                            'Customer testimonials'
                                        }
                                    />
                                ) : (
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
                                                        {/* Rich text from the CMS (purified on save); older plain-text reviews render the same. */}
                                                        <div
                                                            className="prose prose-sm mt-2 max-w-none [overflow-wrap:anywhere] text-slate-600 prose-p:my-1.5 prose-p:leading-relaxed prose-ol:my-1.5 prose-ul:my-1.5"
                                                            dangerouslySetInnerHTML={{
                                                                __html:
                                                                    item.text ??
                                                                    '',
                                                            }}
                                                        />
                                                        <p className="mt-3 text-xs font-semibold text-slate-800">
                                                            — {item.name}
                                                        </p>
                                                    </div>
                                                ),
                                        )}
                                    </div>
                                )}
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
                                    <SiteLogo
                                        src={siteLogo}
                                        name={siteName}
                                        plate="always"
                                        tone="onDark"
                                        className="h-12"
                                    />
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

            {/* Mobile: sticky order bar */}
            <div
                className={cn(
                    'fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-[#0b1020]/95 p-3 backdrop-blur-xl transition-transform duration-300 lg:hidden',
                    pastHero && !orderInView
                        ? 'translate-y-0'
                        : 'translate-y-full',
                )}
            >
                <GlowButton href="#order" size="lg" className="w-full">
                    {ctaText}
                </GlowButton>
            </div>

            <SocialChatWidget />
        </>
    );
}
