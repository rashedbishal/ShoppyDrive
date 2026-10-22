import { Head, Link, useForm } from '@inertiajs/react';
import type { VariantRow } from '@/components/admin/ProductVariantManager';
import { ProductVariantManager } from '@/components/admin/ProductVariantManager';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { MultiMediaUpload } from '@/components/ui/multi-media-upload';
import { RouteModal } from '@/components/ui/route-modal';
import { Select } from '@/components/ui/select';
import { buildMediaTagOptions } from '@/lib/variant-media-tags';

type AttributeValueOption = {
    id: number;
    value: string;
    color_hex: string | null;
};

type Props = {
    categories: { id: number; name: string }[];
    subCategories: { id: number; name: string; category_id: number }[];
    brands: { id: number; name: string }[];
    attributeValues: {
        colors: AttributeValueOption[];
        sizes: AttributeValueOption[];
    };
};

export default function ProductCreate({
    categories,
    subCategories,
    brands,
    attributeValues,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        sku: '',
        short_description: '',
        description: '',
        category_id: '',
        sub_category_id: '',
        brand_id: '',
        price: '',
        compare_price: '',
        cost_price: '',
        stock_quantity: '0',
        low_stock_threshold: '5',
        is_active: true,
        is_featured: false,
        has_color_variant: false,
        has_size_variant: false,
        variants: [] as VariantRow[],
        cry_baby_enabled: false,
        images: [] as (File | string)[],
        media_tags: [] as (string | null)[],
        meta_title: '',
        meta_description: '',
    });

    const hasVariants = data.has_color_variant || data.has_size_variant;

    const setImages = (files: (File | string)[]) => {
        setData((prev) => ({
            ...prev,
            images: files,
            media_tags: files.map((_, i) => prev.media_tags[i] ?? null),
        }));
    };

    const tagOptions = buildMediaTagOptions(
        data.variants,
        attributeValues.colors,
        attributeValues.sizes,
        data.has_color_variant,
        data.has_size_variant,
    );

    const slugify = (name: string) =>
        name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.products.store'));
    };

    const Field = ({
        label,
        error,
        children,
    }: {
        label: string;
        error?: string;
        children: React.ReactNode;
    }) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
                {label}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title="Add Product — Admin" />
            <RouteModal
                title="Add Product"
                description="Fill in the details below"
                returnTo={route('admin.products.index')}
                className="sm:max-w-4xl"
            >
                <form
                    onSubmit={handleSubmit}
                    className="grid gap-5 lg:grid-cols-[1fr_320px]"
                >
                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Basic Info
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-4">
                                <Field
                                    label="Product Name *"
                                    error={errors.name}
                                >
                                    <input
                                        className={inputClass(errors.name)}
                                        value={data.name}
                                        onChange={(e) => {
                                            setData((prev) => ({
                                                ...prev,
                                                name: e.target.value,
                                                slug: slugify(e.target.value),
                                            }));
                                        }}
                                    />
                                </Field>
                                <Field label="Slug *" error={errors.slug}>
                                    <input
                                        className={inputClass(errors.slug)}
                                        value={data.slug}
                                        onChange={(e) =>
                                            setData('slug', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="SKU" error={errors.sku}>
                                    <input
                                        className={inputClass(errors.sku)}
                                        value={data.sku}
                                        onChange={(e) =>
                                            setData('sku', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Short Description"
                                    error={errors.short_description}
                                >
                                    <textarea
                                        rows={2}
                                        className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-1 focus:outline-none ${errors.short_description ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`}
                                        value={data.short_description}
                                        onChange={(e) =>
                                            setData(
                                                'short_description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Description"
                                    error={errors.description}
                                >
                                    <textarea
                                        rows={5}
                                        className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-1 focus:outline-none ${errors.description ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`}
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Pricing & Stock
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-4">
                                {!hasVariants && (
                                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                        <Field
                                            label="Price (৳) *"
                                            error={errors.price}
                                        >
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className={inputClass(
                                                    errors.price,
                                                )}
                                                value={data.price}
                                                onChange={(e) =>
                                                    setData(
                                                        'price',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Compare Price (৳)"
                                            error={errors.compare_price}
                                        >
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className={inputClass(
                                                    errors.compare_price,
                                                )}
                                                value={data.compare_price}
                                                onChange={(e) =>
                                                    setData(
                                                        'compare_price',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Cost Price (৳)"
                                            error={errors.cost_price}
                                        >
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className={inputClass(
                                                    errors.cost_price,
                                                )}
                                                value={data.cost_price}
                                                onChange={(e) =>
                                                    setData(
                                                        'cost_price',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Stock Qty *"
                                            error={errors.stock_quantity}
                                        >
                                            <input
                                                type="number"
                                                min="0"
                                                className={inputClass(
                                                    errors.stock_quantity,
                                                )}
                                                value={data.stock_quantity}
                                                onChange={(e) =>
                                                    setData(
                                                        'stock_quantity',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Low Stock Alert"
                                            error={errors.low_stock_threshold}
                                        >
                                            <input
                                                type="number"
                                                min="0"
                                                className={inputClass(
                                                    errors.low_stock_threshold,
                                                )}
                                                value={data.low_stock_threshold}
                                                onChange={(e) =>
                                                    setData(
                                                        'low_stock_threshold',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                )}

                                <div className="border-t border-slate-100 pt-4">
                                    <ProductVariantManager
                                        colorValues={attributeValues.colors}
                                        sizeValues={attributeValues.sizes}
                                        hasColorVariant={data.has_color_variant}
                                        hasSizeVariant={data.has_size_variant}
                                        onToggleColor={(enabled) =>
                                            setData(
                                                'has_color_variant',
                                                enabled,
                                            )
                                        }
                                        onToggleSize={(enabled) =>
                                            setData('has_size_variant', enabled)
                                        }
                                        variants={data.variants}
                                        onVariantsChange={(variants) =>
                                            setData('variants', variants)
                                        }
                                        errors={errors}
                                    />
                                    {hasVariants &&
                                        data.variants.length > 0 && (
                                            <p className="mt-3 text-xs text-slate-500">
                                                Computed from{' '}
                                                {data.variants.length} variant
                                                {data.variants.length === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                — from ৳
                                                {Math.min(
                                                    ...data.variants.map(
                                                        (v) =>
                                                            Number(v.price) ||
                                                            Infinity,
                                                    ),
                                                )}
                                            </p>
                                        )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Media
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <MultiMediaUpload
                                    value={data.images}
                                    onChange={setImages}
                                    error={errors.images}
                                    tagOptions={tagOptions}
                                    tags={data.media_tags}
                                    onTagChange={(index, tag) =>
                                        setData((prev) => {
                                            const next = [...prev.media_tags];
                                            next[index] = tag;

                                            return {
                                                ...prev,
                                                media_tags: next,
                                            };
                                        })
                                    }
                                />
                                {errors.images &&
                                    typeof errors.images === 'string' && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.images}
                                        </p>
                                    )}
                                {Object.keys(errors)
                                    .filter((k) => k.startsWith('images.'))
                                    .map((k) => (
                                        <p
                                            key={k}
                                            className="mt-1 text-xs text-red-500"
                                        >
                                            {errors[k as keyof typeof errors]}
                                        </p>
                                    ))}
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    SEO
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-4">
                                <Field
                                    label="Meta Title"
                                    error={errors.meta_title}
                                >
                                    <input
                                        className={inputClass(
                                            errors.meta_title,
                                        )}
                                        value={data.meta_title}
                                        onChange={(e) =>
                                            setData(
                                                'meta_title',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Meta Description"
                                    error={errors.meta_description}
                                >
                                    <textarea
                                        rows={2}
                                        className={`w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none`}
                                        value={data.meta_description}
                                        onChange={(e) =>
                                            setData(
                                                'meta_description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Organisation
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-4">
                                <Field
                                    label="Category *"
                                    error={errors.category_id}
                                >
                                    <Select
                                        selectClassName={`h-9 w-full rounded-lg border px-3 text-sm focus:ring-1 focus:outline-none ${errors.category_id ? 'border-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`}
                                        value={data.category_id}
                                        onChange={(e) => {
                                            setData((prev) => ({
                                                ...prev,
                                                category_id: e.target.value,
                                                sub_category_id: '',
                                            }));
                                        }}
                                    >
                                        <option value="">
                                            — Select Category —
                                        </option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                <Field
                                    label="Sub-category *"
                                    error={errors.sub_category_id}
                                >
                                    <Select
                                        selectClassName={`h-9 w-full rounded-lg border px-3 text-sm focus:ring-1 focus:outline-none ${errors.sub_category_id ? 'border-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`}
                                        value={data.sub_category_id}
                                        onChange={(e) =>
                                            setData(
                                                'sub_category_id',
                                                e.target.value,
                                            )
                                        }
                                        disabled={!data.category_id}
                                    >
                                        <option value="">
                                            — Select Sub-category —
                                        </option>
                                        {subCategories
                                            .filter(
                                                (sc) =>
                                                    sc.category_id ===
                                                    Number(data.category_id),
                                            )
                                            .map((sc) => (
                                                <option
                                                    key={sc.id}
                                                    value={sc.id}
                                                >
                                                    {sc.name}
                                                </option>
                                            ))}
                                    </Select>
                                </Field>

                                <Field label="Brand" error={errors.brand_id}>
                                    <Select
                                        selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                        value={data.brand_id}
                                        onChange={(e) =>
                                            setData('brand_id', e.target.value)
                                        }
                                    >
                                        <option value="">— None —</option>
                                        {brands.map((b) => (
                                            <option key={b.id} value={b.id}>
                                                {b.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Visibility
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-4">
                                <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={data.is_active}
                                        onChange={(e) =>
                                            setData(
                                                'is_active',
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-slate-300 accent-[#c8102e]"
                                    />
                                    Active (visible in store)
                                </label>
                                <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={data.is_featured}
                                        onChange={(e) =>
                                            setData(
                                                'is_featured',
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-slate-300 accent-[#c8102e]"
                                    />
                                    Featured
                                </label>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Easter Egg
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-4">
                                <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={data.cry_baby_enabled}
                                        onChange={(e) =>
                                            setData(
                                                'cry_baby_enabled',
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-slate-300 accent-[#c8102e]"
                                    />
                                    Crying baby on Add to Cart
                                </label>
                                <p className="text-xs text-slate-400">
                                    Plays the crying-baby GIF beside the Add to
                                    Cart button when a shopper lingers for a
                                    minute without adding the product, or
                                    removes it from the cart.
                                </p>
                            </CardContent>
                        </Card>

                        <div className="flex flex-col gap-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25]"
                            >
                                {processing ? 'Saving…' : 'Create Product'}
                            </Button>
                            <Link
                                href={route('admin.products.index')}
                                className="text-center text-sm text-slate-500 hover:underline"
                            >
                                Cancel
                            </Link>
                        </div>
                    </div>
                </form>
            </RouteModal>
        </>
    );
}
