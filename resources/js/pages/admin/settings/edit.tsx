import { Head, Link, useForm } from '@inertiajs/react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';
import { Switch } from '@/components/ui/switch';

type Props = {
    settings: Record<string, string | Record<string, string> | null>;
};

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
        logo:
            (settings.logo as string | null) ?? (null as File | string | null),
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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
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
                    <p className="text-sm text-slate-500">
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
                                        className={settingsInputClass}
                                        value={data.site_name}
                                        onChange={(e) =>
                                            setData('site_name', e.target.value)
                                        }
                                        placeholder="ShoppyDrive"
                                    />
                                </SettingsField>
                                <SettingsField label="Currency Symbol">
                                    <input
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
                                </SettingsField>
                            </div>
                            <SettingsField
                                label="Brand Logo"
                                hint="Displayed in the website header and CMS. JPG, PNG, or WEBP — max 2 MB."
                            >
                                <ImageUpload
                                    value={data.logo}
                                    onChange={(file) => setData('logo', file)}
                                    previewClassName="h-32"
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
                            <p className="text-xs text-slate-500">
                                Customers always get their own order
                                confirmation and cancellation emails. These two
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
                                    {errors.order_cancellation_email && (
                                        <p className="mt-1 text-xs font-medium text-red-600">
                                            {errors.order_cancellation_email}
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
                                </SettingsField>
                                <SettingsField label="Instagram">
                                    <input
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
                                </SettingsField>
                                <SettingsField label="Twitter / X">
                                    <input
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
                                </SettingsField>
                                <SettingsField label="YouTube">
                                    <input
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
                                </SettingsField>
                                <SettingsField label="TikTok">
                                    <input
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
                                </SettingsField>
                                <SettingsField
                                    label="Facebook Messenger"
                                    hint="Your Facebook page username or full m.me URL, e.g. shoppydrive"
                                >
                                    <input
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
                                </SettingsField>
                                <SettingsField
                                    label="Nagad Number"
                                    hint="Owner's Nagad number customers will send money to"
                                >
                                    <input
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
                                </SettingsField>
                            </div>
                        </CardContent>
                    </Card>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        {processing ? 'Saving…' : 'Save Settings'}
                    </Button>
                </form>
            </div>
        </>
    );
}
