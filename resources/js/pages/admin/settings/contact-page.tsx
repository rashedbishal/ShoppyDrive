import { Head, Link, useForm } from '@inertiajs/react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    settings: {
        contact_page_hero_eyebrow?: string | null;
        contact_page_hero_title?: string | null;
        contact_page_hero_title_highlight?: string | null;
        contact_page_hero_description?: string | null;
        contact_page_support_hours?: string | null;
        contact_phone?: string | null;
        contact_email?: string | null;
        contact_address?: string | null;
    };
};

export default function ContactPageConfig({ settings }: Props) {
    const { data, setData, put, processing, recentlySuccessful } = useForm({
        contact_page_hero_eyebrow:
            settings.contact_page_hero_eyebrow ?? 'Get in touch',
        contact_page_hero_title:
            settings.contact_page_hero_title ?? "We're here to",
        contact_page_hero_title_highlight:
            settings.contact_page_hero_title_highlight ?? 'help',
        contact_page_hero_description:
            settings.contact_page_hero_description ??
            'Have a question, complaint, or just want to say hello? Our team is ready to assist you.',
        contact_page_support_hours:
            settings.contact_page_support_hours ?? 'Sat–Thu, 9am–9pm BST',
        contact_phone: settings.contact_phone ?? '',
        contact_email: settings.contact_email ?? '',
        contact_address: settings.contact_address ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Contact Page — Admin" />
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Contact Page
                        </h1>
                        <p className="text-sm text-slate-500">
                            Edit the page copy and contact details shown on{' '}
                            <code className="rounded bg-slate-100 px-1">
                                /contact
                            </code>
                            . Received messages are managed under{' '}
                            <Link
                                href={route('admin.contact-messages.index')}
                                className="underline"
                            >
                                Contact Messages
                            </Link>
                            .
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        {recentlySuccessful && (
                            <span className="text-xs font-medium text-emerald-600">
                                Saved.
                            </span>
                        )}
                    </div>
                </div>

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-4">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Hero
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4">
                        <SettingsField label="Eyebrow badge">
                            <input
                                className={settingsInputClass}
                                value={data.contact_page_hero_eyebrow}
                                onChange={(e) =>
                                    setData(
                                        'contact_page_hero_eyebrow',
                                        e.target.value,
                                    )
                                }
                            />
                        </SettingsField>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SettingsField
                                label="Title"
                                hint='e.g. "We&apos;re here to"'
                            >
                                <input
                                    className={settingsInputClass}
                                    value={data.contact_page_hero_title}
                                    onChange={(e) =>
                                        setData(
                                            'contact_page_hero_title',
                                            e.target.value,
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Title (highlighted word)"
                                hint="Shown right after the title, in the brand color"
                            >
                                <input
                                    className={settingsInputClass}
                                    value={
                                        data.contact_page_hero_title_highlight
                                    }
                                    onChange={(e) =>
                                        setData(
                                            'contact_page_hero_title_highlight',
                                            e.target.value,
                                        )
                                    }
                                />
                            </SettingsField>
                        </div>
                        <SettingsField label="Description">
                            <Textarea
                                textareaClassName={settingsInputClass}
                                rows={2}
                                value={data.contact_page_hero_description}
                                onChange={(e) =>
                                    setData(
                                        'contact_page_hero_description',
                                        e.target.value,
                                    )
                                }
                            />
                        </SettingsField>
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-4">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Contact Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4">
                        <p className="text-xs text-slate-400">
                            Phone, email, and address are shared with the site
                            footer and SEO tags — editing them here updates
                            everywhere.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SettingsField label="Phone">
                                <input
                                    className={settingsInputClass}
                                    value={data.contact_phone}
                                    onChange={(e) =>
                                        setData('contact_phone', e.target.value)
                                    }
                                    placeholder="+880 1700-000000"
                                />
                            </SettingsField>
                            <SettingsField label="Email">
                                <input
                                    type="email"
                                    className={settingsInputClass}
                                    value={data.contact_email}
                                    onChange={(e) =>
                                        setData('contact_email', e.target.value)
                                    }
                                    placeholder="support@shoppydrive.com"
                                />
                            </SettingsField>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SettingsField label="Address / Location">
                                <input
                                    className={settingsInputClass}
                                    value={data.contact_address}
                                    onChange={(e) =>
                                        setData(
                                            'contact_address',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Dhaka, Bangladesh"
                                />
                            </SettingsField>
                            <SettingsField label="Support Hours">
                                <input
                                    className={settingsInputClass}
                                    value={data.contact_page_support_hours}
                                    onChange={(e) =>
                                        setData(
                                            'contact_page_support_hours',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Sat–Thu, 9am–9pm BST"
                                />
                            </SettingsField>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex items-center gap-3">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-[#c8102e] px-8 text-white hover:bg-[#a50d25]"
                    >
                        {processing ? 'Saving…' : 'Save Changes'}
                    </Button>
                    {recentlySuccessful && (
                        <span className="text-xs font-medium text-emerald-600">
                            Saved.
                        </span>
                    )}
                </div>
            </form>
        </>
    );
}
