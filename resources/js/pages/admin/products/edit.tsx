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
import { MultiMediaUpload } from '@/components/ui/multi-media-upload';
import { Select } from '@/components/ui/select';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import { belowCostWarning } from '@/lib/price-warnings';
import { mediaFlagIndex, remapProductMedia } from '@/lib/product-media-form';
import { maxLength, minValue, required, validate } from '@/lib/validate';
import { buildMediaTagOptions } from '@/lib/variant-media-tags';
import { computeMinVariantPrice } from '@/lib/variant-pricing';

type ProductMedia = {
    id: number;
    path: string;
    type: 'image' | 'video';
    is_primary: boolean;
    is_hover: boolean;
    variant_id: number | null;
    attribute_value_id: number | null;
};
type ProductVariant = {
    id: number;
    color_value_id: number | null;
    size_value_id: number | null;
    /** Null on a row saved before custom attributes; see variantValueIds(). */
    attribute_value_ids: number[] | null;
    sku: string | null;
    price: number;
    compare_price: number | null;
    cost_price: number | null;
    stock_quantity: number;
    low_stock_threshold: number | null;
    is_default: boolean;
};
type Product = {
    id: number;
    name: string;
    slug: string;
    sku: string | null;
    short_description: string | null;
    description: string | null;
    specifications: SpecificationRow[] | null;
    category_id: number | null;
    sub_category_id: number | null;
    brand_id: number | null;
    price: number;
    compare_price: number | null;
    cost_price: number | null;
    stock_quantity: number;
    low_stock_threshold: number;
    is_active: boolean;
    is_featured: boolean;
    free_delivery: boolean;
    free_delivery_min_qty: number | null;
    variant_attribute_ids: number[] | null;
    cry_baby_enabled: boolean;
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    media: ProductMedia[];
    variants: ProductVariant[];
};

type Props = {
    product: Product;
    stockToken: string;
    categories: { id: number; name: string }[];
    subCategories: { id: number; name: string; category_id: number }[];
    brands: { id: number; name: string }[];
    variantAttributes: VariantAttributeOption[];
};

/** A variant's values; a row saved before custom attributes has only Color/Size. */
function variantValueIds(variant: ProductVariant): number[] {
    return (
        variant.attribute_value_ids ??
        [variant.color_value_id, variant.size_value_id].filter(
            (id): id is number => id !== null,
        )
    );
}

/**
 * Images are picked per attribute value: an image saved for a value (Red)
 * shows as that value; an older one saved for a single variant (Red/L)
 * shows as that variant's first value that allows images (Red), or its only
 * value, and saving keeps it for every variant with that value.
 */
function mediaTagFor(
    media: ProductMedia,
    variants: ProductVariant[],
    attributes: VariantAttributeOption[],
): string | null {
    if (media.attribute_value_id != null) {
        return `value:${media.attribute_value_id}`;
    }

    const variant = variants.find((v) => v.id === media.variant_id);

    if (!variant) {
        return null;
    }

    const valueIds = variantValueIds(variant);
    const imageValueId = valueIds.find((valueId) =>
        attributes.some(
            (attribute) =>
                attribute.has_images &&
                attribute.values.some((value) => value.id === valueId),
        ),
    );
    const valueId =
        imageValueId ?? (valueIds.length === 1 ? valueIds[0] : undefined);

    return valueId === undefined ? null : `value:${valueId}`;
}

export default function ProductEdit({
    product,
    stockToken,
    categories,
    subCategories,
    brands,
    variantAttributes,
}: Props) {
    const listUrl = useAdminListUrl('admin.products.index');
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            _method: 'PUT',
            // Kept from when the form first loaded, even after a failed save:
            // it proves the stock shown here is still current.
            stock_token: stockToken,
            name: product.name,
            slug: product.slug,
            sku: product.sku ?? '',
            short_description: product.short_description ?? '',
            description: product.description ?? '',
            specifications: product.specifications ?? [],
            category_id: product.category_id ? String(product.category_id) : '',
            sub_category_id: product.sub_category_id
                ? String(product.sub_category_id)
                : '',
            brand_id: product.brand_id ? String(product.brand_id) : '',
            price: String(product.price),
            compare_price: product.compare_price
                ? String(product.compare_price)
                : '',
            cost_price: product.cost_price ? String(product.cost_price) : '',
            stock_quantity: String(product.stock_quantity),
            low_stock_threshold: String(product.low_stock_threshold),
            is_active: product.is_active,
            is_featured: product.is_featured,
            free_delivery: product.free_delivery,
            free_delivery_min_qty: product.free_delivery_min_qty
                ? String(product.free_delivery_min_qty)
                : '',
            variant_attribute_ids: product.variant_attribute_ids ?? [],
            variants: product.variants.map(
                (v): VariantRow => ({
                    value_ids: variantValueIds(v),
                    sku: v.sku ?? '',
                    price: String(v.price),
                    compare_price: v.compare_price
                        ? String(v.compare_price)
                        : '',
                    cost_price: v.cost_price ? String(v.cost_price) : '',
                    stock_quantity: String(v.stock_quantity),
                    low_stock_threshold: v.low_stock_threshold
                        ? String(v.low_stock_threshold)
                        : '5',
                    is_default: v.is_default,
                }),
            ),
            cry_baby_enabled: product.cry_baby_enabled,
            images: product.media.length
                ? product.media.map((m) => m.path)
                : ([] as (File | string)[]),
            media_tags: product.media.map((m) =>
                mediaTagFor(m, product.variants, variantAttributes),
            ) as (string | null)[],
            primary_image_index: mediaFlagIndex(product.media, 'is_primary'),
            hover_image_index: mediaFlagIndex(product.media, 'is_hover'),
            meta_title: product.meta_title ?? '',
            meta_description: product.meta_description ?? '',
            meta_keywords: product.meta_keywords ?? '',
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

        post(route('admin.products.update', product.id), {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title={`Edit ${product.name} — Admin`} />
            <div className="space-y-5">
                <FormPageHeader
                    title="Edit Product"
                    description={product.name}
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
                                    data-form-error
                                    className="lg:col-span-2"
                                >
                                    <Alert variant="error">
                                        <AlertTitle>
                                            Couldn't save product — please fix
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
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
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
                                    <CharCount
                                        value={data.slug}
                                        max={150}
                                        className="mt-1 block text-right"
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
                                                <CharCount
                                                    value={data.sku}
                                                    max={50}
                                                    className="mt-1 block text-right"
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
                                        className={`w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none`}
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
                                        className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
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
                                    <Select
                                        selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
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
                                    Delivery
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-3">
                                <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={data.free_delivery}
                                        onChange={(e) =>
                                            setData(
                                                'free_delivery',
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-slate-300 accent-[#c8102e]"
                                    />
                                    Free Delivery
                                </label>
                                <AnimatePresence initial={false}>
                                    {data.free_delivery && (
                                        <AdminReveal key="free-delivery-min">
                                            <Field
                                                label="Min Qty for Free Delivery"
                                                error={
                                                    errors.free_delivery_min_qty
                                                }
                                            >
                                                <input
                                                    max={9999}
                                                    type="number"
                                                    min="1"
                                                    className={inputClass(
                                                        errors.free_delivery_min_qty,
                                                    )}
                                                    value={
                                                        data.free_delivery_min_qty
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'free_delivery_min_qty',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Leave blank = any qty"
                                                />
                                                <p className="mt-1 text-xs text-slate-400">
                                                    Leave blank = free delivery
                                                    for any quantity
                                                </p>
                                            </Field>
                                        </AdminReveal>
                                    )}
                                </AnimatePresence>
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
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
