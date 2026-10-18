import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';

type PathaoStore = {
    store_id: number;
    store_name: string;
    store_address: string;
    is_default_store: boolean;
};

type Props = {
    settings: Record<string, string | Record<string, string> | null>;
};

function Field({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
                {label}
            </label>
            {hint && <p className="mb-1.5 text-xs text-slate-400">{hint}</p>}
            {children}
        </div>
    );
}

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
        logo:
            (settings.logo as string | null) ?? (null as File | string | null),
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
        google_client_id: (settings.google_client_id as string) ?? '',
        google_client_secret: (settings.google_client_secret as string) ?? '',
        google_redirect_uri: (settings.google_redirect_uri as string) ?? '',
        facebook_client_id: (settings.facebook_client_id as string) ?? '',
        facebook_client_secret:
            (settings.facebook_client_secret as string) ?? '',
        facebook_redirect_uri: (settings.facebook_redirect_uri as string) ?? '',
        pathao_environment:
            (settings.pathao_environment as string) ?? 'sandbox',
        pathao_client_id: (settings.pathao_client_id as string) ?? '',
        pathao_client_secret: (settings.pathao_client_secret as string) ?? '',
        pathao_username: (settings.pathao_username as string) ?? '',
        pathao_password: (settings.pathao_password as string) ?? '',
        pathao_store_id: (settings.pathao_store_id as string) ?? '',
        steadfast_api_key: (settings.steadfast_api_key as string) ?? '',
        steadfast_secret_key: (settings.steadfast_secret_key as string) ?? '',
        steadfast_webhook_secret:
            (settings.steadfast_webhook_secret as string) ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.settings.update'), { forceFormData: true });
    };

    const [pathaoStores, setPathaoStores] = useState<PathaoStore[]>([]);
    const [pathaoStoresLoading, setPathaoStoresLoading] = useState(false);
    const [pathaoStoresError, setPathaoStoresError] = useState<string | null>(
        null,
    );

    const loadPathaoStores = async () => {
        setPathaoStoresLoading(true);
        setPathaoStoresError(null);

        try {
            const res = await fetch(route('admin.settings.pathao.stores'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const json = await res.json();

            if (!res.ok) {
                setPathaoStoresError(json.message ?? 'Could not load stores.');

                return;
            }

            setPathaoStores(json.stores ?? []);

            if (!data.pathao_store_id) {
                const defaultStore = (json.stores ?? []).find(
                    (s: PathaoStore) => s.is_default_store,
                );

                if (defaultStore) {
                    setData('pathao_store_id', String(defaultStore.store_id));
                }
            }
        } catch {
            setPathaoStoresError('Could not reach the server.');
        } finally {
            setPathaoStoresLoading(false);
        }
    };

    const [steadfastBalance, setSteadfastBalance] = useState<number | null>(
        null,
    );
    const [steadfastBalanceLoading, setSteadfastBalanceLoading] =
        useState(false);
    const [steadfastBalanceError, setSteadfastBalanceError] = useState<
        string | null
    >(null);

    const checkSteadfastBalance = async () => {
        setSteadfastBalanceLoading(true);
        setSteadfastBalanceError(null);

        try {
            const res = await fetch(route('admin.settings.steadfast.balance'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const json = await res.json();

            if (!res.ok) {
                setSteadfastBalanceError(
                    json.message ?? 'Could not reach Steadfast.',
                );

                return;
            }

            setSteadfastBalance(json.current_balance ?? 0);
        } catch {
            setSteadfastBalanceError('Could not reach the server.');
        } finally {
            setSteadfastBalanceLoading(false);
        }
    };

    const inputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    return (
        <>
            <Head title="Settings — Admin" />
            <div className="space-y-5">
                <h1 className="text-xl font-bold text-slate-900">Settings</h1>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-5">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                General
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Site Name">
                                <input
                                    className={inputClass}
                                    value={data.site_name}
                                    onChange={(e) =>
                                        setData('site_name', e.target.value)
                                    }
                                    placeholder="ShoppyDrive"
                                />
                            </Field>
                            <Field
                                label="Brand Logo"
                                hint="Displayed in the website header and CMS. JPG, PNG, or WEBP — max 2 MB."
                            >
                                <ImageUpload
                                    value={data.logo}
                                    onChange={(file) => setData('logo', file)}
                                    previewClassName="h-32"
                                />
                            </Field>
                            <Field label="Currency Symbol">
                                <input
                                    className={`${inputClass} max-w-[120px]`}
                                    value={data.currency_symbol}
                                    onChange={(e) =>
                                        setData(
                                            'currency_symbol',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="৳"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Contact
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Contact Email">
                                <input
                                    type="email"
                                    className={inputClass}
                                    value={data.contact_email}
                                    onChange={(e) =>
                                        setData('contact_email', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Contact Phone">
                                <input
                                    type="tel"
                                    className={inputClass}
                                    value={data.contact_phone}
                                    onChange={(e) =>
                                        setData('contact_phone', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Contact Address">
                                <textarea
                                    rows={3}
                                    className={
                                        inputClass + ' h-auto resize-none py-2'
                                    }
                                    value={data.contact_address}
                                    onChange={(e) =>
                                        setData(
                                            'contact_address',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 123 Main Street, Dhaka, Bangladesh"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Social Links
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Facebook">
                                <input
                                    className={inputClass}
                                    value={data.social_links.facebook}
                                    onChange={(e) =>
                                        setData('social_links', {
                                            ...data.social_links,
                                            facebook: e.target.value,
                                        })
                                    }
                                    placeholder="https://facebook.com/..."
                                />
                            </Field>
                            <Field label="Instagram">
                                <input
                                    className={inputClass}
                                    value={data.social_links.instagram}
                                    onChange={(e) =>
                                        setData('social_links', {
                                            ...data.social_links,
                                            instagram: e.target.value,
                                        })
                                    }
                                    placeholder="https://instagram.com/..."
                                />
                            </Field>
                            <Field label="Twitter / X">
                                <input
                                    className={inputClass}
                                    value={data.social_links.twitter}
                                    onChange={(e) =>
                                        setData('social_links', {
                                            ...data.social_links,
                                            twitter: e.target.value,
                                        })
                                    }
                                    placeholder="https://twitter.com/..."
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Integrations
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field
                                label="Google Analytics ID"
                                hint="e.g. G-XXXXXXXX or UA-XXXXXXXX"
                            >
                                <input
                                    className={inputClass}
                                    value={data.google_analytics_id}
                                    onChange={(e) =>
                                        setData(
                                            'google_analytics_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="G-XXXXXXXXXX"
                                />
                            </Field>
                            <Field
                                label="Meta Pixel ID"
                                hint="Facebook Pixel tracking ID"
                            >
                                <input
                                    className={inputClass}
                                    value={data.meta_pixel_id}
                                    onChange={(e) =>
                                        setData('meta_pixel_id', e.target.value)
                                    }
                                    placeholder="123456789"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Google Login (OAuth)
                            </CardTitle>
                        </CardHeader>
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
                                . "Continue with Google" is hidden on the login
                                and register pages until a Client ID is set
                                here.
                            </p>
                            <Field label="Client ID">
                                <input
                                    className={inputClass}
                                    value={data.google_client_id}
                                    onChange={(e) =>
                                        setData(
                                            'google_client_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="xxxxxxxxxxxx.apps.googleusercontent.com"
                                />
                            </Field>
                            <Field label="Client Secret">
                                <input
                                    type="password"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={data.google_client_secret}
                                    onChange={(e) =>
                                        setData(
                                            'google_client_secret',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="GOCSPX-..."
                                />
                            </Field>
                            <Field
                                label="Redirect URI"
                                hint="Must match an Authorized redirect URI registered in the Google Cloud Console"
                            >
                                <input
                                    className={inputClass}
                                    value={data.google_redirect_uri}
                                    onChange={(e) =>
                                        setData(
                                            'google_redirect_uri',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={`${window.location.origin}/auth/google/callback`}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Facebook Login (OAuth)
                            </CardTitle>
                        </CardHeader>
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
                                . "Continue with Facebook" is hidden on the
                                login and register pages until a Client ID is
                                set here.
                            </p>
                            <Field label="App ID">
                                <input
                                    className={inputClass}
                                    value={data.facebook_client_id}
                                    onChange={(e) =>
                                        setData(
                                            'facebook_client_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="1234567890123456"
                                />
                            </Field>
                            <Field label="App Secret">
                                <input
                                    type="password"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={data.facebook_client_secret}
                                    onChange={(e) =>
                                        setData(
                                            'facebook_client_secret',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="App secret"
                                />
                            </Field>
                            <Field
                                label="Redirect URI"
                                hint="Must match a Valid OAuth Redirect URI registered in Facebook Login settings"
                            >
                                <input
                                    className={inputClass}
                                    value={data.facebook_redirect_uri}
                                    onChange={(e) =>
                                        setData(
                                            'facebook_redirect_uri',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={`${window.location.origin}/auth/facebook/callback`}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Pathao Courier
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <p className="text-xs text-slate-400">
                                Get these from your{' '}
                                <a
                                    href="https://merchant.pathao.com"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline"
                                >
                                    Pathao Merchant
                                </a>{' '}
                                account (or the sandbox portal for testing).
                                Used to send Cash-on-Delivery orders to Pathao
                                for pickup and delivery from the order detail
                                page.
                            </p>
                            <Field label="Environment">
                                <select
                                    className={inputClass}
                                    value={data.pathao_environment}
                                    onChange={(e) =>
                                        setData(
                                            'pathao_environment',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="sandbox">
                                        Sandbox (testing)
                                    </option>
                                    <option value="production">
                                        Production (live)
                                    </option>
                                </select>
                            </Field>
                            <Field label="Client ID">
                                <input
                                    className={inputClass}
                                    value={data.pathao_client_id}
                                    onChange={(e) =>
                                        setData(
                                            'pathao_client_id',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Pathao Client ID"
                                />
                            </Field>
                            <Field label="Client Secret">
                                <input
                                    type="password"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={data.pathao_client_secret}
                                    onChange={(e) =>
                                        setData(
                                            'pathao_client_secret',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Pathao Client Secret"
                                />
                            </Field>
                            <Field
                                label="Account Email"
                                hint="Your Pathao Merchant login email"
                            >
                                <input
                                    type="email"
                                    className={inputClass}
                                    value={data.pathao_username}
                                    onChange={(e) =>
                                        setData(
                                            'pathao_username',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="merchant@example.com"
                                />
                            </Field>
                            <Field
                                label="Account Password"
                                hint="Your Pathao Merchant login password"
                            >
                                <input
                                    type="password"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={data.pathao_password}
                                    onChange={(e) =>
                                        setData(
                                            'pathao_password',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Store"
                                hint="Sets the pickup location for orders. Fetched from your Pathao account — save your Client ID, Secret, Account Email, and Password above first."
                            >
                                <div className="space-y-2">
                                    {pathaoStores.length > 0 ? (
                                        <select
                                            className={inputClass}
                                            value={data.pathao_store_id}
                                            onChange={(e) =>
                                                setData(
                                                    'pathao_store_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Select a store…
                                            </option>
                                            {pathaoStores.map((store) => (
                                                <option
                                                    key={store.store_id}
                                                    value={store.store_id}
                                                >
                                                    {store.store_name} —{' '}
                                                    {store.store_address}
                                                    {store.is_default_store
                                                        ? ' (default)'
                                                        : ''}
                                                </option>
                                            ))}
                                        </select>
                                    ) : data.pathao_store_id ? (
                                        <p className="text-xs text-slate-500">
                                            Currently set to store ID{' '}
                                            <span className="font-mono">
                                                {data.pathao_store_id}
                                            </span>
                                            . Click below to load your stores
                                            and change it.
                                        </p>
                                    ) : (
                                        <p className="text-xs text-slate-400">
                                            No store selected yet.
                                        </p>
                                    )}

                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={pathaoStoresLoading}
                                        onClick={loadPathaoStores}
                                    >
                                        {pathaoStoresLoading
                                            ? 'Loading…'
                                            : pathaoStores.length > 0
                                              ? 'Reload Stores'
                                              : 'Load Stores'}
                                    </Button>

                                    {pathaoStoresError && (
                                        <p className="text-xs text-red-600">
                                            {pathaoStoresError}
                                        </p>
                                    )}
                                </div>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Steadfast Courier
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <p className="text-xs text-slate-400">
                                Get your Api Key and Secret Key from{' '}
                                <a
                                    href="https://portal.packzy.com"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline"
                                >
                                    Steadfast's merchant portal
                                </a>
                                . Used to send Cash-on-Delivery orders to
                                Steadfast for pickup and delivery from the
                                order detail page.
                            </p>
                            <Field label="Api Key">
                                <input
                                    className={inputClass}
                                    value={data.steadfast_api_key}
                                    onChange={(e) =>
                                        setData(
                                            'steadfast_api_key',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Steadfast Api Key"
                                />
                            </Field>
                            <Field label="Secret Key">
                                <input
                                    type="password"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={data.steadfast_secret_key}
                                    onChange={(e) =>
                                        setData(
                                            'steadfast_secret_key',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Steadfast Secret Key"
                                />
                            </Field>
                            <Field
                                label="Webhook Secret (optional)"
                                hint="Paste the same value into Steadfast's webhook settings so delivery status updates can be verified. Leave blank to accept unverified webhooks."
                            >
                                <input
                                    type="password"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={data.steadfast_webhook_secret}
                                    onChange={(e) =>
                                        setData(
                                            'steadfast_webhook_secret',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Webhook Callback URL"
                                hint="Set this as the callback URL in Steadfast's webhook settings."
                            >
                                <input
                                    readOnly
                                    className={`${inputClass} bg-slate-50 text-slate-500`}
                                    value={`${window.location.origin}/api/steadfast/webhook`}
                                    onFocus={(e) => e.target.select()}
                                />
                            </Field>
                            <div className="space-y-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={steadfastBalanceLoading}
                                    onClick={checkSteadfastBalance}
                                >
                                    {steadfastBalanceLoading
                                        ? 'Checking…'
                                        : 'Check Balance'}
                                </Button>

                                {steadfastBalance !== null && (
                                    <p className="text-xs text-emerald-600">
                                        Connected. Current balance: ৳
                                        {steadfastBalance.toLocaleString()}
                                    </p>
                                )}

                                {steadfastBalanceError && (
                                    <p className="text-xs text-red-600">
                                        {steadfastBalanceError}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Chat Widgets
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field
                                label="WhatsApp Number"
                                hint="Phone number with country code, e.g. +8801700000000"
                            >
                                <input
                                    type="tel"
                                    className={inputClass}
                                    value={data.whatsapp_number}
                                    onChange={(e) =>
                                        setData(
                                            'whatsapp_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="+8801700000000"
                                />
                            </Field>
                            <Field
                                label="Facebook Messenger"
                                hint="Your Facebook page username or full m.me URL, e.g. shoppydrive"
                            >
                                <input
                                    className={inputClass}
                                    value={data.messenger_link}
                                    onChange={(e) =>
                                        setData(
                                            'messenger_link',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="shoppydrive or https://m.me/shoppydrive"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Mobile Banking Payment
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field
                                label="Bkash Number"
                                hint="Owner's Bkash number customers will send money to"
                            >
                                <input
                                    type="tel"
                                    className={inputClass}
                                    value={data.bkash_number}
                                    onChange={(e) =>
                                        setData('bkash_number', e.target.value)
                                    }
                                    placeholder="01700000000"
                                />
                            </Field>
                            <Field
                                label="Nagad Number"
                                hint="Owner's Nagad number customers will send money to"
                            >
                                <input
                                    type="tel"
                                    className={inputClass}
                                    value={data.nagad_number}
                                    onChange={(e) =>
                                        setData('nagad_number', e.target.value)
                                    }
                                    placeholder="01700000000"
                                />
                            </Field>
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
