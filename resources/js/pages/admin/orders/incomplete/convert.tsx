import { Head, Link, router, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import {
    AlertTriangle,
    ArrowLeft,
    Package,
    Search,
    ShoppingBag,
    Trash2,
    Truck,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminFade, AdminReveal } from '@/components/admin/admin-motion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { Select } from '@/components/ui/select';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import {
    DHAKA_CITY_OPTIONS,
    DISTRICT_OPTIONS,
} from '@/lib/bangladesh-geography';
import { formatCurrency } from '@/lib/utils';
import { maxLength, pattern, required, validate } from '@/lib/validate';

type IncompleteOrderItem = {
    product_id: number;
    variant_id: number | null;
    quantity: number;
    available: boolean;
    product_name: string;
    sku: string | null;
    price: number;
    image: string | null;
    variant_attributes: Record<string, string> | null;
};

type SearchVariant = {
    id: number;
    sku: string;
    price: number;
    stock_quantity: number;
    attributes: Record<string, string> | null;
};

type SearchProduct = {
    id: number;
    name: string;
    sku: string;
    price: number;
    image: string | null;
    variants: SearchVariant[];
};

type OrderLine = {
    product_id: number;
    variant_id: number | null;
    product_name: string;
    sku: string | null;
    price: number;
    quantity: number;
    variant_attributes: Record<string, string> | null;
};

type Props = {
    incompleteOrder: {
        id: number;
        name: string | null;
        phone: string;
    };
    items: IncompleteOrderItem[];
};

function variantLabel(attributes: Record<string, string> | null): string {
    if (!attributes) {
        return '';
    }

    return Object.entries(attributes)
        .map(([k, v]) => `${k}: ${v}`)
        .join(', ');
}

