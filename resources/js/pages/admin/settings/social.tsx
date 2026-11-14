import { Head, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { AdminFade } from '@/components/admin/admin-motion';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';

type Props = {
    settings: Record<string, string | Record<string, string> | null>;
};

function GoogleLoginCard({ settings }: Props) {
    const { data, setData, put, processing, recentlySuccessful } = useForm({
        google_client_id: (settings.google_client_id as string) ?? '',
        google_client_secret: (settings.google_client_secret as string) ?? '',
        google_redirect_uri: (settings.google_redirect_uri as string) ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <Card className="shadow-sm">
            <CardHeader className="border-b border-slate-100">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    Google Login (OAuth)
                </CardTitle>
            </CardHeader>
            <form onSubmit={handleSubmit}>
                <CardContent className="space-y-4 pt-3">
                    <p className="text-xs text-slate-600">
                        Create an OAuth client in the{' '}
                        <a
                            href="https://console.cloud.google.com/apis/credentials"
                            target="_blank"
                            rel="noreferrer"
                            className="underline"
                        >
                            Google Cloud Console
                        </a>
                        . "Continue with Google" is hidden on the login and
                        register pages until a Client ID is set here.
                    </p>
                    <SettingsField label="Client ID">
                        <input
                            maxLength={255}
                            className={settingsInputClass}
                            value={data.google_client_id}
                            onChange={(e) =>
                                setData('google_client_id', e.target.value)
                            }
                            placeholder="xxxxxxxxxxxx.apps.googleusercontent.com"
                        />
                        <CharCount
                            value={data.google_client_id}
                            max={255}
                            className="mt-1 block text-right"
                        />
                    </SettingsField>
                    <SettingsField label="Client Secret">
                        <input
                            maxLength={255}
                            type="password"
                            autoComplete="off"
                            className={settingsInputClass}
                            value={data.google_client_secret}
                            onChange={(e) =>
                                setData('google_client_secret', e.target.value)
                            }
                            placeholder="GOCSPX-..."
                        />
                    </SettingsField>
                    <SettingsField
                        label="Redirect URI"
                        hint="Must match an Authorized redirect URI registered in the Google Cloud Console"
                    >
                        <input
                            maxLength={300}
                            className={settingsInputClass}
                            value={data.google_redirect_uri}
                            onChange={(e) =>
                                setData('google_redirect_uri', e.target.value)
                            }
                            placeholder={`${window.location.origin}/auth/google/callback`}
                        />
                        <CharCount
                            value={data.google_redirect_uri}
                            max={300}
                            className="mt-1 block text-right"
                        />
                    </SettingsField>
                </CardContent>
                <div className="flex items-center gap-3 border-t border-slate-100 px-6 py-4">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-brand text-brand-foreground hover:bg-brand-hover"
                    >
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                    <AnimatePresence initial={false}>
                        {recentlySuccessful && (
                            <AdminFade key="saved-0">
                                <span className="text-xs font-medium text-emerald-600">
                                    Saved.
                                </span>
                            </AdminFade>
                        )}
                    </AnimatePresence>
                </div>
            </form>
        </Card>
    );
}

function FacebookLoginCard({ settings }: Props) {
    const { data, setData, put, processing, recentlySuccessful } = useForm({
        facebook_client_id: (settings.facebook_client_id as string) ?? '',
        facebook_client_secret:
            (settings.facebook_client_secret as string) ?? '',
        facebook_redirect_uri: (settings.facebook_redirect_uri as string) ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <Card className="shadow-sm">
            <CardHeader className="border-b border-slate-100">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    Facebook Login (OAuth)
                </CardTitle>
            </CardHeader>
            <form onSubmit={handleSubmit}>
                <CardContent className="space-y-4 pt-3">
                    <p className="text-xs text-slate-600">
                        Create an app in{' '}
                        <a
                            href="https://developers.facebook.com/apps"
                            target="_blank"
                            rel="noreferrer"
                            className="underline"
                        >
                            Meta for Developers
                        </a>
                        . "Continue with Facebook" is hidden on the login and
                        register pages until a Client ID is set here.
                    </p>
                    <SettingsField label="App ID">
                        <input
                            maxLength={255}
                            className={settingsInputClass}
                            value={data.facebook_client_id}
                            onChange={(e) =>
                                setData('facebook_client_id', e.target.value)
                            }
                            placeholder="1234567890123456"
                        />
                        <CharCount
                            value={data.facebook_client_id}
                            max={255}
                            className="mt-1 block text-right"
                        />
                    </SettingsField>
                    <SettingsField label="App Secret">
                        <input
                            maxLength={255}
                            type="password"
                            autoComplete="off"
                            className={settingsInputClass}
                            value={data.facebook_client_secret}
                            onChange={(e) =>
                                setData(
                                    'facebook_client_secret',
                                    e.target.value,
                                )
                            }
                            placeholder="App secret"
                        />
                    </SettingsField>
                    <SettingsField
                        label="Redirect URI"
                        hint="Must match a Valid OAuth Redirect URI registered in Facebook Login settings"
                    >
                        <input
                            maxLength={300}
                            className={settingsInputClass}
                            value={data.facebook_redirect_uri}
                            onChange={(e) =>
                                setData('facebook_redirect_uri', e.target.value)
                            }
                            placeholder={`${window.location.origin}/auth/facebook/callback`}
                        />
                        <CharCount
                            value={data.facebook_redirect_uri}
                            max={300}
                            className="mt-1 block text-right"
                        />
                    </SettingsField>
                </CardContent>
                <div className="flex items-center gap-3 border-t border-slate-100 px-6 py-4">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-brand text-brand-foreground hover:bg-brand-hover"
                    >
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                    <AnimatePresence initial={false}>
                        {recentlySuccessful && (
                            <AdminFade key="saved-1">
                                <span className="text-xs font-medium text-emerald-600">
                                    Saved.
                                </span>
                            </AdminFade>
                        )}
                    </AnimatePresence>
                </div>
            </form>
        </Card>
    );
}

export default function SocialConfig({ settings }: Props) {
    return (
        <>
            <Head title="Social Config — Admin" />
            <div className="space-y-4">
                <h1 className="text-xl font-bold text-slate-900">
                    Social Config
                </h1>

                <div className="grid gap-4 lg:grid-cols-2">
                    <GoogleLoginCard settings={settings} />
                    <FacebookLoginCard settings={settings} />
                </div>
            </div>
        </>
    );
}
