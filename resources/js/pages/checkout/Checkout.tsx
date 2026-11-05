import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Lock,
    MapPin,
    Package,
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
import React, { useEffect, useMemo, useState } from 'react';

import CheckoutLayout from '@/components/layout/CheckoutLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import {
    DHAKA_CITY_OPTIONS,
    DISTRICT_OPTIONS,
} from '@/lib/bangladesh-geography';
import { groupCartItems } from '@/lib/cart-bundles';
import { getCouponEligibleSubtotal, toCouponPreviewItems } from '@/lib/coupon';
import { getXsrfToken } from '@/lib/csrf';
import { formatCurrency, getPrimaryImagePath } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import type { Address, CartItem, Coupon, SharedPageProps } from '@/types';

// `crypto.randomUUID()` only exists in secure contexts (HTTPS, or HTTP on
// localhost) - browsing the dev server over a LAN IP doesn't qualify, so this
// falls back to `crypto.getRandomValues()` (available everywhere) and finally
// `Math.random()`. Not used for anything security-sensitive, just a unique
// per-visit tracking token.
function generateAttemptToken(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.getRandomValues === 'function'
    ) {
        const bytes = crypto.getRandomValues(new Uint8Array(16));

        return Array.from(bytes, (byte) =>
            byte.toString(16).padStart(2, '0'),
        ).join('');
    }

    return `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
}

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

// Pulled out of the main render tree and memoized: this is the one part of
// the page with real per-item DOM work (an <img> per cart line), and its
// inputs (items/totals/shipping/coupon/points) are untouched by typing into
// name/email/phone/address fields or switching payment method — the most
// frequent interactions on this page. Without this, every keystroke
// anywhere on the form re-rendered and re-reconciled the whole items list
// and every totals row for no reason. `submitting`/`paymentMethod` are
// passed as primitives (not the pre-built `placeOrderLabel` node) so a
// fresh-but-equal JSX element each parent render can't defeat memo.
interface OrderSummaryProps {
    items: CartItem[];
    subtotal: number;
    couponDiscount: number;
    appliedCouponCode: string | null;
    pointsDiscount: number;
    pointsToUse: number;
    referralDiscount: number;
    shippingCost: number | null;
    total: number;
    submitting: boolean;
    paymentMethod: 'cod' | 'mobile_banking';
    itemsError?: string;
}

const OrderSummary = React.memo(function OrderSummary({
    items,
    subtotal,
    couponDiscount,
    appliedCouponCode,
    pointsDiscount,
    pointsToUse,
    referralDiscount,
    shippingCost,
    total,
    submitting,
    paymentMethod,
    itemsError,
}: OrderSummaryProps) {
    const placeOrderLabel = submitting ? (
        'Processing…'
    ) : paymentMethod === 'mobile_banking' ? (
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
                        groupCartItems(items).map((group) =>
                            group.bundleId === null ? (
                                <OrderSummaryRow
                                    key={group.items[0].item_key}
                                    item={group.items[0]}
                                />
                            ) : (
                                <div
                                    key={`bundle-${group.bundleId}`}
                                    className="space-y-1.5 rounded-lg bg-[var(--color-surface-alt)]/50 p-2"
                                >
                                    <div className="flex items-center gap-1.5 px-1 text-[11px] font-bold tracking-widest text-[var(--color-primary)] uppercase">
                                        <Package size={12} />
                                        {group.bundleName} (bundle)
                                    </div>
                                    {group.items.map((item) => (
                                        <OrderSummaryRow
                                            key={item.item_key}
                                            item={item}
                                        />
                                    ))}
                                </div>
                            ),
                        )
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
                    {couponDiscount > 0 && appliedCouponCode && (
                        <div className="flex justify-between text-sm">
                            <span className="flex items-center gap-1 text-[var(--color-text-secondary)]">
                                <Tag size={12} />
                                Coupon ({appliedCouponCode})
                            </span>
                            <span className="font-semibold text-[var(--color-success)]">
                                −{formatCurrency(couponDiscount)}
                            </span>
                        </div>
                    )}
                    {pointsDiscount > 0 && (
                        <div className="flex justify-between text-sm">
                            <span className="text-[var(--color-text-secondary)]">
                                Points redeemed ({pointsToUse})
                            </span>
                            <span className="font-semibold text-[var(--color-success)]">
                                −{formatCurrency(pointsDiscount)}
                            </span>
                        </div>
                    )}
                    {referralDiscount > 0 && (
                        <div className="flex justify-between text-sm">
                            <span className="text-[var(--color-text-secondary)]">
                                Referral discount
                            </span>
                            <span className="font-semibold text-[var(--color-success)]">
                                −{formatCurrency(referralDiscount)}
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
                                    All items qualify for free delivery
                                </p>
                            )}
                            {shippingCost !== null && shippingCost > 0 && (
                                <p className="mt-0.5 text-[11px] text-[var(--color-text-muted)]">
                                    1+ item requires delivery charge
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
                    className="h-13 w-full rounded-xl text-base font-bold shadow-[var(--color-primary)]/20 shadow-lg max-lg:hidden"
                >
                    {placeOrderLabel}
                </Button>

                {/* Trust badge */}
                <div className="flex items-center justify-center gap-2 text-xs text-[var(--color-text-muted)]">
                    <ShieldCheck size={14} />
                    <span>Secure & encrypted checkout</span>
                </div>

                {itemsError && (
                    <p className="text-center text-sm text-red-500">
                        {itemsError}
                    </p>
                )}
            </div>
        </div>
    );
});

function OrderSummaryRow({ item }: { item: CartItem }): React.JSX.Element {
    return (
        <div className="flex gap-3">
            <div className="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                <img
                    src={
                        item.variant?.image ||
                        item.product.primary_image_url ||
                        getPrimaryImagePath(item.product)
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
                    {formatCurrency(item.subtotal)}
                </p>
            </div>
        </div>
    );
}

// ── Component ─────────────────────────────────────────────────────────────────
interface CheckoutProps {
    orderToken: string;
    savedAddresses: Address[];
    buyNowItem?: CartItem | null;
    referralDiscountEligible?: boolean;
    referralDiscountPreview?: number;
    pointsBalance?: number;
    pointsValue?: number;
    pointsRedeemMaxPercent?: number;
}

type AddressMode = 'default' | 'select' | 'new';

export default function Checkout({
    orderToken,
    savedAddresses = [],
    buyNowItem = null,
    referralDiscountEligible = false,
    referralDiscountPreview = 0,
    pointsBalance = 0,
    pointsValue = 0,
    pointsRedeemMaxPercent = 100,
}: CheckoutProps): React.JSX.Element {
    const { auth, flash, settings } = usePage<SharedPageProps>().props;
    const {
        items: cartItems,
        coupon: storeCoupon,
        applyCoupon: applyStoreCoupon,
    } = useCartStore();

    // Reaching this page at all as a guest already means "Allow Guest
    // Checkout" is on (the server redirects to login otherwise) — email
    // becomes optional for them, phone stays required either way.
    const isGuest = !auth.user;

    // A "Buy Now" item is shown merged with whatever's already in the cart
    // (summed into the same line if it's the same product/variant) — but
    // only for this page's display and for what gets submitted if the order
    // is actually placed. It's never written back into the cart store
    // itself, so abandoning checkout here leaves the real cart exactly as
    // it was. Memoized so the array reference is stable across unrelated
    // re-renders (e.g. typing in any field) — otherwise this is a brand new
    // array every render, which would defeat OrderSummary's memoization.
    const items = useMemo(() => {
        if (!buyNowItem) {
            return cartItems;
        }

        const existingIndex = cartItems.findIndex(
            (item) => item.item_key === buyNowItem.item_key,
        );

        if (existingIndex === -1) {
            return [...cartItems, buyNowItem];
        }

        return cartItems.map((item, index) => {
            if (index !== existingIndex) {
                return item;
            }

            const quantity = item.quantity + buyNowItem.quantity;
            const subtotal = item.price * quantity;

            return {
                ...item,
                quantity,
                subtotal,
                formatted_subtotal: formatCurrency(subtotal),
            };
        });
    }, [buyNowItem, cartItems]);

    // The server can't see a guest's cart, so an empty one is caught here. Only
    // on arrival: the cart also empties when an order is placed, and that visit
    // must carry on to the confirmation page.
    useEffect(() => {
        if (items.length === 0) {
            router.visit(route('home'), { replace: true });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

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
        // CHECKOUT-COD-002: fresh per page render (see CheckoutController::
        // index()), submitted back unchanged across retries of this same
        // render so the server can dedupe a double-click/resubmit.
        order_token: orderToken,
    });

    const isDhaka = form.data.state === 'Dhaka';

    // Based on the merged `items` (cart + Buy Now item, if any) so the total
    // always matches what's actually listed above, instead of just the cart
    // store's own subtotal, which wouldn't include a merged Buy Now item.
    const subtotal = items.reduce((sum, item) => sum + item.subtotal, 0);

    // ── Shipping cost calculation ─────────────────────────────────────────────
    function calcShipping(district: string, city: string): number | null {
        if (!district || !city) {
            return null;
        } // address incomplete

        // Free delivery threshold
        const threshold = settings.free_delivery_threshold ?? 0;

        if (threshold > 0 && subtotal >= threshold) {
            return 0;
        }

        // Per-product free delivery: if ALL items qualify → free
        if (items.length > 0) {
            const allFree = items.every((item) => {
                const p = item.product;

                if (!p.free_delivery) {
                    return false;
                }

                if (p.free_delivery_min_qty != null) {
                    return item.quantity >= p.free_delivery_min_qty;
                }

                return true;
            });

            if (allFree) {
                return 0;
            }
        }

        return district === 'Dhaka' && city === 'Inside Dhaka City Corporation'
            ? (settings.shipping_rate_inside_dhaka ?? 60)
            : (settings.shipping_rate_outside_dhaka ?? 120);
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

    // ── Incomplete-order tracking ───────────────────────────────────────────
    // Lets admins follow up on abandoned checkouts: once a valid phone number
    // is present with items to order, a debounced ping records this attempt.
    // One row per page visit (the token lives in sessionStorage for this tab,
    // not across visits), so repeated edits update the same row instead of
    // creating a new one on every keystroke.
    const [attemptToken] = useState<string>(() => {
        if (typeof window === 'undefined') {
            return '';
        }

        const existing = sessionStorage.getItem('shoppydrive.checkout_attempt');

        if (existing) {
            return existing;
        }

        const token = generateAttemptToken();
        sessionStorage.setItem('shoppydrive.checkout_attempt', token);

        return token;
    });

    const itemsSignature = items
        .map(
            (item) =>
                `${item.product.id}:${item.variant?.id ?? ''}:${item.quantity}`,
        )
        .join('|');

    useEffect(() => {
        if (!/^01[3-9]\d{8}$/.test(form.data.phone) || items.length === 0) {
            return;
        }

        const timeout = setTimeout(() => {
            fetch(route('checkout.track-incomplete'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    attempt_token: attemptToken,
                    phone: form.data.phone,
                    name: form.data.name || null,
                    items: items.map((item) => ({
                        product_id: item.product.id,
                        variant_id: item.variant?.id ?? null,
                        quantity: item.quantity,
                    })),
                }),
            }).catch(() => {});
        }, 1000);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.phone, form.data.name, itemsSignature]);

    // ── Coupon state ──────────────────────────────────────────────────────────
    const [couponInput, setCouponInput] = useState('');
    // BIZ-003: a coupon applied on the Cart page lives in the cart store, not
    // here — seeded once on mount (not buyNowItem, which never inherits a
    // cart-level coupon, same as before this fix) so what's displayed here
    // is what actually gets submitted below, instead of a second,
    // disconnected "applied on this page only" state that silently dropped
    // whatever the Cart page had already applied.
    const [appliedCoupon, setAppliedCoupon] = useState<Coupon | null>(() =>
        buyNowItem ? null : storeCoupon,
    );

    // Keeps the cart store in sync so navigating back to /cart (or a later
    // visit to checkout) reflects whatever was just applied/removed here.
    // Buy Now stays isolated, same as the seed above.
    const applyCoupon = (coupon: Coupon | null) => {
        setAppliedCoupon(coupon);

        if (!buyNowItem) {
            applyStoreCoupon(coupon);
        }
    };

    const [couponError, setCouponError] = useState('');
    const [applyingCoupon, setApplyingCoupon] = useState(false);

    const handleApplyCoupon = async () => {
        const code = couponInput.trim().toUpperCase();

        if (!code) {
            return;
        }

        setApplyingCoupon(true);
        setCouponError('');

        try {
            const res = await fetch(route('coupon.validate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    code,
                    subtotal,
                    phone: form.data.phone || undefined,
                    items: toCouponPreviewItems(items),
                }),
            });
            const data = await res.json();

            if (res.ok) {
                applyCoupon(data.coupon);
                setCouponInput('');
                // Only one discount mechanism applies per order — a coupon
                // always wins over a points redemption.
                setUsePoints(false);
            } else {
                setCouponError(data.error || 'Invalid coupon.');
            }
        } catch {
            setCouponError('Could not apply coupon. Please try again.');
        } finally {
            setApplyingCoupon(false);
        }
    };

    // ── Points redemption state ─────────────────────────────────────────────
    // Mutually exclusive with a coupon (coupon wins) and with the
    // auto-applied referral discount (points win if the customer opts in).
    const [usePoints, setUsePoints] = useState(false);
    const maxRedeemableAmount = subtotal * (pointsRedeemMaxPercent / 100);
    const maxRedeemablePoints =
        pointsValue > 0
            ? Math.min(
                  pointsBalance,
                  Math.floor(maxRedeemableAmount / pointsValue),
              )
            : 0;
    const [pointsToUse, setPointsToUse] = useState(maxRedeemablePoints);

    const handleTogglePoints = (checked: boolean) => {
        setUsePoints(checked);

        if (checked) {
            applyCoupon(null);
            setCouponError('');
            setPointsToUse(maxRedeemablePoints);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (submitting) {
            return;
        }

        setSubmitting(true);

        const redeemingPoints = !appliedCoupon && usePoints && pointsToUse > 0;

        router.post(
            route('checkout.store'),
            {
                ...form.data,
                coupon_code: appliedCoupon?.code ?? null,
                redeem_points: redeemingPoints,
                points_to_redeem: redeemingPoints ? pointsToUse : null,
                items: items.map((item) => ({
                    product_id: item.product.id,
                    variant_id: item.variant?.id ?? null,
                    quantity: item.quantity,
                    // BIZ-002: without these, the server's sync step always
                    // strips the bundle grouping this line was in, so
                    // BundleService re-detects "a price changed" on every
                    // attempt and the order can never actually be placed.
                    bundle_id: item.bundle_id ?? null,
                    bundle_unit_quantity: item.bundle_unit_quantity ?? null,
                    // BIZ-001: what this page is actually showing for the
                    // line, so the server can refuse instead of silently
                    // charging a price that changed after this page loaded.
                    price: item.price,
                })),
            },
            {
                onError: (errors) => {
                    form.setError(errors as any);
                    setSubmitting(false);
                },
                onSuccess: () => {
                    setSubmitting(false);
                    sessionStorage.removeItem('shoppydrive.checkout_attempt');
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const couponDiscount = (() => {
        if (!appliedCoupon) {
            return 0;
        }

        const eligibleSubtotal = getCouponEligibleSubtotal(
            appliedCoupon,
            items,
        );

        if (appliedCoupon.type === 'percent') {
            return Math.min(
                (eligibleSubtotal * appliedCoupon.value) / 100,
                eligibleSubtotal,
            );
        }

        return Math.min(appliedCoupon.value, eligibleSubtotal);
    })();

    // Discount priority mirrors the backend exactly: coupon > points > the
    // auto-applied referral discount. Only one ever contributes.
    const pointsDiscount =
        !appliedCoupon && usePoints && pointsToUse > 0
            ? Math.min(pointsToUse * pointsValue, subtotal)
            : 0;

    const referralDiscount =
        !appliedCoupon &&
        !(usePoints && pointsToUse > 0) &&
        referralDiscountEligible
            ? Math.min(referralDiscountPreview, subtotal)
            : 0;

    const total = Math.max(
        0,
        subtotal -
            couponDiscount -
            pointsDiscount -
            referralDiscount +
            (shippingCost ?? 0),
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
                                            applyCoupon(null);
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

                            {pointsBalance > 0 && pointsValue > 0 && (
                                <div
                                    className={`mt-3 rounded-xl border px-4 py-3 ${
                                        usePoints
                                            ? 'border-green-200 bg-green-50'
                                            : 'border-[var(--color-border)]'
                                    } ${appliedCoupon ? 'opacity-50' : ''}`}
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <Label
                                            htmlFor="use-points"
                                            className="flex items-center gap-2 text-sm font-medium"
                                        >
                                            <Checkbox
                                                id="use-points"
                                                checked={usePoints}
                                                disabled={
                                                    !!appliedCoupon ||
                                                    maxRedeemablePoints === 0
                                                }
                                                onCheckedChange={(checked) =>
                                                    handleTogglePoints(
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            Use my points ({pointsBalance}{' '}
                                            available)
                                        </Label>
                                        {usePoints && (
                                            <span className="text-sm font-semibold text-green-700">
                                                −
                                                {formatCurrency(pointsDiscount)}
                                            </span>
                                        )}
                                    </div>
                                    {usePoints && (
                                        <div className="mt-2 flex items-center gap-2">
                                            <Input
                                                type="number"
                                                min={1}
                                                max={maxRedeemablePoints}
                                                value={pointsToUse}
                                                onChange={(e) =>
                                                    setPointsToUse(
                                                        Math.max(
                                                            0,
                                                            Math.min(
                                                                maxRedeemablePoints,
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            ),
                                                        ),
                                                    )
                                                }
                                                className="h-9 w-28 rounded-lg"
                                            />
                                            <span className="text-xs text-[var(--color-text-secondary)]">
                                                points (worth{' '}
                                                {formatCurrency(pointsValue)}{' '}
                                                each)
                                            </span>
                                        </div>
                                    )}
                                    {appliedCoupon && (
                                        <p className="mt-1 text-xs text-[var(--color-text-secondary)]">
                                            Remove your coupon to use points
                                            instead.
                                        </p>
                                    )}
                                </div>
                            )}

                            {referralDiscountEligible &&
                                !appliedCoupon &&
                                !usePoints && (
                                    <p className="mt-3 flex items-center gap-1.5 text-xs text-green-700">
                                        <Tag size={12} />
                                        Your first-order referral discount (
                                        {formatCurrency(referralDiscount)}) will
                                        be applied automatically.
                                    </p>
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

                    <OrderSummary
                        items={items}
                        subtotal={subtotal}
                        couponDiscount={couponDiscount}
                        appliedCouponCode={appliedCoupon?.code ?? null}
                        pointsDiscount={pointsDiscount}
                        pointsToUse={pointsToUse}
                        referralDiscount={referralDiscount}
                        shippingCost={shippingCost}
                        total={total}
                        submitting={submitting}
                        paymentMethod={form.data.payment_method}
                        itemsError={
                            (form.errors as Partial<Record<string, string>>)
                                .items
                        }
                    />
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
