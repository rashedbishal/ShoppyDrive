import { Head, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';

type Props = {
    settings: Record<string, string | Record<string, string> | null>;
};

export default function SettingsEdit({ settings }: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const socialLinks = (settings.social_links ?? {}) as Record<string, string>;

    const { data, setData, post, processing } = useForm({
        _method: 'PUT',
        site_name: (settings.site_name as string) ?? '',
        contact_email: (settings.contact_email as string) ?? '',
        contact_phone: (settings.contact_phone as string) ?? '',
        contact_address: (settings.contact_address as string) ?? '',
        currency_symbol: (settings.currency_symbol as string) ?? '৳',
        logo: (settings.logo as string | null) ?? null as File | string | null,
        social_links: {
            facebook: socialLinks.facebook ?? '',
            instagram: socialLinks.instagram ?? '',
            twitter: socialLinks.twitter ?? '',
        },
        google_analytics_id: (settings.google_analytics_id as string) ?? '',
        meta_pixel_id: (settings.meta_pixel_id as string) ?? '',
        whatsapp_number: (settings.whatsapp_number as string) ?? '',
        messenger_link: (settings.messenger_link as string) ?? '',
        bkash_number: (settings.bkash_number as string) ?? '',
        nagad_number: (settings.nagad_number as string) ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.settings.update'), { forceFormData: true });
    };

    const Field = ({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{label}</label>
            {hint && <p className="mb-1.5 text-xs text-slate-400">{hint}</p>}
            {children}
        </div>
    );

    const inputClass = 'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    return (
        <>
            <Head title="Settings — Admin" />
            <div className="space-y-5">
                <h1 className="text-xl font-bold text-slate-900">Settings</h1>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-5">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">General</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Site Name">
                                <input className={inputClass} value={data.site_name} onChange={(e) => setData('site_name', e.target.value)} placeholder="ShoppyDrive" />
                            </Field>
                            <Field label="Brand Logo" hint="Displayed in the website header and CMS. JPG, PNG, or WEBP — max 2 MB.">
                                <ImageUpload
                                    value={data.logo}
                                    onChange={(file) => setData('logo', file)}
                                    previewClassName="h-32"
                                />
                            </Field>
                            <Field label="Currency Symbol">
                                <input className={`${inputClass} max-w-[120px]`} value={data.currency_symbol} onChange={(e) => setData('currency_symbol', e.target.value)} placeholder="৳" />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">Contact</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Contact Email">
                                <input type="email" className={inputClass} value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} />
                            </Field>
                            <Field label="Contact Phone">
                                <input type="tel" className={inputClass} value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} />
                            </Field>
                            <Field label="Contact Address">
                                <textarea rows={3} className={inputClass + ' h-auto py-2 resize-none'} value={data.contact_address} onChange={(e) => setData('contact_address', e.target.value)} placeholder="e.g. 123 Main Street, Dhaka, Bangladesh" />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">Social Links</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Facebook">
                                <input className={inputClass} value={data.social_links.facebook} onChange={(e) => setData('social_links', { ...data.social_links, facebook: e.target.value })} placeholder="https://facebook.com/..." />
                            </Field>
                            <Field label="Instagram">
                                <input className={inputClass} value={data.social_links.instagram} onChange={(e) => setData('social_links', { ...data.social_links, instagram: e.target.value })} placeholder="https://instagram.com/..." />
                            </Field>
                            <Field label="Twitter / X">
                                <input className={inputClass} value={data.social_links.twitter} onChange={(e) => setData('social_links', { ...data.social_links, twitter: e.target.value })} placeholder="https://twitter.com/..." />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">Integrations</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Google Analytics ID" hint="e.g. G-XXXXXXXX or UA-XXXXXXXX">
                                <input className={inputClass} value={data.google_analytics_id} onChange={(e) => setData('google_analytics_id', e.target.value)} placeholder="G-XXXXXXXXXX" />
                            </Field>
                            <Field label="Meta Pixel ID" hint="Facebook Pixel tracking ID">
                                <input className={inputClass} value={data.meta_pixel_id} onChange={(e) => setData('meta_pixel_id', e.target.value)} placeholder="123456789" />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">Chat Widgets</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="WhatsApp Number" hint="Phone number with country code, e.g. +8801700000000">
                                <input
                                    type="tel"
                                    className={inputClass}
                                    value={data.whatsapp_number}
                                    onChange={(e) => setData('whatsapp_number', e.target.value)}
                                    placeholder="+8801700000000"
                                />
                            </Field>
                            <Field label="Facebook Messenger" hint="Your Facebook page username or full m.me URL, e.g. shoppydrive">
                                <input
                                    className={inputClass}
                                    value={data.messenger_link}
                                    onChange={(e) => setData('messenger_link', e.target.value)}
                                    placeholder="shoppydrive or https://m.me/shoppydrive"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">Mobile Banking Payment</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Bkash Number" hint="Owner's Bkash number customers will send money to">
                                <input
                                    type="tel"
                                    className={inputClass}
                                    value={data.bkash_number}
                                    onChange={(e) => setData('bkash_number', e.target.value)}
                                    placeholder="01700000000"
                                />
                            </Field>
                            <Field label="Nagad Number" hint="Owner's Nagad number customers will send money to">
                                <input
                                    type="tel"
                                    className={inputClass}
                                    value={data.nagad_number}
                                    onChange={(e) => setData('nagad_number', e.target.value)}
                                    placeholder="01700000000"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing} className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                        {processing ? 'Saving…' : 'Save Settings'}
                    </Button>
                </form>
            </div>
        </>
    );
}
