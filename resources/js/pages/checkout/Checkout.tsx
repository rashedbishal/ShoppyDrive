import React, { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Lock,
    MapPin,
    ShoppingBag,
    ShieldCheck,
    AlertCircle,
    Edit2,
    Plus,
    Tag,
    X,
    CreditCard,
    LogIn,
} from 'lucide-react';

import CheckoutLayout from '@/components/layout/CheckoutLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import type { Address, CartItem, SharedPageProps } from '@/types';

// ── Bangladesh geography ──────────────────────────────────────────────────────
const BANGLADESH_DISTRICTS = [
    'Bagerhat',
    'Bandarban',
    'Barguna',
    'Barishal',
    'Bhola',
    'Bogura',
    'Brahmanbaria',
    'Chandpur',
    'Chapai Nawabganj',
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
    'Jhalokati',
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

const DHAKA_CITY_OPTIONS = [
    'Inside Dhaka City Corporation',
    'Outside Dhaka City Corporation',
];

const DISTRICT_OPTIONS = [
    { label: 'Select district…', value: '', disabled: true },
    ...BANGLADESH_DISTRICTS.map((d) => ({ label: d, value: d })),
];

// ── Sub-components ─────────────────────────────────────────────────────────────
const Section = ({
    step,
    title,
    children,
}: {
    step: number;
    title: string;
    children: React.ReactNode;
}) => (
    <div className="space-y-4 rounded-2xl border border-[var(--color-border)] bg-white p-6">
        <div className="flex items-center gap-2.5">
            <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[var(--color-text-primary)] text-xs font-bold text-white">
                {step}
            </span>
            <h3 className="text-xs font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                {title}
            </h3>
        </div>
        {children}
    </div>
);

// ── Component ─────────────────────────────────────────────────────────────────
interface CheckoutProps {
    savedAddresses: Address[];
    buyNowItem?: CartItem | null;
}

type AddressMode = 'default' | 'select' | 'new';

export default function Checkout({
    savedAddresses = [],
    buyNowItem = null,
}: CheckoutProps): React.JSX.Element {
    const { auth, flash, settings } = usePage<SharedPageProps>().props;
    const { items: cartItems, getSubtotal, getDiscount } = useCartStore();

    // Reaching this page at all as a guest already means "Allow Guest
    // Checkout" is on (the server redirects to login otherwise) — email
    // becomes optional for them, phone stays required either way.
    const isGuest = !auth.user;

    // Buy Now bypasses the real cart entirely — checking out a single item
    // this way must never touch what's actually in the customer's cart.
    const items = buyNowItem ? [buyNowItem] : cartItems;

    const defaultAddress = savedAddresses.find((a) => a.is_default) ?? null;
    const hasDefault = defaultAddress !== null;
    const hasAddresses = savedAddresses.length > 0;

    // 'default' = show saved default, 'select' = pick from list, 'new' = enter form
    const [addressMode, setAddressMode] = useState<AddressMode>(
        hasDefault ? 'default' : 'new',
    );
    const [selectedAddr, setSelectedAddr] = useState<Address | null>(
        defaultAddress,
    );

    const form = useForm({
        name: auth.user?.name ?? defaultAddress?.name ?? '',
        email: auth.user?.email ?? '',
        phone: (auth.user as any)?.phone ?? defaultAddress?.phone ?? '',
        // address
        address_id: defaultAddress?.id ?? (null as number | null),
        address_line1: '',
        address_line2: '',
        city: '',
        state: '',
        postal_code: '',
        country: 'Bangladesh',
        save_address: !hasAddresses,
        // payment
        payment_method: 'cod' as 'cod' | 'mobile_banking',
    });

    const isDhaka = form.data.state === 'Dhaka';

    const subtotal = buyNowItem ? buyNowItem.subtotal : getSubtotal();
    const discount = buyNowItem ? 0 : getDiscount();

    // ── Shipping cost calculation ─────────────────────────────────────────────
    function calcShipping(district: string, city: string): number | null {
        if (!district || !city) return null; // address incomplete

        // Free delivery threshold
        const threshold = settings.free_delivery_threshold ?? 0;
        if (threshold > 0 && subtotal >= threshold) return 0;

        // Per-product free delivery: if ALL items qualify → free
        if (items.length > 0) {
            const allFree = items.every((item) => {
                const p = item.product;
                if (!p.free_delivery) return false;
                if (p.free_delivery_min_qty != null)
                    return item.quantity >= p.free_delivery_min_qty;
                return true;
            });
            if (allFree) return 0;
        }

        return district === 'Dhaka' && city === 'Inside Dhaka City Corporation'
            ? 60
            : 120;
    }

    const effectiveDistrict = selectedAddr?.state ?? form.data.state;
    const effectiveCity = selectedAddr?.city ?? form.data.city;
    const shippingCost = calcShipping(effectiveDistrict, effectiveCity);

    const handleDistrictChange = (value: string) => {
        form.setData({ ...form.data, state: value, city: '' });
    };

    const pickSavedAddress = (addr: Address) => {
        setSelectedAddr(addr);
        form.setData({ ...form.data, address_id: addr.id });
        setAddressMode('default');
    };

    const switchToNewForm = () => {
        setSelectedAddr(null);
        form.setData({ ...form.data, address_id: null });
        setAddressMode('new');
    };

    const [submitting, setSubmitting] = useState(false);

    // ── Coupon state ──────────────────────────────────────────────────────────
    const [couponInput, setCouponInput] = useState('');
    const [appliedCoupon, setAppliedCoupon] = useState<{
        id: number;
        code: string;
        type: string;
        value: number;
    } | null>(null);
    const [couponError, setCouponError] = useState('');
    const [applyingCoupon, setApplyingCoupon] = useState(false);

    const handleApplyCoupon = async () => {
        const code = couponInput.trim().toUpperCase();
        if (!code) return;
        setApplyingCoupon(true);
        setCouponError('');
        try {
            const res = await fetch(route('coupon.validate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ code, subtotal }),
            });
            const data = await res.json();
            if (res.ok) {
                setAppliedCoupon(data.coupon);
                setCouponInput('');
            } else {
                setCouponError(data.error || 'Invalid coupon.');
            }
        } catch {
            setCouponError('Could not apply coupon. Please try again.');
        } finally {
            setApplyingCoupon(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);

        router.post(
            route('checkout.store'),
            {
                ...form.data,
                coupon_code: appliedCoupon?.code ?? null,
                is_buy_now: buyNowItem !== null,
                items: items.map((item) => ({
                    product_id: item.product.id,
                    variant_id: item.variant?.id ?? null,
                    quantity: item.quantity,
                })),
            },
            {
                onError: (errors) => {
                    form.setError(errors as any);
                    setSubmitting(false);
                },
                onSuccess: () => setSubmitting(false),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const couponDiscount = (() => {
        if (!appliedCoupon) return 0;
        if (appliedCoupon.type === 'percent')
            return Math.min((subtotal * appliedCoupon.value) / 100, subtotal);
        return Math.min(appliedCoupon.value, subtotal);
    })();

    const total = Math.max(
        0,
        subtotal - discount - couponDiscount + (shippingCost ?? 0),
    );

    const placeOrderLabel = submitting ? (
        'Processing…'
    ) : form.data.payment_method === 'mobile_banking' ? (
        <>
            <CreditCard size={16} className="mr-2" />
            Continue to Payment
        </>
    ) : (
        <>
            <Lock size={16} className="mr-2" />
            Place Order
        </>
    );

    return (
        <CheckoutLayout>
            <Head title="Checkout" />

            <div className="mb-6 flex items-center justify-between">
                <Link
                    href={route('cart.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--color-text-secondary)] transition-colors hover:text-[var(--color-primary)]"
                >
                    <ArrowLeft size={15} />
                    Back to cart
                </Link>
                <div className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-[var(--color-text-muted)] uppercase">
                    <Lock size={13} />
                    Secure checkout
                </div>
            </div>

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-[var(--color-text-primary)] sm:text-3xl">
                    Checkout
                </h1>
                <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                    You're almost done — review your details and place your
                    order.
                </p>
            </div>

            {flash?.error && (
                <div className="mb-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <AlertCircle size={16} className="shrink-0" />
                    {flash.error}
                </div>
            )}

            {flash?.info && (
                <div className="mb-4 flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                    <AlertCircle size={16} className="shrink-0" />
                    {flash.info}
                </div>
            )}

            {isGuest && (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-4 py-3 text-sm">
                    <span className="text-[var(--color-text-secondary)]">
                        Checking out as a guest — only your phone number is
                        required.
                    </span>
                    <Link
                        href={route('login')}
                        className="flex items-center gap-1.5 font-semibold text-[var(--color-primary)] hover:underline"
                    >
                        <LogIn size={14} />
                        Log in for faster checkout
                    </Link>
                </div>
            )}

            <form
                id="checkout-form"
                onSubmit={handleSubmit}
                className="pb-24 lg:pb-0"
            >
                <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                    {/* ── LEFT: Info + Address + Payment ─────────────────── */}
                    <div className="space-y-4 lg:col-span-7">
                        {/* ── Customer Info ── */}
                        <Section step={1} title="Customer Details">
                            <div className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="name">
                                            Full Name *
                                        </Label>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Your full name"
                                            className="h-11 rounded-xl"
                                            required
                                        />
                                        {form.errors.name && (
                                            <p className="text-xs text-red-500">
                                                {form.errors.name}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="email">
                                            Email {isGuest ? '' : '*'}
                                            {isGuest && (
                                                <span className="font-normal text-[var(--color-text-muted)]">
                                                    {' '}
                                                    (optional)
                                                </span>
                                            )}
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={form.data.email}
                                            onChange={(e) =>
                                                form.setData(
                                                    'email',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="example@mail.com"
                                            className="h-11 rounded-xl"
                                            required={!isGuest}
                                        />
                                        {form.errors.email && (
                                            <p className="text-xs text-red-500">
                                                {form.errors.email}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="max-w-xs space-y-1.5">
                                    <Label htmlFor="phone">Phone *</Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        inputMode="numeric"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value
                                                    .replace(/\D/g, '')
                                                    .slice(0, 11),
                                            )
                                        }
                                        placeholder="01712345678"
                                        className="h-11 rounded-xl"
                                        maxLength={11}
                                        pattern="01[3-9][0-9]{8}"
                                        title="Enter an 11-digit Bangladeshi phone number, e.g. 01712345678"
                                        required
                                    />
                                    {form.errors.phone ? (
                                        <p className="text-xs text-red-500">
                                            {form.errors.phone}
                                        </p>
                                    ) : (
                                        <p className="text-xs text-[var(--color-text-muted)]">
                                            11 digits, starting with 01 (e.g.
                                            01712345678)
                                        </p>
                                    )}
                                </div>
                            </div>
                        </Section>

                        {/* ── Shipping Address ── */}
                        <Section step={2} title="Shipping Address">
                            {/* MODE: show the selected / default address as info card */}
                            {addressMode === 'default' && selectedAddr && (
                                <div className="space-y-3">
                                    <div className="flex items-start gap-4 rounded-xl border-2 border-[var(--color-primary)] bg-[var(--color-primary-light)] p-4">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary)] text-white">
                                            <MapPin size={17} />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="mb-0.5 flex items-center gap-2">
                                                <span className="text-sm font-bold text-[var(--color-text-primary)]">
                                                    {selectedAddr.label ||
                                                        'Address'}
                                                </span>
                                                {selectedAddr.is_default && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-[10px]"
                                                    >
                                                        Default
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-sm leading-relaxed text-[var(--color-text-secondary)]">
                                                {selectedAddr.address_line1}
                                                {selectedAddr.address_line2
                                                    ? `, ${selectedAddr.address_line2}`
                                                    : ''}
                                                <br />
                                                {selectedAddr.city},{' '}
                                                {selectedAddr.state}
                                                {selectedAddr.postal_code
                                                    ? ` – ${selectedAddr.postal_code}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setAddressMode('select')
                                            }
                                            className="shrink-0 rounded-lg p-1.5 text-[var(--color-text-muted)] transition-all hover:bg-white hover:text-[var(--color-primary)]"
                                            title="Change address"
                                        >
                                            <Edit2 size={15} />
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* MODE: pick from saved addresses list */}
                            {addressMode === 'select' && (
                                <div className="space-y-3">
                                    <p className="text-xs font-semibold text-[var(--color-text-muted)]">
                                        Choose an address
                                    </p>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        {savedAddresses.map((addr) => (
                                            <button
                                                key={addr.id}
                                                type="button"
                                                onClick={() =>
                                                    pickSavedAddress(addr)
                                                }
                                                className={`rounded-xl border-2 p-4 text-left transition-all ${
                                                    selectedAddr?.id === addr.id
                                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)]'
                                                        : 'border-[var(--color-border)] hover:border-[var(--color-text-primary)]'
                                                }`}
                                            >
                                                <div className="mb-1 flex items-start justify-between">
                                                    <span className="text-sm font-bold text-[var(--color-text-primary)]">
                                                        {addr.label ||
                                                            'Address'}
                                                    </span>
                                                    {addr.is_default && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-[10px]"
                                                        >
                                                            Default
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="line-clamp-2 text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                                    {addr.address_line1},{' '}
                                                    {addr.city}, {addr.state}
                                                </p>
                                            </button>
                                        ))}
                                        <button
                                            type="button"
                                            onClick={switchToNewForm}
                                            className="flex min-h-[80px] flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-[var(--color-border)] p-4 text-[var(--color-text-muted)] transition-all hover:border-[var(--color-text-primary)] hover:text-[var(--color-text-primary)]"
                                        >
                                            <Plus size={18} />
                                            <span className="text-sm font-bold">
                                                New Address
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* MODE: enter a new address */}
                            {addressMode === 'new' && (
                                <div className="space-y-4">
                                    {/* If they have saved addresses, offer to use one */}
                                    {hasAddresses && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setAddressMode('select')
                                            }
                                            className="text-sm font-semibold text-[var(--color-primary)] hover:underline"
                                        >
                                            ← Use a saved address
                                        </button>
                                    )}

                                    <div className="space-y-1.5">
                                        <Label htmlFor="address_line1">
                                            Address Line 1 *
                                        </Label>
                                        <Input
                                            id="address_line1"
                                            value={form.data.address_line1}
                                            onChange={(e) =>
                                                form.setData(
                                                    'address_line1',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Street address, house number"
                                            className="h-11 rounded-xl"
                                            required
                                        />
                                        {form.errors.address_line1 && (
                                            <p className="text-xs text-red-500">
                                                {form.errors.address_line1}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="state">
                                                District *
                                            </Label>
                                            <Select
                                                id="state"
                                                value={form.data.state}
                                                onChange={(e) =>
                                                    handleDistrictChange(
                                                        e.target.value,
                                                    )
                                                }
                                                options={DISTRICT_OPTIONS}
                                                className="h-11 rounded-xl"
                                                required
                                                error={form.errors.state}
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="city">City *</Label>
                                            {isDhaka ? (
                                                <Select
                                                    id="city"
                                                    value={form.data.city}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'city',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-11 rounded-xl"
                                                    required
                                                    error={form.errors.city}
                                                >
                                                    <option value="" disabled>
                                                        Select area…
                                                    </option>
                                                    {DHAKA_CITY_OPTIONS.map(
                                                        (opt) => (
                                                            <option
                                                                key={opt}
                                                                value={opt}
                                                            >
                                                                {opt}
                                                            </option>
                                                        ),
                                                    )}
                                                </Select>
                                            ) : (
                                                <Input
                                                    id="city"
                                                    value={form.data.city}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'city',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="e.g. Sylhet"
                                                    className="h-11 rounded-xl"
                                                    disabled={!form.data.state}
                                                    required
                                                />
                                            )}
                                            {form.errors.city && (
                                                <p className="text-xs text-red-500">
                                                    {form.errors.city}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="max-w-xs space-y-1.5">
                                        <Label htmlFor="postal_code">
                                            Postal Code
                                        </Label>
                                        <Input
                                            id="postal_code"
                                            value={form.data.postal_code}
                                            onChange={(e) =>
                                                form.setData(
                                                    'postal_code',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="e.g. 1212"
                                            className="h-11 rounded-xl"
                                        />
                                    </div>

                                    {auth.user &&
                                        (!hasAddresses ? (
                                            <p className="flex items-center gap-1.5 text-xs text-[var(--color-text-muted)]">
                                                <Check
                                                    size={13}
                                                    className="text-[var(--color-success)]"
                                                />
                                                This address will be saved to
                                                your address book.
                                            </p>
                                        ) : (
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="save_address"
                                                    checked={
                                                        form.data.save_address
                                                    }
                                                    onCheckedChange={(v) =>
                                                        form.setData(
                                                            'save_address',
                                                            !!v,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor="save_address"
                                                    className="cursor-pointer text-sm"
                                                >
                                                    Save for future orders
                                                </Label>
                                            </div>
                                        ))}
                                </div>
                            )}
                        </Section>

                        {/* ── Coupon ── */}
                        <Section step={3} title="Coupon Code">
                            {appliedCoupon ? (
                                <div className="flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <Tag
                                            size={15}
                                            className="text-green-600"
                                        />
                                        <span className="text-sm font-bold text-green-700">
                                            {appliedCoupon.code}
                                        </span>
                                        <span className="text-xs text-green-600">
                                            —{' '}
                                            {appliedCoupon.type === 'percent'
                                                ? `${appliedCoupon.value}% off`
                                                : `৳${appliedCoupon.value} off`}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setAppliedCoupon(null);
                                            setCouponError('');
                                        }}
                                        className="text-green-500 transition-colors hover:text-red-500"
                                        title="Remove coupon"
                                    >
                                        <X size={16} />
                                    </button>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <div className="flex gap-2">
                                        <Input
                                            value={couponInput}
                                            onChange={(e) => {
                                                setCouponInput(
                                                    e.target.value.toUpperCase(),
                                                );
                                                setCouponError('');
                                            }}
                                            onKeyDown={(e) =>
                                                e.key === 'Enter' &&
                                                (e.preventDefault(),
                                                handleApplyCoupon())
                                            }
                                            placeholder="Enter coupon code"
                                            className="h-11 rounded-xl uppercase"
                                        />
                                        <Button
                                            type="button"
                                            onClick={handleApplyCoupon}
                                            disabled={
                                                applyingCoupon ||
                                                !couponInput.trim()
                                            }
                                            className="h-11 shrink-0 rounded-xl px-5"
                                        >
                                            {applyingCoupon
                                                ? 'Applying…'
                                                : 'Apply'}
                                        </Button>
                                    </div>
                                    {couponError && (
                                        <p className="flex items-center gap-1.5 text-xs text-red-500">
                                            <AlertCircle size={12} />
                                            {couponError}
                                        </p>
                                    )}
                                </div>
                            )}
                        </Section>

                        {/* ── Payment ── */}
                        <Section step={4} title="Payment Method">
                            <div className="space-y-3">
                                {/* COD */}
                                <button
                                    type="button"
                                    onClick={() =>
                                        form.setData('payment_method', 'cod')
                                    }
                                    className={`flex w-full items-center gap-4 rounded-xl border-2 p-4 text-left transition-all ${
                                        form.data.payment_method === 'cod'
                                            ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)]'
                                            : 'border-[var(--color-border)] bg-white hover:border-slate-300'
                                    }`}
                                >
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[var(--color-border)] bg-white text-[var(--color-primary)] shadow-sm">
                                        <ShoppingBag size={20} />
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)]">
                                            Cash on Delivery
                                        </p>
                                        <p className="text-xs text-[var(--color-text-muted)]">
                                            Pay with cash when your order
                                            arrives
                                        </p>
                                    </div>
                                    <div
                                        className={`flex h-5 w-5 items-center justify-center rounded-full transition-colors ${
                                            form.data.payment_method === 'cod'
                                                ? 'bg-[var(--color-primary)]'
                                                : 'border-2 border-slate-300'
                                        }`}
                                    >
                                        {form.data.payment_method === 'cod' && (
                                            <Check
                                                size={11}
                                                className="text-white"
                                            />
                                        )}
                                    </div>
                                </button>

                                {/* Bkash / Nagad */}
                                <button
                                    type="button"
                                    onClick={() =>
                                        form.setData(
                                            'payment_method',
                                            'mobile_banking',
                                        )
                                    }
                                    className={`flex w-full items-center gap-4 rounded-xl border-2 p-4 text-left transition-all ${
                                        form.data.payment_method ===
                                        'mobile_banking'
                                            ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)]'
                                            : 'border-[var(--color-border)] bg-white hover:border-slate-300'
                                    }`}
                                >
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[var(--color-border)] bg-white shadow-sm">
                                        <CreditCard
                                            size={20}
                                            className="text-pink-600"
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)]">
                                            Bkash / Nagad
                                            <span className="ml-2 inline-flex items-center rounded-full bg-pink-100 px-2 py-0.5 text-[10px] font-semibold text-pink-700">
                                                Send Money
                                            </span>
                                        </p>
                                        <p className="text-xs text-[var(--color-text-muted)]">
                                            Send money & enter your Transaction
                                            ID
                                        </p>
                                    </div>
                                    <div
                                        className={`flex h-5 w-5 items-center justify-center rounded-full transition-colors ${
                                            form.data.payment_method ===
                                            'mobile_banking'
                                                ? 'bg-[var(--color-primary)]'
                                                : 'border-2 border-slate-300'
                                        }`}
                                    >
                                        {form.data.payment_method ===
                                            'mobile_banking' && (
                                            <Check
                                                size={11}
                                                className="text-white"
                                            />
                                        )}
                                    </div>
                                </button>
                            </div>

                            {form.data.payment_method === 'cod' && (
                                <div className="flex gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-3">
                                    <AlertCircle
                                        size={15}
                                        className="mt-0.5 shrink-0 text-[var(--color-text-muted)]"
                                    />
                                    <p className="text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                        Please keep the exact change ready.
                                        Delivery time may vary by location.
                                    </p>
                                </div>
                            )}

                            {form.data.payment_method === 'mobile_banking' && (
                                <div className="flex gap-3 rounded-xl border border-pink-200 bg-pink-50 p-3">
                                    <ShieldCheck
                                        size={15}
                                        className="mt-0.5 shrink-0 text-pink-600"
                                    />
                                    <p className="text-xs leading-relaxed text-pink-700">
                                        After placing the order you'll see our
                                        Bkash/Nagad number and the exact amount
                                        to send. Enter your TrxID to confirm.
                                    </p>
                                </div>
                            )}
                        </Section>
                    </div>

                    {/* ── RIGHT: Order Summary ────────────────────────────── */}
                    <div className="lg:col-span-5">
                        <div className="sticky top-24 space-y-5 rounded-2xl border border-[var(--color-border)] bg-white p-6">
                            <h3 className="text-base font-bold text-[var(--color-text-primary)]">
                                Order Summary
                            </h3>

                            {/* Items */}
                            <div className="max-h-64 space-y-3 overflow-y-auto pr-1">
                                {items.length === 0 ? (
                                    <p className="py-4 text-center text-sm text-[var(--color-text-muted)]">
                                        Your cart is empty.
                                    </p>
                                ) : (
                                    items.map((item) => (
                                        <div
                                            key={item.item_key}
                                            className="flex gap-3"
                                        >
                                            <div className="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                                                <img
                                                    src={
                                                        item.variant?.image ||
                                                        item.product
                                                            .primary_image_url ||
                                                        item.product.images[0]
                                                            ?.path
                                                    }
                                                    alt={item.product.name}
                                                    className="h-full w-full object-cover"
                                                />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold text-[var(--color-text-primary)]">
                                                    {item.product.name}
                                                </p>
                                                <p className="text-xs text-[var(--color-text-muted)]">
                                                    Qty: {item.quantity}
                                                </p>
                                                <p className="text-sm font-bold text-[var(--color-primary)]">
                                                    {formatCurrency(
                                                        item.subtotal,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            {/* Totals */}
                            <div className="space-y-2.5 border-t border-[var(--color-border)] pt-4">
                                <div className="flex justify-between text-sm">
                                    <span className="text-[var(--color-text-secondary)]">
                                        Subtotal
                                    </span>
                                    <span className="font-semibold">
                                        {formatCurrency(subtotal)}
                                    </span>
                                </div>
                                {discount > 0 && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-[var(--color-text-secondary)]">
                                            Discount
                                        </span>
                                        <span className="font-semibold text-[var(--color-success)]">
                                            −{formatCurrency(discount)}
                                        </span>
                                    </div>
                                )}
                                {couponDiscount > 0 && appliedCoupon && (
                                    <div className="flex justify-between text-sm">
                                        <span className="flex items-center gap-1 text-[var(--color-text-secondary)]">
                                            <Tag size={12} />
                                            Coupon ({appliedCoupon.code})
                                        </span>
                                        <span className="font-semibold text-[var(--color-success)]">
                                            −{formatCurrency(couponDiscount)}
                                        </span>
                                    </div>
                                )}
                                <div className="flex items-start justify-between gap-2 text-sm">
                                    <div>
                                        <span className="text-[var(--color-text-secondary)]">
                                            Shipping
                                        </span>
                                        {shippingCost === 0 && (
                                            <p className="mt-0.5 text-[11px] text-[var(--color-success)]">
                                                All items qualify for free
                                                delivery
                                            </p>
                                        )}
                                        {shippingCost !== null &&
                                            shippingCost > 0 && (
                                                <p className="mt-0.5 text-[11px] text-[var(--color-text-muted)]">
                                                    1+ item requires delivery
                                                    charge
                                                </p>
                                            )}
                                    </div>
                                    <span className="shrink-0 font-semibold">
                                        {shippingCost === null ? (
                                            <span className="text-xs font-normal text-[var(--color-text-muted)]">
                                                Enter address
                                            </span>
                                        ) : shippingCost === 0 ? (
                                            <span className="text-[var(--color-success)]">
                                                Free
                                            </span>
                                        ) : (
                                            formatCurrency(shippingCost)
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between border-t border-[var(--color-border)] pt-3">
                                    <span className="font-bold text-[var(--color-text-primary)]">
                                        Total
                                    </span>
                                    <span className="text-xl font-bold text-[var(--color-primary)]">
                                        {formatCurrency(total)}
                                    </span>
                                </div>
                            </div>

                            {/* Place Order (hidden on mobile — the sticky bottom bar takes over there) */}
                            <Button
                                type="submit"
                                disabled={submitting}
                                size="lg"
                                className="h-13 w-full max-lg:hidden rounded-xl text-base font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                            >
                                {placeOrderLabel}
                            </Button>

                            {/* Trust badge */}
                            <div className="flex items-center justify-center gap-2 text-xs text-[var(--color-text-muted)]">
                                <ShieldCheck size={14} />
                                <span>Secure & encrypted checkout</span>
                            </div>

                            {form.errors.items && (
                                <p className="text-center text-sm text-red-500">
                                    {form.errors.items}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </form>

            {/* Mobile sticky Place Order bar — the in-card button above is hidden below lg */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--color-border)] bg-white/95 p-4 backdrop-blur-sm lg:hidden">
                <div className="flex items-center gap-4">
                    <div className="min-w-0 flex-1">
                        <p className="text-[11px] font-semibold tracking-wide text-[var(--color-text-muted)] uppercase">
                            Total
                        </p>
                        <p className="truncate text-lg font-bold text-[var(--color-primary)]">
                            {formatCurrency(total)}
                        </p>
                    </div>
                    <Button
                        type="submit"
                        form="checkout-form"
                        disabled={submitting}
                        size="lg"
                        className="h-12 shrink-0 rounded-xl px-6 text-base font-bold"
                    >
                        {placeOrderLabel}
                    </Button>
                </div>
            </div>
        </CheckoutLayout>
    );
}
