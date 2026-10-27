import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';

type Props = {
    settings: Record<string, string | Record<string, string> | null>;
};

type PathaoStore = {
    store_id: number;
    store_name: string;
    store_address: string;
    is_default_store: boolean;
};

function PathaoCourierCard({ settings }: Props) {
    const { data, setData, put, processing, recentlySuccessful } = useForm({
        pathao_environment:
            (settings.pathao_environment as string) ?? 'sandbox',
        pathao_client_id: (settings.pathao_client_id as string) ?? '',
        pathao_client_secret: (settings.pathao_client_secret as string) ?? '',
        pathao_username: (settings.pathao_username as string) ?? '',
        pathao_password: (settings.pathao_password as string) ?? '',
        pathao_store_id: (settings.pathao_store_id as string) ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
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

    return (
        <Card className="shadow-sm">
            <CardHeader className="border-b border-slate-100 pb-4">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    Pathao Courier
                </CardTitle>
            </CardHeader>
            <form onSubmit={handleSubmit}>
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
                        account (or the sandbox portal for testing). Used to
                        send Cash-on-Delivery orders to Pathao for pickup and
                        delivery from the order detail page.
                    </p>
                    <SettingsField label="Environment">
                        <Select
                            selectClassName={settingsInputClass}
                            value={data.pathao_environment}
                            onChange={(e) =>
                                setData('pathao_environment', e.target.value)
                            }
                        >
                            <option value="sandbox">Sandbox (testing)</option>
                            <option value="production">
                                Production (live)
                            </option>
                        </Select>
                    </SettingsField>
                    <SettingsField label="Client ID">
                        <input
                            className={settingsInputClass}
                            value={data.pathao_client_id}
                            onChange={(e) =>
                                setData('pathao_client_id', e.target.value)
                            }
                            placeholder="Pathao Client ID"
                        />
                    </SettingsField>
                    <SettingsField label="Client Secret">
                        <input
                            type="password"
                            autoComplete="off"
                            className={settingsInputClass}
                            value={data.pathao_client_secret}
                            onChange={(e) =>
                                setData(
                                    'pathao_client_secret',
                                    e.target.value,
                                )
                            }
                            placeholder="Pathao Client Secret"
                        />
                    </SettingsField>
                    <SettingsField
                        label="Account Email"
                        hint="Your Pathao Merchant login email"
                    >
                        <input
                            type="email"
                            className={settingsInputClass}
                            value={data.pathao_username}
                            onChange={(e) =>
                                setData('pathao_username', e.target.value)
                            }
                            placeholder="merchant@example.com"
                        />
                    </SettingsField>
                    <SettingsField
                        label="Account Password"
                        hint="Your Pathao Merchant login password"
                    >
                        <input
                            type="password"
                            autoComplete="off"
                            className={settingsInputClass}
                            value={data.pathao_password}
                            onChange={(e) =>
                                setData('pathao_password', e.target.value)
                            }
                        />
                    </SettingsField>
                    <SettingsField
                        label="Store"
                        hint="Sets the pickup location for orders. Fetched from your Pathao account — save your Client ID, Secret, Account Email, and Password above first."
                    >
                        <div className="space-y-2">
                            {pathaoStores.length > 0 ? (
                                <Select
                                    selectClassName={settingsInputClass}
                                    value={data.pathao_store_id}
                                    onChange={(e) =>
                                        setData(
                                            'pathao_store_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Select a store…</option>
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
                                </Select>
                            ) : data.pathao_store_id ? (
                                <p className="text-xs text-slate-500">
                                    Currently set to store ID{' '}
                                    <span className="font-mono">
                                        {data.pathao_store_id}
                                    </span>
                                    . Click below to load your stores and
                                    change it.
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

function ShippingRateCard({
    zoneKey,
    title,
    hint,
}: {
    zoneKey: 'shipping_rate_inside_dhaka' | 'shipping_rate_outside_dhaka';
    title: string;
    hint: string;
}) {
    const page = usePage<{ settings: Record<string, unknown> }>();
    const settings = page.props.settings;
    const defaultRate = zoneKey === 'shipping_rate_inside_dhaka' ? 60 : 120;

    const { data, setData, put, processing } = useForm({
        [zoneKey]: String(settings[zoneKey] ?? defaultRate),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <Card className="shadow-sm">
            <CardHeader className="border-b border-slate-100 pb-4">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    {title}
                </CardTitle>
            </CardHeader>
            <form onSubmit={handleSubmit}>
                <CardContent className="space-y-4 pt-4">
                    <SettingsField label="Delivery Charge (৳)" hint={hint}>
                        <input
                            type="number"
                            min={0}
                            step="0.01"
                            className={settingsInputClass}
                            value={data[zoneKey]}
                            onChange={(e) =>
                                setData(zoneKey, e.target.value)
                            }
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
                </div>
            </form>
        </Card>
    );
}

function SteadfastCourierCard({ settings }: Props) {
    const { data, setData, put, processing, recentlySuccessful } = useForm({
        steadfast_api_key: (settings.steadfast_api_key as string) ?? '',
        steadfast_secret_key: (settings.steadfast_secret_key as string) ?? '',
        steadfast_webhook_secret:
            (settings.steadfast_webhook_secret as string) ?? '',
        steadfast_webhook_url: (settings.steadfast_webhook_url as string) ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
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

    return (
        <Card className="shadow-sm">
            <CardHeader className="border-b border-slate-100 pb-4">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    Steadfast Courier
                </CardTitle>
            </CardHeader>
            <form onSubmit={handleSubmit}>
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
                        . Used to send Cash-on-Delivery orders to Steadfast
                        for pickup and delivery from the order detail page.
                    </p>
                    <SettingsField label="Api Key">
                        <input
                            className={settingsInputClass}
                            value={data.steadfast_api_key}
                            onChange={(e) =>
                                setData('steadfast_api_key', e.target.value)
                            }
                            placeholder="Steadfast Api Key"
                        />
                    </SettingsField>
                    <SettingsField label="Secret Key">
                        <input
                            type="password"
                            autoComplete="off"
                            className={settingsInputClass}
                            value={data.steadfast_secret_key}
                            onChange={(e) =>
                                setData(
                                    'steadfast_secret_key',
                                    e.target.value,
                                )
                            }
                            placeholder="Steadfast Secret Key"
                        />
                    </SettingsField>
                    <SettingsField
                        label="Webhook Secret (optional)"
                        hint="Paste the same value into Steadfast's webhook settings so delivery status updates can be verified. Leave blank to accept unverified webhooks."
                    >
                        <input
                            type="password"
                            autoComplete="off"
                            className={settingsInputClass}
                            value={data.steadfast_webhook_secret}
                            onChange={(e) =>
                                setData(
                                    'steadfast_webhook_secret',
                                    e.target.value,
                                )
                            }
                        />
                    </SettingsField>
                    <SettingsField
                        label="Webhook Callback URL"
                        hint="Set this as the callback URL in Steadfast's webhook settings. Leave blank to use this domain by default — the backend endpoint itself is always /api/steadfast/webhook regardless of what's saved here."
                    >
                        <input
                            className={settingsInputClass}
                            value={data.steadfast_webhook_url}
                            onChange={(e) =>
                                setData(
                                    'steadfast_webhook_url',
                                    e.target.value,
                                )
                            }
                            onFocus={(e) => e.target.select()}
                            placeholder={`${window.location.origin}/api/steadfast/webhook`}
                        />
                    </SettingsField>
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

export default function CourierConfig({ settings }: Props) {
    return (
        <>
            <Head title="Courier Config — Admin" />
            <div className="max-w-2xl space-y-5">
                <h1 className="text-xl font-bold text-slate-900">
                    Courier Config
                </h1>

                <ShippingRateCard
                    zoneKey="shipping_rate_inside_dhaka"
                    title="Inside Dhaka City Corporation"
                    hint="Flat delivery charge for addresses inside Dhaka City Corporation."
                />
                <ShippingRateCard
                    zoneKey="shipping_rate_outside_dhaka"
                    title="Outside Dhaka City Corporation"
                    hint="Flat delivery charge for every other district, and areas of Dhaka outside the city corporation."
                />
                <PathaoCourierCard settings={settings} />
                <SteadfastCourierCard settings={settings} />
            </div>
        </>
    );
}
