import { router, useForm, usePage } from '@inertiajs/react';
import {
    Package,
    Search,
    ShoppingBag,
    Trash2,
    UserRound,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    DHAKA_CITY_OPTIONS,
    DISTRICT_OPTIONS,
} from '@/lib/bangladesh-geography';
import { formatCurrency } from '@/lib/utils';
import { email, maxLength, pattern, required, validate } from '@/lib/validate';
import type { SharedPageProps } from '@/types';

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

type CustomerAddress = {
    address_line1: string;
    address_line2: string | null;
    city: string;
    state: string;
    postal_code: string | null;
};

type SearchCustomer = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    address: CustomerAddress | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function variantLabel(attributes: Record<string, string> | null): string {
    if (!attributes) {
        return '';
    }

    return Object.entries(attributes)
        .map(([k, v]) => `${k}: ${v}`)
        .join(', ');
}

const emptyFormData = {
    customer_id: null as number | null,
    name: '',
    phone: '',
    email: '',
    address_line1: '',
    address_line2: '',
    city: '',
    state: '',
    postal_code: '',
    payment_method: 'cod' as 'cod' | 'mobile_banking',
    shipping_cost: 0,
    discount: 0,
    notes: '',
};

export function CreateOrderModal({ open, onOpenChange }: Props) {
    const { settings } = usePage<SharedPageProps>().props;
    const form = useForm(emptyFormData);
    const [submitting, setSubmitting] = useState(false);
    const [lines, setLines] = useState<OrderLine[]>([]);
    const [shippingTouched, setShippingTouched] = useState(false);

    const isDhaka = form.data.state === 'Dhaka';
    const subtotal = lines.reduce(
        (sum, line) => sum + line.price * line.quantity,
        0,
    );
    const total = Math.max(
        0,
        subtotal -
            (Number(form.data.discount) || 0) +
            (Number(form.data.shipping_cost) || 0),
    );

    // ── Auto-calculated shipping, same formula the storefront uses ───────────
    useEffect(() => {
        if (shippingTouched) {
            return;
        }

        const threshold = settings.free_delivery_threshold ?? 0;

        if (threshold > 0 && subtotal >= threshold) {
            form.setData('shipping_cost', 0);

            return;
        }

        if (!form.data.state || !form.data.city) {
            return;
        }

        const rate =
            form.data.state === 'Dhaka' &&
            form.data.city === 'Inside Dhaka City Corporation'
                ? (settings.shipping_rate_inside_dhaka ?? 60)
                : (settings.shipping_rate_outside_dhaka ?? 120);

        form.setData('shipping_cost', rate);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.state, form.data.city, subtotal, shippingTouched]);

    const resetAll = () => {
        form.reset();
        form.clearErrors();
        setLines([]);
        setShippingTouched(false);
        setSelectedCustomer(null);
        setCustomerQuery('');
        setCustomerResults([]);
        setProductQuery('');
        setProductResults([]);
        setPendingVariantFor(null);
    };

    const handleOpenChange = (next: boolean) => {
        if (!next) {
            resetAll();
        }

        onOpenChange(next);
    };

    // ── Customer search ───────────────────────────────────────────────────────
    const [selectedCustomer, setSelectedCustomer] =
        useState<SearchCustomer | null>(null);
    const [customerQuery, setCustomerQuery] = useState('');
    const [customerResults, setCustomerResults] = useState<SearchCustomer[]>(
        [],
    );
    const [searchingCustomers, setSearchingCustomers] = useState(false);
    const customerSearchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                customerSearchRef.current &&
                !customerSearchRef.current.contains(e.target as Node)
            ) {
                setCustomerResults([]);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (!customerQuery.trim()) {
            setCustomerResults([]);

            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearchingCustomers(true);

            try {
                const res = await fetch(
                    route('admin.orders.customers.search', {
                        q: customerQuery,
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
                    setCustomerResults(await res.json());
                }
            } catch {
                // aborted or network error
            } finally {
                setSearchingCustomers(false);
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [customerQuery]);

    const selectCustomer = (customer: SearchCustomer) => {
        setSelectedCustomer(customer);
        form.setData({
            ...form.data,
            customer_id: customer.id,
            name: customer.name,
            phone: customer.phone ?? form.data.phone,
            email: customer.email ?? '',
            address_line1:
                customer.address?.address_line1 ?? form.data.address_line1,
            address_line2: customer.address?.address_line2 ?? '',
            city: customer.address?.city ?? form.data.city,
            state: customer.address?.state ?? form.data.state,
            postal_code: customer.address?.postal_code ?? '',
        });
        setCustomerQuery('');
        setCustomerResults([]);
    };

    const clearCustomer = () => {
        setSelectedCustomer(null);
        form.setData('customer_id', null);
    };

    // ── Product search ────────────────────────────────────────────────────────
    const [productQuery, setProductQuery] = useState('');
    const [productResults, setProductResults] = useState<SearchProduct[]>([]);
    const [searchingProducts, setSearchingProducts] = useState(false);
    const [pendingVariantFor, setPendingVariantFor] =
        useState<SearchProduct | null>(null);
    const productSearchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                productSearchRef.current &&
                !productSearchRef.current.contains(e.target as Node)
            ) {
                setProductResults([]);
                setPendingVariantFor(null);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (!productQuery.trim()) {
            setProductResults([]);

            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearchingProducts(true);

            try {
                const res = await fetch(
                    route('admin.orders.incomplete.products.search', {
                        q: productQuery,
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
                    setProductResults(await res.json());
                }
            } catch {
                // aborted or network error
            } finally {
                setSearchingProducts(false);
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [productQuery]);

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
        setProductQuery('');
        setProductResults([]);
        setPendingVariantFor(null);
    };

    const handleSelectProduct = (product: SearchProduct) => {
        if (product.variants.length > 0) {
            setPendingVariantFor(product);

            return;
        }

        addLine(product, null);
    };

    const updateLine = (index: number, patch: Partial<OrderLine>) => {
        setLines((prev) =>
            prev.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );
    };

    const removeLine = (index: number) => {
        setLines((prev) => prev.filter((_, i) => i !== index));
    };

    // ── Submit ─────────────────────────────────────────────────────────────────
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (submitting) {
            return;
        }

        form.clearErrors();
        const clientErrors: Record<string, string> = validate(form.data, {
            name: [required(), maxLength(255)],
            phone: [
                required(),
                pattern(
                    /^01[3-9][0-9]{8}$/,
                    'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).',
                ),
            ],
            email: [email()],
            address_line1: [required(), maxLength(255)],
            address_line2: [maxLength(255)],
            city: [required(), maxLength(255)],
            state: [required(), maxLength(255)],
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
            route('admin.orders.store'),
            {
                ...form.data,
                items: lines.map((line) => ({
                    product_id: line.product_id,
                    variant_id: line.variant_id,
                    quantity: line.quantity,
                    price: line.price,
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
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent
                appearance="premium"
                className="flex max-h-[90vh] flex-col overflow-y-auto sm:max-w-3xl"
            >
                <DialogHeader>
                    <DialogTitle>Create Order</DialogTitle>
                    <DialogDescription>
                        Place an order on behalf of a customer who called or
                        messaged in.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="space-y-2">
                        <Label>Customer</Label>
                        {selectedCustomer ? (
                            <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#c8102e]/10 text-[#c8102e]">
                                    <UserRound className="size-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">
                                        {selectedCustomer.name}
                                    </p>
                                    <p className="truncate text-xs text-slate-500">
                                        {selectedCustomer.email}
                                        {selectedCustomer.phone
                                            ? ` · ${selectedCustomer.phone}`
                                            : ''}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={clearCustomer}
                                    className="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700"
                                    title="Remove — place as a guest instead"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>
                        ) : (
                            <div ref={customerSearchRef} className="relative">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        type="text"
                                        placeholder="Search an existing customer by name, email or phone (optional)…"
                                        value={customerQuery}
                                        onChange={(e) =>
                                            setCustomerQuery(e.target.value)
                                        }
                                        className="h-10 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                                    />
                                </div>
                                {customerResults.length > 0 && (
                                    <div className="absolute z-10 mt-1 max-h-56 w-full space-y-0.5 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                        {customerResults.map((customer) => (
                                            <button
                                                key={customer.id}
                                                type="button"
                                                onClick={() =>
                                                    selectCustomer(customer)
                                                }
                                                className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-slate-50"
                                            >
                                                <span className="truncate">
                                                    {customer.name}
                                                </span>
                                                <span className="shrink-0 text-xs text-slate-400">
                                                    {customer.phone ??
                                                        customer.email}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                                {customerQuery.trim() !== '' &&
                                    !searchingCustomers &&
                                    customerResults.length === 0 && (
                                        <div className="absolute z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white p-3 text-center text-xs text-slate-400 shadow-lg">
                                            No matching customer — fill in the
                                            fields below for a guest order.
                                        </div>
                                    )}
                            </div>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="co-name">Full Name *</Label>
                            <Input
                                id="co-name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                error={form.errors.name}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="co-phone">Phone *</Label>
                            <Input
                                id="co-phone"
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
                                error={form.errors.phone}
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="co-email">Email</Label>
                        <Input
                            id="co-email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            error={form.errors.email}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="co-address1">Address Line 1 *</Label>
                        <Input
                            id="co-address1"
                            value={form.data.address_line1}
                            onChange={(e) =>
                                form.setData('address_line1', e.target.value)
                            }
                            placeholder="Street address, house number"
                            error={form.errors.address_line1}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="co-address2">Address Line 2</Label>
                        <Input
                            id="co-address2"
                            value={form.data.address_line2}
                            onChange={(e) =>
                                form.setData('address_line2', e.target.value)
                            }
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="co-state">District *</Label>
                            <Select
                                id="co-state"
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
                            <Label htmlFor="co-city">City *</Label>
                            {isDhaka ? (
                                <Select
                                    id="co-city"
                                    value={form.data.city}
                                    onChange={(e) =>
                                        form.setData('city', e.target.value)
                                    }
                                    error={form.errors.city}
                                >
                                    <option value="" disabled>
                                        Select area…
                                    </option>
                                    {DHAKA_CITY_OPTIONS.map((opt) => (
                                        <option key={opt} value={opt}>
                                            {opt}
                                        </option>
                                    ))}
                                </Select>
                            ) : (
                                <Input
                                    id="co-city"
                                    value={form.data.city}
                                    onChange={(e) =>
                                        form.setData('city', e.target.value)
                                    }
                                    disabled={!form.data.state}
                                    error={form.errors.city}
                                />
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="co-postal">Postal Code</Label>
                            <Input
                                id="co-postal"
                                value={form.data.postal_code}
                                onChange={(e) =>
                                    form.setData('postal_code', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="co-payment">Payment *</Label>
                            <Select
                                id="co-payment"
                                value={form.data.payment_method}
                                onChange={(e) =>
                                    form.setData(
                                        'payment_method',
                                        e.target.value as
                                            | 'cod'
                                            | 'mobile_banking',
                                    )
                                }
                                options={[
                                    {
                                        label: 'Cash on Delivery',
                                        value: 'cod',
                                    },
                                    {
                                        label: 'Mobile Banking — already received',
                                        value: 'mobile_banking',
                                    },
                                ]}
                                error={form.errors.payment_method}
                            />
                        </div>
                    </div>

                    <div className="space-y-2 border-t border-slate-100 pt-4">
                        <Label>Products *</Label>

                        <div ref={productSearchRef} className="relative">
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Search products to add…"
                                    value={productQuery}
                                    onChange={(e) =>
                                        setProductQuery(e.target.value)
                                    }
                                    className="h-10 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                                />
                            </div>

                            {pendingVariantFor && (
                                <div className="absolute z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                    <p className="mb-1.5 px-1 text-xs font-semibold text-slate-500">
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
                                                        ) || variant.sku}
                                                    </span>
                                                    <span className="text-xs text-slate-400">
                                                        {formatCurrency(
                                                            variant.price,
                                                        )}{' '}
                                                        · stock{' '}
                                                        {variant.stock_quantity}
                                                    </span>
                                                </button>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}

                            {!pendingVariantFor &&
                                productResults.length > 0 && (
                                    <div className="absolute z-10 mt-1 max-h-64 w-full space-y-0.5 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                        {productResults.map((product) => (
                                            <button
                                                key={product.id}
                                                type="button"
                                                onClick={() =>
                                                    handleSelectProduct(product)
                                                }
                                                className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-slate-50"
                                            >
                                                <span className="truncate">
                                                    {product.name}
                                                    {product.variants.length >
                                                        0 && (
                                                        <span className="ml-1.5 text-xs text-slate-400">
                                                            (
                                                            {
                                                                product.variants
                                                                    .length
                                                            }{' '}
                                                            variants)
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="shrink-0 text-xs text-slate-400">
                                                    {formatCurrency(
                                                        product.price,
                                                    )}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}

                            {!pendingVariantFor &&
                                productQuery.trim() !== '' &&
                                !searchingProducts &&
                                productResults.length === 0 && (
                                    <div className="absolute z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white p-3 text-center text-xs text-slate-400 shadow-lg">
                                        No products found.
                                    </div>
                                )}
                        </div>

                        {(form.errors as Record<string, string>).items && (
                            <p className="text-xs text-red-500">
                                {(form.errors as Record<string, string>).items}
                            </p>
                        )}

                        <div className="space-y-2">
                            {lines.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 py-8 text-center">
                                    <ShoppingBag className="size-6 text-slate-300" />
                                    <p className="text-xs text-slate-400">
                                        No products selected yet.
                                    </p>
                                </div>
                            ) : (
                                lines.map((line, index) => (
                                    <div
                                        key={`${line.product_id}-${line.variant_id}`}
                                        className="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                                    >
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                            <Package className="size-4" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-slate-800">
                                                {line.product_name}
                                            </p>
                                            {line.variant_attributes && (
                                                <p className="truncate text-xs text-slate-400">
                                                    {variantLabel(
                                                        line.variant_attributes,
                                                    )}
                                                </p>
                                            )}
                                            <input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                value={line.price}
                                                onChange={(e) =>
                                                    updateLine(index, {
                                                        price:
                                                            parseFloat(
                                                                e.target.value,
                                                            ) || 0,
                                                    })
                                                }
                                                className="mt-1 h-7 w-24 rounded border border-slate-200 px-1.5 text-xs focus:border-[#c8102e] focus:outline-none"
                                            />
                                        </div>
                                        <QuantityStepper
                                            value={line.quantity}
                                            onChange={(val) =>
                                                updateLine(index, {
                                                    quantity: val,
                                                })
                                            }
                                            className="h-9 scale-90"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => removeLine(index)}
                                            className="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="co-shipping">
                                Shipping Cost (৳)
                            </Label>
                            <Input
                                id="co-shipping"
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.shipping_cost}
                                onChange={(e) => {
                                    setShippingTouched(true);
                                    form.setData(
                                        'shipping_cost',
                                        parseFloat(e.target.value) || 0,
                                    );
                                }}
                                error={form.errors.shipping_cost}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="co-discount">Discount (৳)</Label>
                            <Input
                                id="co-discount"
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.discount}
                                onChange={(e) =>
                                    form.setData(
                                        'discount',
                                        parseFloat(e.target.value) || 0,
                                    )
                                }
                                error={form.errors.discount}
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="co-notes">Order Notes</Label>
                        <Textarea
                            id="co-notes"
                            rows={2}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            error={form.errors.notes}
                        />
                    </div>

                    <div className="space-y-1 rounded-xl bg-slate-50 p-3 text-sm">
                        <div className="flex justify-between text-slate-600">
                            <span>Subtotal</span>
                            <span>{formatCurrency(subtotal)}</span>
                        </div>
                        {Number(form.data.discount) > 0 && (
                            <div className="flex justify-between text-emerald-600">
                                <span>Discount</span>
                                <span>
                                    -
                                    {formatCurrency(Number(form.data.discount))}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between text-slate-600">
                            <span>Shipping</span>
                            <span>
                                {formatCurrency(
                                    Number(form.data.shipping_cost) || 0,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between border-t border-slate-200 pt-1 font-bold text-slate-900">
                            <span>Total</span>
                            <span>{formatCurrency(total)}</span>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={submitting}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {submitting ? 'Creating…' : 'Create Order'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
