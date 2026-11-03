import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Package, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { maxLength, required, validate } from '@/lib/validate';

type SearchVariant = {
    id: number;
    color_value: { value: string } | null;
    size_value: { value: string } | null;
};

type SearchProduct = {
    id: number;
    name: string;
    price: number;
    sku: string | null;
    has_color_variant: boolean;
    has_size_variant: boolean;
    variants: SearchVariant[];
};

type SelectedItem = {
    uid: string;
    product: SearchProduct;
    variant_id: string;
    quantity: string;
};

/**
 * How many more times `product` could still be added as a distinct bundle
 * component — a variant-less product tops out at 1 (a second row would be
 * an exact duplicate); a product with variants tops out at its variant
 * count, since each occurrence must pin a different one.
 */
function remainingCapacity(
    product: SearchProduct,
    selectedItems: SelectedItem[],
): number {
    const capacity = Math.max(product.variants.length, 1);
    const used = selectedItems.filter(
        (item) => item.product.id === product.id,
    ).length;

    return capacity - used;
}

type BundleItem = {
    id: number;
    variant_id: number | null;
    quantity: number;
    product: SearchProduct;
};

type ProductBundle = {
    id: number;
    name: string;
    description: string | null;
    image: string | null;
    discount_type: 'fixed_price' | 'free_delivery';
    bundle_price: number | null;
    is_active: boolean;
    items: BundleItem[];
};

type Props = {
    bundle: ProductBundle;
};

function variantLabel(variant: SearchVariant): string {
    return [variant.color_value?.value, variant.size_value?.value]
        .filter(Boolean)
        .join(' / ');
}

