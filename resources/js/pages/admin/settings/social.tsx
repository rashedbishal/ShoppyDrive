import { Head, useForm } from '@inertiajs/react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
            <CardHeader className="border-b border-slate-100 pb-4">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    Google Login (OAuth)
                </CardTitle>
            </CardHeader>
            <form onSubmit={handleSubmit}>
                <CardContent className="space-y-4 pt-4">
                    <p className="text-xs text-slate-400">
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
                            className={settingsInputClass}
                            value={data.google_client_id}
                            onChange={(e) =>
                                setData('google_client_id', e.target.value)
                            }
                            placeholder="xxxxxxxxxxxx.apps.googleusercontent.com"
                        />
                    </SettingsField>
                    <SettingsField label="Client Secret">
                        <input
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
                            className={settingsInputClass}
                            value={data.google_redirect_uri}
                            onChange={(e) =>
                                setData('google_redirect_uri', e.target.value)
                            }
                            placeholder={`${window.location.origin}/auth/google/callback`}
                        />
                    </SettingsField>
                </CardContent>
                <div className="flex items-center gap-3 border-t border-slate-100 px-6 py-4">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                    {recentlySuccessful && (
                        <span className="text-xs font-medium text-emerald-600">
                            Saved.
                        </span>
                    )}
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
            <CardHeader className="border-b border-slate-100 pb-4">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    Facebook Login (OAuth)
                </CardTitle>
            </CardHeader>
            <form onSubmit={handleSubmit}>
                <CardContent className="space-y-4 pt-4">
                    <p className="text-xs text-slate-400">
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
                            className={settingsInputClass}
                            value={data.facebook_client_id}
                            onChange={(e) =>
                                setData('facebook_client_id', e.target.value)
                            }
                            placeholder="1234567890123456"
                        />
                    </SettingsField>
                    <SettingsField label="App Secret">
                        <input
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
                            className={settingsInputClass}
                            value={data.facebook_redirect_uri}
                            onChange={(e) =>
                                setData('facebook_redirect_uri', e.target.value)
                            }
                            placeholder={`${window.location.origin}/auth/facebook/callback`}
                        />
                    </SettingsField>
                </CardContent>
                <div className="flex items-center gap-3 border-t border-slate-100 px-6 py-4">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                    {recentlySuccessful && (
                        <span className="text-xs font-medium text-emerald-600">
                            Saved.
                        </span>
                    )}
                </div>
            </form>
        </Card>
    );
}

export default function SocialConfig({ settings }: Props) {
    return (
        <>
            <Head title="Social Config — Admin" />
            <div className="max-w-2xl space-y-5">
                <h1 className="text-xl font-bold text-slate-900">
                    Social Config
                </h1>

                <GoogleLoginCard settings={settings} />
                <FacebookLoginCard settings={settings} />
            </div>
        </>
    );
}
