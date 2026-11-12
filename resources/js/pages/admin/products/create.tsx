import { Head, Link, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { useEffect, useRef } from 'react';
import { AdminReveal } from '@/components/admin/admin-motion';
import { FormPageHeader } from '@/components/admin/form-page-header';
import {
    ProductFormField as Field,
    productInputClass as inputClass,
} from '@/components/admin/product-form-field';
import type { SpecificationRow } from '@/components/admin/ProductSpecificationsManager';
import { ProductSpecificationsManager } from '@/components/admin/ProductSpecificationsManager';
import type {
    VariantAttributeOption,
    VariantRow,
} from '@/components/admin/ProductVariantManager';
import { ProductVariantManager } from '@/components/admin/ProductVariantManager';
import { RichTextEditor } from '@/components/admin/RichTextEditor';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { ComboboxField } from '@/components/ui/combobox';
import { MultiMediaUpload } from '@/components/ui/multi-media-upload';
import { Select } from '@/components/ui/select';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import { belowCostWarning } from '@/lib/price-warnings';
import { remapProductMedia } from '@/lib/product-media-form';
import { maxLength, minValue, required, validate } from '@/lib/validate';
import { buildMediaTagOptions } from '@/lib/variant-media-tags';
import { computeMinVariantPrice } from '@/lib/variant-pricing';

type Props = {
    categories: { id: number; name: string }[];
    subCategories: { id: number; name: string; category_id: number }[];
    brands: { id: number; name: string }[];
    variantAttributes: VariantAttributeOption[];
};

export default function ProductCreate({
    categories,
    subCategories,
    brands,
    variantAttributes,
}: Props) {
    const listUrl = useAdminListUrl('admin.products.index');
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            name: '',
            slug: '',
            sku: '',
            short_description: '',
            description: '',
            specifications: [] as SpecificationRow[],
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
            variant_attribute_ids: [] as number[],
            variants: [] as VariantRow[],
            cry_baby_enabled: false,
            images: [] as (File | string)[],
            media_tags: [] as (string | null)[],
            primary_image_index: null as number | null,
            hover_image_index: null as number | null,
            meta_title: '',
            meta_description: '',
            meta_keywords: '',
        });

    const hasVariants = data.variant_attribute_ids.length > 0;

    const setImages = (files: (File | string)[]) => {
        setData((prev) => ({ ...prev, ...remapProductMedia(prev, files) }));
    };

    const tagOptions = buildMediaTagOptions(
        data.variants,
        variantAttributes,
        data.variant_attribute_ids,
    );
    const minVariantPrice = computeMinVariantPrice(data.variants);

    const slugify = (name: string) =>
        name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');

    const errorSummaryRef = useRef<HTMLDivElement>(null);
    const errorMessages = Object.values(errors).filter(Boolean);

    useEffect(() => {
        if (errorMessages.length > 0) {
            errorSummaryRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errors]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors: Record<string, string> = validate(data, {
            name: [required(), maxLength(150)],
            slug: [required(), maxLength(150)],
            category_id: [required()],
            sub_category_id: [required()],
        });

        data.specifications.forEach((row, index) => {
            if (!row.key.trim()) {
                clientErrors[`specifications.${index}.key`] =
                    'Key is required.';
            }

            if (!row.value.trim()) {
                clientErrors[`specifications.${index}.value`] =
                    'Value is required.';
            }
        });

        if (!hasVariants) {
            Object.assign(
                clientErrors,
                validate(data, {
                    price: [required(), minValue(0)],
                    stock_quantity: [required(), minValue(0)],
                }),
            );
        } else if (data.variants.length === 0) {
            clientErrors.variants =
                'Add at least one color/size variant, or turn off the variant toggles.';
        } else {
            let defaultCount = 0;
            data.variants.forEach((variant, index) => {
                if (!variant.price) {
                    clientErrors[`variants.${index}.price`] =
                        'The variant price is required.';
                }

                if (!variant.stock_quantity) {
                    clientErrors[`variants.${index}.stock_quantity`] =
                        'The variant stock quantity is required.';
                }

                if (variant.is_default) {
                    defaultCount++;
                }
            });

            if (defaultCount !== 1) {
                clientErrors.variants =
                    'Exactly one variant must be marked as the default.';
            }
        }

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.products.store'));
    };

    return (
        <>
            <Head title="Add Product — Admin" />
            <div className="space-y-5">
                <FormPageHeader
                    title="Add Product"
                    description="Fill in the details below"
                    backHref={listUrl}
                    backLabel="Back to products"
                />
                <form
                    onSubmit={handleSubmit}
                    className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]"
                >
                    <AnimatePresence initial={false}>
                        {errorMessages.length > 0 && (
                            <AdminReveal
                                key="error-summary"
                                className="lg:col-span-2"
                            >
                                <div
                                    ref={errorSummaryRef}
                                    className="lg:col-span-2"
                                >
                                    <Alert variant="error">
                                        <AlertTitle>
                                            Couldn't create product — please fix
                                            the highlighted field
                                            {errorMessages.length > 1
                                                ? 's'
                                                : ''}
                                        </AlertTitle>
                                        <AlertDescription>
                                            <ul className="list-disc space-y-0.5 pl-4">
                                                {errorMessages.map(
                                                    (message, i) => (
                                                        <li key={i}>
                                                            {message}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                </div>
                            </AdminReveal>
                        )}
                    </AnimatePresence>
                    <div className="min-w-0 space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Basic Info
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-3">
                                <Field
                                    label="Product Name *"
                                    error={errors.name}
                                >
                                    <input
                                        maxLength={150}
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
                                    <CharCount
                                        value={data.name}
                                        max={150}
                                        className="mt-1 block text-right"
                                    />
                                </Field>
                                <Field label="Slug *" error={errors.slug}>
                                    <input
                                        maxLength={150}
                                        className={inputClass(errors.slug)}
                                        value={data.slug}
                                        onChange={(e) =>
                                            setData('slug', e.target.value)
                                        }
                                    />
                                </Field>
                                <AnimatePresence initial={false}>
                                    {hasVariants ? (
                                        <AdminReveal key="sku-field-a">
                                            <p className="text-xs text-slate-400">
                                                SKU is set per color/size
                                                variant below instead, since
                                                each variant needs its own.
                                            </p>
                                        </AdminReveal>
                                    ) : (
                                        <AdminReveal key="sku-field-b">
                                            <Field
                                                label="SKU"
                                                error={errors.sku}
                                            >
                                                <input
                                                    maxLength={50}
                                                    className={inputClass(
                                                        errors.sku,
                                                    )}
                                                    value={data.sku}
                                                    onChange={(e) =>
                                                        setData(
                                                            'sku',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </AdminReveal>
                                    )}
                                </AnimatePresence>
                                <Field
                                    label="Short Description"
                                    error={errors.short_description}
                                >
                                    <textarea
                                        maxLength={300}
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
                                    <CharCount
                                        value={data.short_description}
                                        max={300}
                                        className="mt-1 block text-right"
                                    />
                                </Field>
                                <Field
                                    label="Description"
                                    error={errors.description}
                                >
                                    <RichTextEditor
                                        value={data.description}
                                        maxLength={20000}
                                        onChange={(html) =>
                                            setData('description', html)
                                        }
                                    />
                                </Field>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardContent className="pt-3">
                                <ProductSpecificationsManager
                                    specifications={data.specifications}
                                    onChange={(specifications) =>
                                        setData(
                                            'specifications',
                                            specifications,
                                        )
                                    }
                                    errors={errors}
                                    onClearError={(field) =>
                                        clearErrors(field as never)
                                    }
                                />
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Pricing & Stock
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-3">
                                <AnimatePresence initial={false}>
                                    {(!hasVariants ||
                                        data.variants.length === 0) && (
                                        <AdminReveal key="pricing-fields">
                                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                                <Field
                                                    label="Price (৳) *"
                                                    error={errors.price}
                                                    warning={belowCostWarning(
                                                        data.price,
                                                        data.cost_price,
                                                    )}
                                                >
                                                    <input
                                                        max={99999999.99}
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
                                                        max={99999999.99}
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        className={inputClass(
                                                            errors.compare_price,
                                                        )}
                                                        value={
                                                            data.compare_price
                                                        }
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
                                                        max={99999999.99}
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
                                                    error={
                                                        errors.stock_quantity
                                                    }
                                                >
                                                    <input
                                                        max={9999999}
                                                        type="number"
                                                        min="0"
                                                        className={inputClass(
                                                            errors.stock_quantity,
                                                        )}
                                                        value={
                                                            data.stock_quantity
                                                        }
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
                                                    error={
                                                        errors.low_stock_threshold
                                                    }
                                                >
                                                    <input
                                                        max={9999999}
                                                        type="number"
                                                        min="0"
                                                        className={inputClass(
                                                            errors.low_stock_threshold,
                                                        )}
                                                        value={
                                                            data.low_stock_threshold
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'low_stock_threshold',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            </div>
                                        </AdminReveal>
                                    )}
                                </AnimatePresence>

                                <div className="border-t border-slate-100 pt-4">
                                    <ProductVariantManager
                                        attributes={variantAttributes}
                                        selectedAttributeIds={
                                            data.variant_attribute_ids
                                        }
                                        variants={data.variants}
                                        onChange={(setup) =>
                                            setData((prev) => ({
                                                ...prev,
                                                ...setup,
                                            }))
                                        }
                                        errors={errors}
                                    />
                                    <AnimatePresence initial={false}>
                                        {hasVariants &&
                                            data.variants.length > 0 && (
                                                <AdminReveal key="computed-pricing">
                                                    <p className="mt-3 text-xs text-slate-500">
                                                        Computed from{' '}
                                                        {data.variants.length}{' '}
                                                        variant
                                                        {data.variants
                                                            .length === 1
                                                            ? ''
                                                            : 's'}
                                                        {minVariantPrice !==
                                                        null
                                                            ? ` — from ৳${minVariantPrice}`
                                                            : ' — enter a price for each variant'}
                                                    </p>
                                                </AdminReveal>
                                            )}
                                    </AnimatePresence>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    SEO
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-3">
                                <Field
                                    label="Meta Title"
                                    error={errors.meta_title}
                                >
                                    <input
                                        maxLength={70}
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
                                    <CharCount
                                        value={data.meta_title}
                                        max={70}
                                        className="mt-1 block text-right"
                                    />
                                </Field>
                                <Field
                                    label="Meta Description"
                                    error={errors.meta_description}
                                >
                                    <textarea
                                        maxLength={160}
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
                                    <CharCount
                                        value={data.meta_description}
                                        max={160}
                                        className="mt-1 block text-right"
                                    />
                                </Field>
                                <Field
                                    label="Search Keywords"
                                    error={errors.meta_keywords}
                                >
                                    <input
                                        maxLength={200}
                                        className={inputClass(
                                            errors.meta_keywords,
                                        )}
                                        value={data.meta_keywords}
                                        onChange={(e) =>
                                            setData(
                                                'meta_keywords',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <CharCount
                                        value={data.meta_keywords}
                                        max={200}
                                        className="mt-1 block text-right"
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        If this product's name is in Bangla, add
                                        its English name here (e.g. "shirt,
                                        t-shirt") so customers can find it by
                                        searching in English too.
                                    </p>
                                </Field>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="min-w-0 space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Media
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="pt-3">
                                <MultiMediaUpload
                                    value={data.images}
                                    onChange={setImages}
                                    error={errors.images}
                                    tagOptions={tagOptions}
                                    tags={data.media_tags}
                                    primaryIndex={data.primary_image_index}
                                    hoverIndex={data.hover_image_index}
                                    onPrimaryChange={(index) =>
                                        setData((prev) => ({
                                            ...prev,
                                            primary_image_index: index,
                                            hover_image_index:
                                                prev.hover_image_index === index
                                                    ? null
                                                    : prev.hover_image_index,
                                        }))
                                    }
                                    onHoverChange={(index) =>
                                        setData('hover_image_index', index)
                                    }
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
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Organisation
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-3">
                                <Field
                                    label="Category *"
                                    error={errors.category_id}
                                >
                                    <ComboboxField
                                        value={data.category_id}
                                        onChange={(value) => {
                                            setData((prev) => ({
                                                ...prev,
                                                category_id: value,
                                                sub_category_id: '',
                                            }));
                                        }}
                                        options={categories.map((c) => ({
                                            value: String(c.id),
                                            label: c.name,
                                        }))}
                                        error={errors.category_id}
                                        placeholder="Search categories…"
                                        emptyMessage="No categories match that search."
                                        inputClassName="h-9"
                                    />
                                </Field>

                                <Field
                                    label="Sub-category *"
                                    error={errors.sub_category_id}
                                >
                                    <ComboboxField
                                        value={data.sub_category_id}
                                        onChange={(value) =>
                                            setData('sub_category_id', value)
                                        }
                                        options={subCategories
                                            .filter(
                                                (sc) =>
                                                    sc.category_id ===
                                                    Number(data.category_id),
                                            )
                                            .map((sc) => ({
                                                value: String(sc.id),
                                                label: sc.name,
                                            }))}
                                        error={errors.sub_category_id}
                                        placeholder="Search sub-categories…"
                                        emptyMessage="No sub-categories match that search."
                                        disabled={!data.category_id}
                                        inputClassName="h-9"
                                    />
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
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Visibility
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-3">
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
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Easter Egg
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-3">
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
                    </div>

                    <div className="sticky bottom-3 z-20 flex items-center justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur lg:col-span-2">
                        <Button asChild variant="outline">
                            <Link href={listUrl}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="min-w-40 bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : 'Create Product'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
