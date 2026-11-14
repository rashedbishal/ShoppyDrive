import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import LoadingLogoSpinner, {
    LOADING_DESKTOP_MIN_WIDTH_PX,
    LOADING_PHONE_SCALE,
    LOADING_SCREEN_FIELDS,
} from '@/components/shared/LoadingLogoSpinner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { ImageUpload } from '@/components/ui/image-upload';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type Props = {
    settings: Record<string, string | Record<string, string> | null>;
};

type LoadingScreenKey = keyof typeof LOADING_SCREEN_FIELDS;

/** Largest the CMS preview draws the ring; bigger rings are shown scaled down. */
const LOADING_PREVIEW_MAX_PX = 180;

const LOADING_FIELD_LABELS: Record<
    LoadingScreenKey,
    { name: string; unit: string }
> = {
    loading_spin_seconds: { name: 'Speed', unit: 'sec' },
    loading_ring_size: { name: 'Ring diameter', unit: 'px' },
    loading_logo_size: { name: 'Logo size', unit: 'px' },
    loading_ring_thickness: { name: 'Ring thickness', unit: 'px' },
};

/**
 * What's wrong with a typed loading-screen value, or null when it's fine.
 * Mirrors LoadingScreenSettings::rules() on the server.
 */
function loadingValueError(
    key: LoadingScreenKey,
    raw: string,
    ringSize: number | null,
): string | null {
    const { min, max, step } = LOADING_SCREEN_FIELDS[key];
    const { name, unit } = LOADING_FIELD_LABELS[key];
    const text = raw.trim();

    if (text === '') {
        return `Enter the ${name.toLowerCase()}.`;
    }

    if (!/^\d+(\.\d+)?$/.test(text)) {
        return (
            'Enter a number, like ' + LOADING_SCREEN_FIELDS[key].default + '.'
        );
    }

    if (step === 1 && text.includes('.')) {
        return 'Use a whole number of pixels.';
    }

    if (step !== 1 && /\.\d{2,}$/.test(text)) {
        return 'Use at most one decimal place, like 1.5.';
    }

    const value = Number(text);

    if (value < min || value > max) {
        return `Must be between ${min} and ${max} ${unit}.`;
    }

    if (key === 'loading_logo_size' && ringSize !== null && value > ringSize) {
        return `Can't be larger than the ring diameter (${ringSize} px).`;
    }

    return null;
}

/** The typed value when it's valid, else the field's default (for the preview and slider). */
function usableLoadingValue(
    key: LoadingScreenKey,
    raw: string,
    error: string | null,
): number {
    return error === null ? Number(raw) : LOADING_SCREEN_FIELDS[key].default;
}