export default function ConvertIncompleteOrder({
    incompleteOrder,
    items: initialItems,
}: Props) {
    const listUrl = useAdminListUrl('admin.orders.incomplete.index');
    const unavailableItems = initialItems.filter((item) => !item.available);

    const [lines, setLines] = useState<OrderLine[]>(
        initialItems
            .filter((item) => item.available)
            .map((item) => ({
                product_id: item.product_id,
                variant_id: item.variant_id,
                product_name: item.product_name,
                sku: item.sku,
                price: item.price,
                quantity: item.quantity,
                variant_attributes: item.variant_attributes,
            })),
    );

    const [submitting, setSubmitting] = useState(false);

    const form = useForm({
        name: incompleteOrder.name ?? '',
        phone: incompleteOrder.phone,
        address_line1: '',
        address_line2: '',
        city: '',
        state: '',
        postal_code: '',
    });

    const isDhaka = form.data.state === 'Dhaka';

    // ── Add product search ──────────────────────────────────────────────────
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchProduct[]>([]);
    const [searching, setSearching] = useState(false);
    const [pendingVariantFor, setPendingVariantFor] =
        useState<SearchProduct | null>(null);
    const searchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                searchRef.current &&
                !searchRef.current.contains(e.target as Node)
            ) {
                setResults([]);
                setPendingVariantFor(null);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (!query.trim()) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- clears results at once when the query is emptied; fetched results are set asynchronously
            setResults([]);

            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearching(true);

            try {
                const res = await fetch(
                    route('admin.orders.incomplete.products.search', {
                        q: query,
                    }),
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (res.ok) {
                    setResults(await res.json());
                }
            } catch {
                // aborted or network error
            } finally {
                setSearching(false);
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [query]);

    const addLine = (product: SearchProduct, variant: SearchVariant | null) => {
        setLines((prev) => {
            const existing = prev.find(
                (l) =>
                    l.product_id === product.id &&
                    l.variant_id === (variant?.id ?? null),
            );

            if (existing) {
                return prev.map((l) =>
                    l === existing ? { ...l, quantity: l.quantity + 1 } : l,
                );
            }

            return [
                ...prev,
                {
                    product_id: product.id,
                    variant_id: variant?.id ?? null,
                    product_name: product.name,
                    sku: variant?.sku ?? product.sku,
                    price: variant?.price ?? product.price,
                    quantity: 1,
                    variant_attributes: variant?.attributes ?? null,
                },
            ];
        });
        setQuery('');
        setResults([]);
        setPendingVariantFor(null);
    };

    const handleSelectProduct = (product: SearchProduct) => {
        if (product.variants.length > 0) {
            setPendingVariantFor(product);

            return;
        }

        addLine(product, null);
    };

    const updateQuantity = (index: number, quantity: number) => {
        setLines((prev) =>
            prev.map((l, i) => (i === index ? { ...l, quantity } : l)),
        );
    };

    const removeLine = (index: number) => {
        setLines((prev) => prev.filter((_, i) => i !== index));
    };

    const subtotal = lines.reduce(
        (sum, line) => sum + line.price * line.quantity,
        0,
    );

    // BIZ-017: line prices (flash sales included) and the delivery charge
    // come from the same server calculation the order will use, so staff
    // see exactly what will be charged before creating it.
    const [shippingCost, setShippingCost] = useState<number | null>(null);
    const shownShippingCost = lines.length === 0 ? null : shippingCost;
    const pricedLines = JSON.stringify(
        lines.map((line) => [line.product_id, line.variant_id, line.quantity]),
    );

    useEffect(() => {
        if (lines.length === 0) {
            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            try {
                const res = await fetch(
                    route('admin.orders.incomplete.pricingPreview', {
                        state: form.data.state,
                        city: form.data.city,
                        items: lines.map((line) => ({
                            product_id: line.product_id,
                            variant_id: line.variant_id,
                            quantity: line.quantity,
                        })),
                    }),
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!res.ok) {
                    return;
                }

                const pricing = (await res.json()) as {
                    prices: number[];
                    shipping_cost: number;
                };

                setLines((prev) =>
                    prev.map((line, index) => ({
                        ...line,
                        price: pricing.prices[index] ?? line.price,
                    })),
                );
                setShippingCost(
                    form.data.state && form.data.city
                        ? pricing.shipping_cost
                        : null,
                );
            } catch {
                // Aborted or offline: the server recalculates on submit anyway.
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pricedLines, form.data.state, form.data.city]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (submitting) {
            return;
        }

        form.clearErrors();
        const clientErrors: Record<string, string> = validate(form.data, {
            name: [required(), maxLength(100)],
            phone: [
                required(),
                pattern(
                    /^01[3-9][0-9]{8}$/,
                    'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).',
                ),
            ],
            address_line1: [required(), maxLength(200)],
            address_line2: [maxLength(200)],
            city: [required(), maxLength(100)],
            state: [required(), maxLength(100)],
            postal_code: [maxLength(20)],
        });

        if (lines.length === 0) {
            clientErrors.items = 'Add at least one product to the order.';
        }

        if (Object.keys(clientErrors).length > 0) {
            form.setError(clientErrors);

            return;
        }

        setSubmitting(true);
        router.post(
            route('admin.orders.incomplete.placeOrder', incompleteOrder.id),
            {
                ...form.data,
                items: lines.map((line) => ({
                    product_id: line.product_id,
                    variant_id: line.variant_id,
                    quantity: line.quantity,
                })),
            },
            {
                onError: (errors) => {
                    form.setError(errors as any);
                    setSubmitting(false);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <>
            <Head title="Convert Incomplete Order — Admin" />
            <div className="space-y-5">
                <div className="flex items-center gap-4">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="rounded-full"
                    >
                        <Link href={listUrl}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Convert to Order
                        </h1>
                        <p className="text-sm text-slate-600">
                            Creates a real, cash-on-delivery order for{' '}
                            {incompleteOrder.phone}.
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="grid grid-cols-1 items-start gap-5 lg:grid-cols-12"
                >
                    <div className="space-y-5 lg:col-span-7">
                        <Card>
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-base font-semibold">
                                    Customer & Shipping
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-3">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="name">
                                            Full Name *
                                        </Label>
                                        <Input
                                            maxLength={100}
                                            showCount
                                            id="name"
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            error={form.errors.name}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="phone">Phone *</Label>
                                        <Input
                                            id="phone"
                                            value={form.data.phone}
                                            onChange={(e) =>
                                                form.setData(
                                                    'phone',
                                                    e.target.value
                                                        .replace(/\D/g, '')
                                                        .slice(0, 11),
                                                )
                                            }
                                            maxLength={11}
                                            showCount
                                            error={form.errors.phone}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="address_line1">
                                        Address Line 1 *
                                    </Label>
                                    <Input
                                        maxLength={200}
                                        showCount
                                        id="address_line1"
                                        value={form.data.address_line1}
                                        onChange={(e) =>
                                            form.setData(
                                                'address_line1',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Street address, house number"
                                        error={form.errors.address_line1}
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="address_line2">
                                        Address Line 2
                                    </Label>
                                    <Input
                                        maxLength={200}
                                        showCount
                                        id="address_line2"
                                        value={form.data.address_line2}
                                        onChange={(e) =>
                                            form.setData(
                                                'address_line2',
                                                e.target.value,
                                            )
                                        }
                                    />
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
                                                form.setData({
                                                    ...form.data,
                                                    state: e.target.value,
                                                    city: '',
                                                })
                                            }
                                            options={DISTRICT_OPTIONS}
                                            error={form.errors.state}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="city">City *</Label>
                                        <AnimatePresence
                                            initial={false}
                                            mode="wait"
                                        >
                                            {isDhaka ? (
                                                <AdminFade key="city-a">
                                                    <Select
                                                        id="city"
                                                        value={form.data.city}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'city',
                                                                e.target.value,
                                                            )
                                                        }
                                                        error={form.errors.city}
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
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
                                                </AdminFade>
                                            ) : (
                                                <AdminFade key="city-b">
                                                    <Input
                                                        maxLength={100}
                                                        showCount
                                                        id="city"
                                                        value={form.data.city}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'city',
                                                                e.target.value,
                                                            )
                                                        }
                                                        disabled={
                                                            !form.data.state
                                                        }
                                                        error={form.errors.city}
                                                    />
                                                </AdminFade>
                                            )}
                                        </AnimatePresence>
                                    </div>
                                </div>

                                <div className="max-w-xs space-y-1.5">
                                    <Label htmlFor="postal_code">
                                        Postal Code
                                    </Label>
                                    <Input
                                        maxLength={20}
                                        showCount
                                        id="postal_code"
                                        value={form.data.postal_code}
                                        onChange={(e) =>
                                            form.setData(
                                                'postal_code',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    <Truck className="size-4 shrink-0" />
                                    Payment method: this order will be created
                                    as <strong>Cash on Delivery</strong>.
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-5 lg:col-span-5">
                        <Card>
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-base font-semibold">
                                    Products
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-3">
                                {unavailableItems.length > 0 && (
                                    <div className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                        <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                        <span>
                                            {unavailableItems.length} item(s)
                                            from this attempt are no longer
                                            available and were left out:{' '}
                                            {unavailableItems
                                                .map((i) => i.product_name)
                                                .join(', ')}
                                            .
                                        </span>
                                    </div>
                                )}

                                <div ref={searchRef} className="relative">
                                    <div className="relative">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            maxLength={100}
                                            type="text"
                                            placeholder="Search products to add…"
                                            value={query}
                                            onChange={(e) =>
                                                setQuery(e.target.value)
                                            }
                                            className="h-10 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                                        />
                                    </div>

                                    <AnimatePresence initial={false}>
                                        {pendingVariantFor && (
                                            <AdminFade
                                                key="variant-picker"
                                                className="absolute z-10 mt-1 w-full origin-top rounded-lg border border-slate-200 bg-white p-2 shadow-lg"
                                            >
                                                <p className="mb-1.5 px-1 text-xs font-semibold text-slate-600">
                                                    Choose a variant of{' '}
                                                    {pendingVariantFor.name}
                                                </p>
                                                <div className="max-h-56 space-y-0.5 overflow-y-auto">
                                                    {pendingVariantFor.variants.map(
                                                        (variant) => (
                                                            <button
                                                                key={variant.id}
                                                                type="button"
                                                                onClick={() =>
                                                                    addLine(
                                                                        pendingVariantFor,
                                                                        variant,
                                                                    )
                                                                }
                                                                className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-slate-50"
                                                            >
                                                                <span>
                                                                    {variantLabel(
                                                                        variant.attributes,
                                                                    ) ||
                                                                        variant.sku}
                                                                </span>
                                                                <span className="text-xs text-slate-600">
                                                                    {formatCurrency(
                                                                        variant.price,
                                                                    )}{' '}
                                                                    · stock{' '}
                                                                    {
                                                                        variant.stock_quantity
                                                                    }
                                                                </span>
                                                            </button>
                                                        ),
                                                    )}
                                                </div>
                                            </AdminFade>
                                        )}
                                    </AnimatePresence>

                                    {!pendingVariantFor &&
                                        results.length > 0 && (
                                            <div className="absolute z-10 mt-1 max-h-64 w-full space-y-0.5 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                                {results.map((product) => (
                                                    <button
                                                        key={product.id}
                                                        type="button"
                                                        onClick={() =>
                                                            handleSelectProduct(
                                                                product,
                                                            )
                                                        }
                                                        className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-slate-50"
                                                    >
                                                        <span className="truncate">
                                                            {product.name}
                                                            {product.variants
                                                                .length > 0 && (
                                                                <span className="ml-1.5 text-xs text-slate-600">
                                                                    (
                                                                    {
                                                                        product
                                                                            .variants
                                                                            .length
                                                                    }{' '}
                                                                    variants)
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="shrink-0 text-xs text-slate-600">
                                                            {formatCurrency(
                                                                product.price,
                                                            )}
                                                        </span>
                                                    </button>
                                                ))}
                                            </div>
                                        )}

                                    {!pendingVariantFor &&
                                        query.trim() !== '' &&
                                        !searching &&
                                        results.length === 0 && (
                                            <div className="absolute z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white p-3 text-center text-xs text-slate-600 shadow-lg">
                                                No products found.
                                            </div>
                                        )}
                                </div>

                                {(form.errors as any).items && (
                                    <p className="text-xs text-red-500">
                                        {(form.errors as any).items}
                                    </p>
                                )}

                                <div className="space-y-2">
                                    {lines.length === 0 ? (
                                        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 py-8 text-center">
                                            <ShoppingBag className="size-6 text-slate-300" />
                                            <p className="text-xs text-slate-600">
                                                No products selected yet.
                                            </p>
                                        </div>
                                    ) : (
                                        <AnimatePresence initial={false}>
                                            {lines.map((line, index) => (
                                                <AdminReveal
                                                    key={`${line.product_id}-${line.variant_id}`}
                                                >
                                                    <div className="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                                            <Package className="size-4" />
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-sm font-semibold text-slate-800">
                                                                {
                                                                    line.product_name
                                                                }
                                                            </p>
                                                            {line.variant_attributes && (
                                                                <p className="truncate text-xs text-slate-600">
                                                                    {variantLabel(
                                                                        line.variant_attributes,
                                                                    )}
                                                                </p>
                                                            )}
                                                            <p className="text-xs text-slate-600">
                                                                {formatCurrency(
                                                                    line.price,
                                                                )}{' '}
                                                                each
                                                            </p>
                                                        </div>
                                                        <QuantityStepper
                                                            value={
                                                                line.quantity
                                                            }
                                                            onChange={(val) =>
                                                                updateQuantity(
                                                                    index,
                                                                    val,
                                                                )
                                                            }
                                                            className="h-9 scale-90"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeLine(
                                                                    index,
                                                                )
                                                            }
                                                            className="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </div>
                                                </AdminReveal>
                                            ))}
                                        </AnimatePresence>
                                    )}
                                </div>

                                <div className="space-y-2 border-t border-slate-100 pt-4">
                                    <div className="flex items-center justify-between text-sm text-slate-600">
                                        <span>Subtotal</span>
                                        <span>{formatCurrency(subtotal)}</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm text-slate-600">
                                        <span>Delivery</span>
                                        <span>
                                            {shownShippingCost === null
                                                ? 'Enter district and city'
                                                : formatCurrency(
                                                      shownShippingCost,
                                                  )}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-semibold text-slate-600">
                                            Total (cash on delivery)
                                        </span>
                                        <span className="text-lg font-bold text-slate-900">
                                            {formatCurrency(
                                                subtotal +
                                                    (shownShippingCost ?? 0),
                                            )}
                                        </span>
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={submitting || lines.length === 0}
                                    className="w-full"
                                >
                                    {submitting
                                        ? 'Creating order…'
                                        : 'Create Order (COD)'}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </>
    );
}