export default function ProductBundleEdit({ bundle }: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            _method: 'PUT',
            name: bundle.name,
            description: bundle.description ?? '',
            image: bundle.image as File | string | null,
            discount_type: bundle.discount_type,
            bundle_price:
                bundle.bundle_price !== null ? String(bundle.bundle_price) : '',
            is_active: bundle.is_active,
            items: bundle.items.map((item) => ({
                product_id: item.product.id,
                variant_id: item.variant_id,
                quantity: item.quantity,
            })),
        });

    const [selectedItems, setSelectedItems] = useState<SelectedItem[]>(() =>
        bundle.items.map((item) => ({
            uid: crypto.randomUUID(),
            product: item.product,
            variant_id: item.variant_id ? String(item.variant_id) : '',
            quantity: String(item.quantity),
        })),
    );
    const [searchResults, setSearchResults] = useState<SearchProduct[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const searchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                searchRef.current &&
                !searchRef.current.contains(e.target as Node)
            ) {
                setSearchResults([]);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (!searchQuery.trim()) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- clears results at once when the query is emptied; fetched results are set asynchronously
            setSearchResults([]);

            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearching(true);

            try {
                const res = await fetch(
                    `/admin/product-bundles/products/search?q=${encodeURIComponent(searchQuery)}`,
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (res.ok) {
                    setSearchResults(await res.json());
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
    }, [searchQuery]);

    const syncItems = (items: SelectedItem[]) => {
        setData(
            'items',
            items.map((item) => ({
                product_id: item.product.id,
                variant_id: item.variant_id ? Number(item.variant_id) : null,
                quantity: Number(item.quantity) || 1,
            })),
        );
    };

    const addProduct = (product: SearchProduct) => {
        if (remainingCapacity(product, selectedItems) <= 0) {
            return;
        }

        const next = [
            ...selectedItems,
            {
                uid: crypto.randomUUID(),
                product,
                variant_id: '',
                quantity: '1',
            },
        ];
        setSelectedItems(next);
        syncItems(next);
        setSearchQuery('');
        setSearchResults([]);
    };

    const removeItem = (uid: string) => {
        const next = selectedItems.filter((item) => item.uid !== uid);
        setSelectedItems(next);
        syncItems(next);
    };

    const updateItem = (
        uid: string,
        key: 'variant_id' | 'quantity',
        value: string,
    ) => {
        const next = selectedItems.map((item) =>
            item.uid === uid ? { ...item, [key]: value } : item,
        );
        setSelectedItems(next);
        syncItems(next);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors();

        const clientErrors = validate(data, {
            name: [required(), maxLength(255)],
        });

        if (data.discount_type === 'fixed_price' && !data.bundle_price) {
            clientErrors.bundle_price = 'Enter a bundle price.';
        }

        if (selectedItems.length < 2) {
            clientErrors.items = 'Add at least two products to form a bundle.';
        }

        const needsVariant = selectedItems.some(
            (item) =>
                (item.product.has_color_variant ||
                    item.product.has_size_variant) &&
                !item.variant_id,
        );

        if (needsVariant) {
            clientErrors.items =
                'Pick a variant for every product that has one.';
        }

        const pairKeys = selectedItems.map(
            (item) => `${item.product.id}|${item.variant_id || 'none'}`,
        );

        if (new Set(pairKeys).size !== pairKeys.length) {
            clientErrors.items =
                'The same product and variant combination cannot be added more than once.';
        }

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        // PHP never populates $_POST/$_FILES for a literal PUT + multipart/form-data
        // request, so this must go as a real POST with _method spoofing.
        post(route('admin.product-bundles.update', bundle.id), {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title="Edit Bundle — Admin" />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="rounded-full"
                    >
                        <Link href={route('admin.product-bundles.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Edit Bundle
                        </h1>
                        <p className="text-sm text-slate-500">
                            Update bundle information.
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">
                                        Bundle Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-4">
                                    <Input
                                        label="Name"
                                        value={data.name}
                                        onChange={(e) => {
                                            setData('name', e.target.value);
                                            clearErrors('name');
                                        }}
                                        placeholder="e.g. Summer Combo"
                                        error={errors.name}
                                        required
                                    />
                                    <Textarea
                                        label="Description"
                                        value={data.description}
                                        onChange={(e) => {
                                            setData(
                                                'description',
                                                e.target.value,
                                            );
                                            clearErrors('description');
                                        }}
                                        placeholder="Brief supporting text shown on the bundle card"
                                        error={errors.description}
                                        rows={3}
                                    />
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium text-text-primary">
                                            Bundle Image
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            Upload a new image to replace the
                                            current one. Leave as-is to keep the
                                            existing image.
                                        </p>
                                        <ImageUpload
                                            value={data.image}
                                            onChange={(file) => {
                                                setData(
                                                    'image',
                                                    file ?? bundle.image,
                                                );
                                                clearErrors('image');
                                            }}
                                            error={errors.image}
                                            previewClassName="h-48"
                                        />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium text-text-primary">
                                                Discount Type
                                            </label>
                                            <Select
                                                selectClassName="h-10 w-full"
                                                value={data.discount_type}
                                                onChange={(e) => {
                                                    setData(
                                                        'discount_type',
                                                        e.target.value as
                                                            | 'fixed_price'
                                                            | 'free_delivery',
                                                    );
                                                    clearErrors(
                                                        'discount_type',
                                                    );
                                                }}
                                            >
                                                <option value="fixed_price">
                                                    Fixed bundle price
                                                </option>
                                                <option value="free_delivery">
                                                    Free delivery
                                                </option>
                                            </Select>
                                        </div>
                                        {data.discount_type ===
                                            'fixed_price' && (
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                label="Bundle Price (৳)"
                                                value={data.bundle_price}
                                                onChange={(e) => {
                                                    setData(
                                                        'bundle_price',
                                                        e.target.value,
                                                    );
                                                    clearErrors('bundle_price');
                                                }}
                                                error={errors.bundle_price}
                                                required
                                            />
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-4">
                                    <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                        <Package className="size-4 text-emerald-500" />
                                        Bundle Products
                                    </CardTitle>
                                    <div
                                        ref={searchRef}
                                        className="relative w-72"
                                    >
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                        <Input
                                            placeholder="Search products..."
                                            className="h-9 pl-9"
                                            value={searchQuery}
                                            onChange={(e) =>
                                                setSearchQuery(e.target.value)
                                            }
                                        />
                                        {(searching ||
                                            searchResults.length > 0) && (
                                            <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-72 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                                {searching && (
                                                    <p className="px-4 py-3 text-sm text-slate-400">
                                                        Searching...
                                                    </p>
                                                )}
                                                {!searching &&
                                                    searchResults.map(
                                                        (product) => {
                                                            const used =
                                                                selectedItems.filter(
                                                                    (item) =>
                                                                        item
                                                                            .product
                                                                            .id ===
                                                                        product.id,
                                                                ).length;
                                                            const exhausted =
                                                                remainingCapacity(
                                                                    product,
                                                                    selectedItems,
                                                                ) <= 0;

                                                            return (
                                                                <button
                                                                    key={
                                                                        product.id
                                                                    }
                                                                    type="button"
                                                                    disabled={
                                                                        exhausted
                                                                    }
                                                                    className={cn(
                                                                        'flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-2.5 text-left last:border-0',
                                                                        exhausted
                                                                            ? 'cursor-not-allowed bg-slate-50 opacity-50'
                                                                            : 'group cursor-pointer hover:bg-slate-50',
                                                                    )}
                                                                    onClick={() =>
                                                                        !exhausted &&
                                                                        addProduct(
                                                                            product,
                                                                        )
                                                                    }
                                                                >
                                                                    <div className="min-w-0 flex-1">
                                                                        <p className="truncate text-sm font-medium text-slate-900">
                                                                            {
                                                                                product.name
                                                                            }
                                                                        </p>
                                                                        <p className="text-xs text-slate-500">
                                                                            ৳
                                                                            {
                                                                                product.price
                                                                            }
                                                                            {used >
                                                                                0 &&
                                                                                !exhausted &&
                                                                                ` · added ×${used}, pick another variant`}
                                                                        </p>
                                                                    </div>
                                                                    {exhausted ? (
                                                                        <Check className="size-4 shrink-0 text-emerald-500" />
                                                                    ) : (
                                                                        <Plus className="size-4 shrink-0 text-slate-400 group-hover:text-slate-700" />
                                                                    )}
                                                                </button>
                                                            );
                                                        },
                                                    )}
                                            </div>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    {errors.items && (
                                        <p className="mb-3 text-xs text-red-500">
                                            {errors.items}
                                        </p>
                                    )}
                                    {selectedItems.length === 0 ? (
                                        <div className="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 py-10 text-center">
                                            <Package className="mx-auto mb-3 size-10 text-slate-300" />
                                            <p className="text-slate-500">
                                                No products added yet.
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                Search and add at least two
                                                products — or the same product
                                                twice with different variants.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-3">
                                            {selectedItems.map((item) => {
                                                const needsVariant =
                                                    item.product
                                                        .has_color_variant ||
                                                    item.product
                                                        .has_size_variant;
                                                // Variants a sibling row already claimed for this same
                                                // product can't be picked again — keeps every (product,
                                                // variant) pair in the bundle unique by construction.
                                                const usedBySiblings =
                                                    selectedItems
                                                        .filter(
                                                            (other) =>
                                                                other.uid !==
                                                                    item.uid &&
                                                                other.product
                                                                    .id ===
                                                                    item.product
                                                                        .id &&
                                                                other.variant_id,
                                                        )
                                                        .map(
                                                            (other) =>
                                                                other.variant_id,
                                                        );
                                                const availableVariants =
                                                    item.product.variants.filter(
                                                        (variant) =>
                                                            !usedBySiblings.includes(
                                                                String(
                                                                    variant.id,
                                                                ),
                                                            ),
                                                    );

                                                return (
                                                    <div
                                                        key={item.uid}
                                                        className="flex items-end gap-3 rounded-lg border border-slate-100 bg-slate-50/50 p-3"
                                                    >
                                                        <div className="flex-1">
                                                            <p className="mb-1 text-xs font-medium text-slate-600">
                                                                Product
                                                            </p>
                                                            <p className="truncate text-sm font-medium text-slate-900">
                                                                {
                                                                    item.product
                                                                        .name
                                                                }
                                                            </p>
                                                        </div>
                                                        {needsVariant && (
                                                            <div className="w-44">
                                                                <label className="mb-1 block text-xs font-medium text-slate-600">
                                                                    Variant *
                                                                </label>
                                                                <Select
                                                                    selectClassName="h-9 w-full"
                                                                    value={
                                                                        item.variant_id
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateItem(
                                                                            item.uid,
                                                                            'variant_id',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                >
                                                                    <option value="">
                                                                        — Select
                                                                        —
                                                                    </option>
                                                                    {availableVariants.map(
                                                                        (
                                                                            variant,
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    variant.id
                                                                                }
                                                                                value={
                                                                                    variant.id
                                                                                }
                                                                            >
                                                                                {variantLabel(
                                                                                    variant,
                                                                                )}
                                                                            </option>
                                                                        ),
                                                                    )}
                                                                </Select>
                                                            </div>
                                                        )}
                                                        <div className="w-24">
                                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                                Qty *
                                                            </label>
                                                            <input
                                                                type="number"
                                                                min="1"
                                                                className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                                                value={
                                                                    item.quantity
                                                                }
                                                                onChange={(e) =>
                                                                    updateItem(
                                                                        item.uid,
                                                                        'quantity',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeItem(
                                                                    item.uid,
                                                                )
                                                            }
                                                            className="mb-0.5 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">
                                        Settings
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-4">
                                    <div className="flex items-center justify-between">
                                        <label className="text-sm font-medium text-slate-700">
                                            Active
                                        </label>
                                        <Switch
                                            checked={data.is_active}
                                            onCheckedChange={(checked) =>
                                                setData('is_active', checked)
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="px-8"
                        >
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                        <Button
                            asChild
                            variant="ghost"
                            className="text-slate-500"
                        >
                            <Link href={route('admin.product-bundles.index')}>
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