/** One loading-screen number: a slider plus an exact-value box. */
function LoadingScreenSlider({
    field,
    value,
    sliderValue,
    error,
    onChange,
}: {
    field: LoadingScreenKey;
    value: string;
    sliderValue: number;
    error?: string | null;
    onChange: (value: string) => void;
}): React.JSX.Element {
    const { min, max, step } = LOADING_SCREEN_FIELDS[field];
    const { name, unit } = LOADING_FIELD_LABELS[field];
    const errorId = `${field}-error`;

    return (
        <div>
            <div className="flex items-center gap-3">
                <label
                    htmlFor={field}
                    className="w-24 shrink-0 text-sm text-slate-600"
                >
                    {name}
                </label>
                <input
                    type="range"
                    aria-label={`${name} slider`}
                    min={min}
                    max={max}
                    step={step}
                    value={sliderValue}
                    onChange={(e) => onChange(e.target.value)}
                    className="min-w-0 flex-1 accent-[#EE5923]"
                />
                <input
                    id={field}
                    type="number"
                    inputMode={step === 1 ? 'numeric' : 'decimal'}
                    min={min}
                    max={max}
                    step={step}
                    value={value}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                    onChange={(e) => onChange(e.target.value)}
                    className={cn(
                        settingsInputClass,
                        'w-[4.5rem] shrink-0 px-2 text-right',
                        error && 'border-red-400 focus:ring-red-500',
                    )}
                />
                <span className="w-6 shrink-0 text-sm text-slate-600">
                    {unit}
                </span>
            </div>
            {error && (
                <p id={errorId} className="mt-1 text-xs text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

/** A displayable URL for an image field holding a saved URL or a new File. */
function useImagePreviewUrl(value: File | string | null): string | null {
    const fileUrl = useMemo(
        () => (value instanceof File ? URL.createObjectURL(value) : null),
        [value],
    );

    useEffect(
        () => () => {
            if (fileUrl) {
                URL.revokeObjectURL(fileUrl);
            }
        },
        [fileUrl],
    );

    return typeof value === 'string' ? value || null : fileUrl;
}

export default function SettingsEdit({ settings }: Props) {
    const socialLinks = (settings.social_links ?? {}) as Record<string, string>;

    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT',
        site_name: (settings.site_name as string) ?? '',
        currency_symbol: (settings.currency_symbol as string) ?? '৳',
        guest_checkout_enabled: Boolean(settings.guest_checkout_enabled),
        review_images_enabled: Boolean(settings.review_images_enabled),
        review_images_max: Number(settings.review_images_max) || 3,
        order_notification_email:
            (settings.order_notification_email as string) ?? '',
        order_cancellation_email:
            (settings.order_cancellation_email as string) ?? '',
        stock_alert_email: (settings.stock_alert_email as string) ?? '',
        logo:
            (settings.logo as string | null) ?? (null as File | string | null),
        loading_logo:
            (settings.loading_logo as string | null) ??
            (null as File | string | null),
        // Kept as typed text so a half-typed or wrong value can be shown
        // and explained instead of silently turning into a number.
        loading_spin_seconds: String(
            Number(settings.loading_spin_seconds) ||
                LOADING_SCREEN_FIELDS.loading_spin_seconds.default,
        ),
        loading_ring_size: String(
            Number(settings.loading_ring_size) ||
                LOADING_SCREEN_FIELDS.loading_ring_size.default,
        ),
        loading_logo_size: String(
            Number(settings.loading_logo_size) ||
                LOADING_SCREEN_FIELDS.loading_logo_size.default,
        ),
        loading_ring_thickness: String(
            Number(settings.loading_ring_thickness) ||
                LOADING_SCREEN_FIELDS.loading_ring_thickness.default,
        ),
        favicon:
            (settings.favicon as string | null) ??
            (null as File | string | null),
        social_links: {
            facebook: socialLinks.facebook ?? '',
            instagram: socialLinks.instagram ?? '',
            twitter: socialLinks.twitter ?? '',
            youtube: socialLinks.youtube ?? '',
            tiktok: socialLinks.tiktok ?? '',
        },
        whatsapp_number: (settings.whatsapp_number as string) ?? '',
        messenger_link: (settings.messenger_link as string) ?? '',
        bkash_number: (settings.bkash_number as string) ?? '',
        nagad_number: (settings.nagad_number as string) ?? '',
    });

    const loadingLogoPreview = useImagePreviewUrl(data.loading_logo);
    const brandLogoPreview = useImagePreviewUrl(data.logo);
    const ringError = loadingValueError(
        'loading_ring_size',
        data.loading_ring_size,
        null,
    );
    const previewRingSize = usableLoadingValue(
        'loading_ring_size',
        data.loading_ring_size,
        ringError,
    );
    const loadingErrors: Record<LoadingScreenKey, string | null> = {
        loading_spin_seconds: loadingValueError(
            'loading_spin_seconds',
            data.loading_spin_seconds,
            null,
        ),
        loading_ring_size: ringError,
        loading_logo_size: loadingValueError(
            'loading_logo_size',
            data.loading_logo_size,
            ringError === null ? previewRingSize : null,
        ),
        loading_ring_thickness: loadingValueError(
            'loading_ring_thickness',
            data.loading_ring_thickness,
            null,
        ),
    };
    const hasLoadingErrors = Object.values(loadingErrors).some(
        (error) => error !== null,
    );
    const previewSeconds = usableLoadingValue(
        'loading_spin_seconds',
        data.loading_spin_seconds,
        loadingErrors.loading_spin_seconds,
    );
    const previewLogoSize = usableLoadingValue(
        'loading_logo_size',
        data.loading_logo_size,
        loadingErrors.loading_logo_size,
    );
    const previewRingThickness = usableLoadingValue(
        'loading_ring_thickness',
        data.loading_ring_thickness,
        loadingErrors.loading_ring_thickness,
    );
    const previewScale = Math.min(1, LOADING_PREVIEW_MAX_PX / previewRingSize);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (hasLoadingErrors) {
            document
                .getElementById(
                    Object.keys(loadingErrors).find(
                        (key) => loadingErrors[key as LoadingScreenKey],
                    ) ?? '',
                )
                ?.focus();

            return;
        }

        post(route('admin.settings.update'), { forceFormData: true });
    };

    return (
        <>
            <Head title="Settings — Admin" />
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Settings
                    </h1>
                    <p className="text-sm text-slate-600">
                        Contact email, phone, and address (shared with the
                        footer) are now edited from{' '}
                        <Link
                            href={route('admin.settings.contact-page')}
                            className="underline"
                        >
                            Contact Page
                        </Link>
                        . Google Analytics, Facebook Pixel and Custom
                        Header/Footer code are now edited from{' '}
                        <Link
                            href={route('admin.settings.marketing.edit')}
                            className="underline"
                        >
                            Marketing &amp; Tracking
                        </Link>
                        .
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                General
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SettingsField label="Site Name">
                                    <input
                                        maxLength={60}
                                        className={settingsInputClass}
                                        value={data.site_name}
                                        onChange={(e) =>
                                            setData('site_name', e.target.value)
                                        }
                                        placeholder="ShoppyDrive"
                                    />
                                    <CharCount
                                        value={data.site_name}
                                        max={60}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                                <SettingsField label="Currency Symbol">
                                    <input
                                        maxLength={5}
                                        className={settingsInputClass}
                                        value={data.currency_symbol}
                                        onChange={(e) =>
                                            setData(
                                                'currency_symbol',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="৳"
                                    />
                                    <CharCount
                                        value={data.currency_symbol}
                                        max={5}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                            </div>
                            <SettingsField
                                label="Brand Logo"
                                hint="The complete logo (mark, name and motto together), shown in the website header, footer, login pages, error pages, landing pages, CMS, emails and invoices. A wide PNG with a transparent background works best (WebP does not show in some email apps), about 600 × 200 px and under 100 KB — max 2 MB."
                            >
                                <ImageUpload
                                    value={data.logo}
                                    onChange={(file) => setData('logo', file)}
                                    previewClassName="h-32"
                                />
                            </SettingsField>
                            <SettingsField
                                label="Loading Logo"
                                hint="Shown in the middle of the screen while a page is loading, inside a turning ring. Leave empty to use the Brand Logo. A square image with a transparent background (PNG or WEBP) looks best, about 512 × 512 px and under 100 KB — max 2 MB and 4096 × 4096 px. It's resized automatically so it loads fast."
                            >
                                <ImageUpload
                                    value={data.loading_logo}
                                    onChange={(file) =>
                                        setData('loading_logo', file)
                                    }
                                    previewClassName="h-32"
                                />
                            </SettingsField>
                            <SettingsField
                                label="Loading Screen"
                                hint={`Speed sets the pace of the loading ring (roughly seconds per sweep) — smaller is faster. Sizes are for desktop screens (${LOADING_DESKTOP_MIN_WIDTH_PX}px wide and up); on smaller screens the ring, logo and thickness shrink together, down to ${LOADING_PHONE_SCALE * 100}% on phones.`}
                            >
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-center">
                                    <div className="flex flex-1 flex-col gap-3">
                                        <LoadingScreenSlider
                                            field="loading_spin_seconds"
                                            value={data.loading_spin_seconds}
                                            sliderValue={previewSeconds}
                                            error={
                                                loadingErrors.loading_spin_seconds ??
                                                errors.loading_spin_seconds
                                            }
                                            onChange={(value) =>
                                                setData(
                                                    'loading_spin_seconds',
                                                    value,
                                                )
                                            }
                                        />
                                        <LoadingScreenSlider
                                            field="loading_ring_size"
                                            value={data.loading_ring_size}
                                            sliderValue={previewRingSize}
                                            error={
                                                loadingErrors.loading_ring_size ??
                                                errors.loading_ring_size
                                            }
                                            onChange={(value) =>
                                                setData(
                                                    'loading_ring_size',
                                                    value,
                                                )
                                            }
                                        />
                                        <LoadingScreenSlider
                                            field="loading_logo_size"
                                            value={data.loading_logo_size}
                                            sliderValue={previewLogoSize}
                                            error={
                                                loadingErrors.loading_logo_size ??
                                                errors.loading_logo_size
                                            }
                                            onChange={(value) =>
                                                setData(
                                                    'loading_logo_size',
                                                    value,
                                                )
                                            }
                                        />
                                        <LoadingScreenSlider
                                            field="loading_ring_thickness"
                                            value={data.loading_ring_thickness}
                                            sliderValue={previewRingThickness}
                                            error={
                                                loadingErrors.loading_ring_thickness ??
                                                errors.loading_ring_thickness
                                            }
                                            onChange={(value) =>
                                                setData(
                                                    'loading_ring_thickness',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="flex flex-col items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <div
                                            className="flex items-center justify-center"
                                            style={{
                                                width: LOADING_PREVIEW_MAX_PX,
                                                height: LOADING_PREVIEW_MAX_PX,
                                            }}
                                        >
                                            <LoadingLogoSpinner
                                                logo={
                                                    loadingLogoPreview ??
                                                    brandLogoPreview
                                                }
                                                seconds={previewSeconds}
                                                ringSize={previewRingSize}
                                                logoSize={previewLogoSize}
                                                ringThickness={
                                                    previewRingThickness
                                                }
                                                responsive={false}
                                                maxSize={`${LOADING_PREVIEW_MAX_PX}px`}
                                            />
                                        </div>
                                        <span className="text-xs text-slate-600">
                                            {previewScale < 1
                                                ? `Preview at ${Math.round(previewScale * 100)}% of real size`
                                                : 'Preview at real size'}
                                        </span>
                                    </div>
                                </div>
                            </SettingsField>
                            <SettingsField
                                label="Favicon"
                                hint="The small icon in the browser tab and on phone home screens. A square PNG (at least 180 × 180) works best; ICO and WEBP also work. Leave empty to use the built-in icon — max 512 KB."
                            >
                                <ImageUpload
                                    value={data.favicon}
                                    onChange={(file) =>
                                        setData('favicon', file)
                                    }
                                    previewClassName="h-20"
                                    maxSizeMb={0.5}
                                />
                            </SettingsField>
                            <SettingsField
                                label="Allow Guest Checkout"
                                hint="When on, customers can place an order without creating an account or logging in. Phone number stays required; email becomes optional for guests. They can still log in instead if they prefer."
                            >
                                <Switch
                                    checked={data.guest_checkout_enabled}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'guest_checkout_enabled',
                                            checked,
                                        )
                                    }
                                />
                            </SettingsField>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Customer Review Photos
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <SettingsField
                                label="Allow photos with reviews"
                                hint="When on, customers who can review a product may attach photos of what they received. You still approve every review (and its photos) before it appears. Off by default."
                            >
                                <Switch
                                    checked={data.review_images_enabled}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'review_images_enabled',
                                            checked,
                                        )
                                    }
                                />
                            </SettingsField>
                            {data.review_images_enabled && (
                                <SettingsField
                                    label="Photos allowed per review"
                                    hint="The most photos one customer can attach to a single review (1 to 5)."
                                >
                                    <input
                                        type="number"
                                        min={1}
                                        max={5}
                                        step={1}
                                        className={`${settingsInputClass} max-w-[120px]`}
                                        value={data.review_images_max}
                                        onChange={(e) =>
                                            setData(
                                                'review_images_max',
                                                Math.min(
                                                    5,
                                                    Math.max(
                                                        1,
                                                        Number(
                                                            e.target.value,
                                                        ) || 1,
                                                    ),
                                                ),
                                            )
                                        }
                                    />
                                    {errors.review_images_max && (
                                        <p className="mt-1 text-xs font-medium text-red-600">
                                            {errors.review_images_max}
                                        </p>
                                    )}
                                </SettingsField>
                            )}
                            <div className="rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">
                                <p className="mb-1 font-semibold text-slate-700">
                                    Standard photo rules (fixed)
                                </p>
                                <ul className="list-disc space-y-0.5 pl-4">
                                    <li>
                                        JPG, PNG or WebP only (no GIF or SVG)
                                    </li>
                                    <li>Up to 5 MB per photo</li>
                                    <li>
                                        At least 200 × 200 pixels (no more than
                                        10,000 pixels on a side)
                                    </li>
                                    <li>
                                        Saved resized to at most 1600 pixels on
                                        the longest side, as an optimized image,
                                        with camera data (such as location)
                                        removed
                                    </li>
                                    <li>
                                        Deleting a review also deletes its
                                        photos from the server
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Order Notification Emails
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <p className="text-xs text-slate-600">
                                Customers always get their own order
                                confirmation and cancellation emails. These
                                fields set which shop email address is told
                                about each event. Leave a field empty to send it
                                to the main admin's login email instead.
                            </p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SettingsField
                                    label="Order Confirmation Email"
                                    hint="Receives an email every time a new order is placed."
                                >
                                    <input
                                        maxLength={150}
                                        type="email"
                                        className={settingsInputClass}
                                        value={data.order_notification_email}
                                        onChange={(e) =>
                                            setData(
                                                'order_notification_email',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="orders@yourshop.com"
                                    />
                                    <CharCount
                                        value={data.order_notification_email}
                                        max={150}
                                        className="mt-1 block text-right"
                                    />
                                    {errors.order_notification_email && (
                                        <p className="mt-1 text-xs font-medium text-red-600">
                                            {errors.order_notification_email}
                                        </p>
                                    )}
                                </SettingsField>
                                <SettingsField
                                    label="Order Cancellation Email"
                                    hint="Receives an email every time an order is cancelled."
                                >
                                    <input
                                        maxLength={150}
                                        type="email"
                                        className={settingsInputClass}
                                        value={data.order_cancellation_email}
                                        onChange={(e) =>
                                            setData(
                                                'order_cancellation_email',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="cancellations@yourshop.com"
                                    />
                                    <CharCount
                                        value={data.order_cancellation_email}
                                        max={150}
                                        className="mt-1 block text-right"
                                    />
                                    {errors.order_cancellation_email && (
                                        <p className="mt-1 text-xs font-medium text-red-600">
                                            {errors.order_cancellation_email}
                                        </p>
                                    )}
                                </SettingsField>
                                <SettingsField
                                    label="Stock Alert Email"
                                    hint="Receives low-stock alerts, whether stock fell through an order or a manual change."
                                >
                                    <input
                                        maxLength={150}
                                        type="email"
                                        className={settingsInputClass}
                                        value={data.stock_alert_email}
                                        onChange={(e) =>
                                            setData(
                                                'stock_alert_email',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="stock@yourshop.com"
                                    />
                                    <CharCount
                                        value={data.stock_alert_email}
                                        max={150}
                                        className="mt-1 block text-right"
                                    />
                                    {errors.stock_alert_email && (
                                        <p className="mt-1 text-xs font-medium text-red-600">
                                            {errors.stock_alert_email}
                                        </p>
                                    )}
                                </SettingsField>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Social Links
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SettingsField label="Facebook">
                                    <input
                                        maxLength={300}
                                        className={settingsInputClass}
                                        value={data.social_links.facebook}
                                        onChange={(e) =>
                                            setData('social_links', {
                                                ...data.social_links,
                                                facebook: e.target.value,
                                            })
                                        }
                                        placeholder="https://facebook.com/..."
                                    />
                                    <CharCount
                                        value={data.social_links.facebook}
                                        max={300}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                                <SettingsField label="Instagram">
                                    <input
                                        maxLength={300}
                                        className={settingsInputClass}
                                        value={data.social_links.instagram}
                                        onChange={(e) =>
                                            setData('social_links', {
                                                ...data.social_links,
                                                instagram: e.target.value,
                                            })
                                        }
                                        placeholder="https://instagram.com/..."
                                    />
                                    <CharCount
                                        value={data.social_links.instagram}
                                        max={300}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                                <SettingsField label="Twitter / X">
                                    <input
                                        maxLength={300}
                                        className={settingsInputClass}
                                        value={data.social_links.twitter}
                                        onChange={(e) =>
                                            setData('social_links', {
                                                ...data.social_links,
                                                twitter: e.target.value,
                                            })
                                        }
                                        placeholder="https://x.com/..."
                                    />
                                    <CharCount
                                        value={data.social_links.twitter}
                                        max={300}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                                <SettingsField label="YouTube">
                                    <input
                                        maxLength={300}
                                        className={settingsInputClass}
                                        value={data.social_links.youtube}
                                        onChange={(e) =>
                                            setData('social_links', {
                                                ...data.social_links,
                                                youtube: e.target.value,
                                            })
                                        }
                                        placeholder="https://youtube.com/@..."
                                    />
                                    <CharCount
                                        value={data.social_links.youtube}
                                        max={300}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                                <SettingsField label="TikTok">
                                    <input
                                        maxLength={300}
                                        className={settingsInputClass}
                                        value={data.social_links.tiktok}
                                        onChange={(e) =>
                                            setData('social_links', {
                                                ...data.social_links,
                                                tiktok: e.target.value,
                                            })
                                        }
                                        placeholder="https://tiktok.com/@..."
                                    />
                                    <CharCount
                                        value={data.social_links.tiktok}
                                        max={300}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Chat Widgets
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SettingsField
                                    label="WhatsApp Number"
                                    hint="Phone number with country code, e.g. +8801700000000"
                                >
                                    <input
                                        maxLength={20}
                                        type="tel"
                                        className={settingsInputClass}
                                        value={data.whatsapp_number}
                                        onChange={(e) =>
                                            setData(
                                                'whatsapp_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="+8801700000000"
                                    />
                                    <CharCount
                                        value={data.whatsapp_number}
                                        max={20}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                                <SettingsField
                                    label="Facebook Messenger"
                                    hint="Your Facebook page username or full m.me URL, e.g. shoppydrive"
                                >
                                    <input
                                        maxLength={300}
                                        className={settingsInputClass}
                                        value={data.messenger_link}
                                        onChange={(e) =>
                                            setData(
                                                'messenger_link',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="shoppydrive or https://m.me/shoppydrive"
                                    />
                                    <CharCount
                                        value={data.messenger_link}
                                        max={300}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Mobile Banking Payment
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SettingsField
                                    label="Bkash Number"
                                    hint="Owner's Bkash number customers will send money to"
                                >
                                    <input
                                        maxLength={15}
                                        type="tel"
                                        className={settingsInputClass}
                                        value={data.bkash_number}
                                        onChange={(e) =>
                                            setData(
                                                'bkash_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="01700000000"
                                    />
                                    <CharCount
                                        value={data.bkash_number}
                                        max={15}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                                <SettingsField
                                    label="Nagad Number"
                                    hint="Owner's Nagad number customers will send money to"
                                >
                                    <input
                                        maxLength={15}
                                        type="tel"
                                        className={settingsInputClass}
                                        value={data.nagad_number}
                                        onChange={(e) =>
                                            setData(
                                                'nagad_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="01700000000"
                                    />
                                    <CharCount
                                        value={data.nagad_number}
                                        max={15}
                                        className="mt-1 block text-right"
                                    />
                                </SettingsField>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-brand text-brand-foreground hover:bg-brand-hover"
                        >
                            {processing ? 'Saving…' : 'Save Settings'}
                        </Button>
                        {hasLoadingErrors && (
                            <p className="text-sm text-red-600">
                                Fix the Loading Screen values above to save.
                            </p>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
