import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    settings: Record<string, string | Record<string, string> | null>;
};

export default function SettingsEdit({ settings }: Props) {
    const socialLinks = (settings.social_links ?? {}) as Record<string, string>;
    // Not in the shared `Auth.admin` type (it's typed for the customer
    // User shape) — same cast admins/index.tsx already uses to read this.
    const { props: pageProps } = usePage<any>();
    const isMotherAdmin = Boolean(pageProps.auth?.admin?.is_mother);

    const { data, setData, post, processing } = useForm({
        _method: 'PUT',
        site_name: (settings.site_name as string) ?? '',
        currency_symbol: (settings.currency_symbol as string) ?? '৳',
        guest_checkout_enabled: Boolean(settings.guest_checkout_enabled),
        logo:
            (settings.logo as string | null) ?? (null as File | string | null),
        social_links: {
            facebook: socialLinks.facebook ?? '',
            instagram: socialLinks.instagram ?? '',
            twitter: socialLinks.twitter ?? '',
            youtube: socialLinks.youtube ?? '',
            tiktok: socialLinks.tiktok ?? '',
        },
        google_analytics_id: (settings.google_analytics_id as string) ?? '',
        whatsapp_number: (settings.whatsapp_number as string) ?? '',
        messenger_link: (settings.messenger_link as string) ?? '',
        bkash_number: (settings.bkash_number as string) ?? '',
        nagad_number: (settings.nagad_number as string) ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.settings.update'), { forceFormData: true });
    };

    // Separate form, separate mother-only endpoint: the pasted code runs in
    // every visitor's browser, a bigger capability than anything else on
    // this page, so it can't be bundled into the save above (see
    // SettingController::updateMetaPixel()).
    const metaPixelForm = useForm({
        _method: 'PUT',
        meta_pixel_code: (settings.meta_pixel_code as string) ?? '',
    });

    const handleMetaPixelSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        metaPixelForm.post(route('admin.settings.meta-pixel.update'));
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
                        .
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                General
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
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
                                        className={`${settingsInputClass} max-w-[120px]`}
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
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Social Links
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
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
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Integrations
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SettingsField
                                    label="Google Analytics ID"
                                    hint="e.g. G-XXXXXXXX or UA-XXXXXXXX"
                                >
                                    <input
                                        className={settingsInputClass}
                                        value={data.google_analytics_id}
                                        onChange={(e) =>
                                            setData(
                                                'google_analytics_id',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="G-XXXXXXXXXX"
                                    />
                                </SettingsField>
                            </div>
                            <form
                                onSubmit={handleMetaPixelSubmit}
                                className="space-y-3"
                            >
                                <SettingsField
                                    label="Meta Pixel Code"
                                    hint="Paste the whole base code Facebook gives you (Events Manager → Settings → Set up manually), including the <script> and <noscript> tags. It's rendered on every storefront page exactly as pasted, so re-paste it here whenever Meta changes it."
                                >
                                    <Textarea
                                        textareaClassName={`${settingsInputClass} font-mono text-xs`}
                                        rows={8}
                                        disabled={!isMotherAdmin}
                                        value={
                                            metaPixelForm.data.meta_pixel_code
                                        }
                                        onChange={(e) =>
                                            metaPixelForm.setData(
                                                'meta_pixel_code',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="<!-- Meta Pixel Code -->&#10;<script>...</script>&#10;<noscript>...</noscript>&#10;<!-- End Meta Pixel Code -->"
                                    />
                                </SettingsField>

                                {!isMotherAdmin && (
                                    <div className="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                        <Lock className="mt-0.5 size-4 shrink-0" />
                                        Only the main (mother) admin can change
                                        the Meta Pixel code. You can view it
                                        here.
                                    </div>
                                )}

                                {isMotherAdmin && (
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={metaPixelForm.processing}
                                    >
                                        Save Meta Pixel Code
                                    </Button>
                                )}
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Chat Widgets
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
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
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Mobile Banking Payment
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
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
