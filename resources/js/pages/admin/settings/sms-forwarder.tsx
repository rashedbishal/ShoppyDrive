import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useWebPushSms } from '@/hooks/useWebPushSms';

type ForwarderStatus = {
    is_down: boolean;
    has_heartbeat: boolean;
    last_heartbeat_at: string | null;
    minutes_since_heartbeat: number | null;
    last_sms_at: string | null;
    down_after_minutes: number;
};

type PushStatus = {
    configured: boolean;
    subscribed_browsers: number;
    can_manage: boolean;
    last_result: {
        kind: string;
        at: string;
        sent: number;
        expired: number;
        failed: string[];
    } | null;
};

type Props = {
    maskedToken: string;
    fullToken: string | null;
    forwarderStatus: ForwarderStatus;
    pushStatus: PushStatus;
};

const formatMinutes = (minutes: number): string => {
    if (minutes < 1) {
        return 'less than a minute';
    }

    if (minutes < 60) {
        return `${minutes} minute${minutes === 1 ? '' : 's'}`;
    }

    const hours = Math.floor(minutes / 60);

    return hours < 48
        ? `${hours} hour${hours === 1 ? '' : 's'}`
        : `${Math.floor(hours / 24)} days`;
};

function PhoneStatusCard({ status }: { status: ForwarderStatus }) {
    const tone = status.is_down
        ? 'border-red-200 bg-red-50 text-red-900'
        : 'border-emerald-200 bg-emerald-50 text-emerald-900';

    return (
        <div className={`space-y-1 rounded-lg border p-4 text-sm ${tone}`}>
            <p className="font-semibold">
                {status.is_down
                    ? 'Forwarder phone looks OFFLINE — bKash / Nagad are hidden from customers'
                    : 'Forwarder phone is online — bKash / Nagad are available'}
            </p>
            {status.has_heartbeat && status.minutes_since_heartbeat !== null ? (
                <p>
                    Last ping: {formatMinutes(status.minutes_since_heartbeat)}{' '}
                    ago. It counts as offline after {status.down_after_minutes}{' '}
                    minutes without a ping.
                </p>
            ) : (
                <p>
                    No ping from the phone has been received yet, so the site
                    treats it as offline. Open the forwarder app on the phone
                    (with the correct server address and token) to send a ping;
                    bKash / Nagad come back within a minute.
                </p>
            )}
            <p className="text-xs opacity-80">
                Last SMS received:{' '}
                {status.last_sms_at
                    ? new Date(status.last_sms_at).toLocaleString()
                    : 'none yet'}
            </p>
        </div>
    );
}

function SmsNotificationsCard({ status }: { status: PushStatus }) {
    const { props } = usePage<any>();
    const { permission, isSubscribed, enableNotifications } = useWebPushSms(
        status.configured ? (props.vapid_public_key ?? null) : null,
    );

    const handleGenerate = () => {
        const message = status.configured
            ? 'Create new notification keys? Every browser that enabled notifications will have to press "Enable" again.'
            : 'Create the notification keys for this site?';

        if (!confirm(message)) {
            return;
        }

        router.post(route('admin.settings.sms-forwarder.push-keys.generate'));
    };

    const [enableMessage, setEnableMessage] = useState<string | null>(null);
    const [enabling, setEnabling] = useState(false);

    const handleEnable = async () => {
        setEnabling(true);
        setEnableMessage(null);

        const result = await enableNotifications();

        setEnabling(false);

        if (result.ok) {
            window.location.reload();

            return;
        }

        setEnableMessage(result.message);
    };

    const handleTest = () => {
        router.post(
            route('admin.settings.sms-forwarder.push-test'),
            {},
            { preserveScroll: true },
        );
    };

    const last = status.last_result;

    return (
        <Card className="shadow-sm">
            <CardHeader className="border-b border-slate-100">
                <CardTitle className="text-sm font-semibold text-slate-700">
                    Instant SMS Notifications
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 pt-3 text-sm text-slate-600">
                <p className="text-xs text-slate-500">
                    When a bKash / Nagad SMS arrives, your browser can show a
                    notification right away and the Received SMS table updates
                    by itself. This needs a one-time pair of keys, created here
                    (nothing to type or copy), and then each admin presses
                    "Enable" once in the browser they use.
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                            status.configured
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-amber-50 text-amber-800'
                        }`}
                    >
                        {status.configured
                            ? 'Keys are set up'
                            : 'Keys are not set up'}
                    </span>
                    {status.configured && (
                        <span className="text-xs text-slate-500">
                            {status.subscribed_browsers} browser(s) enabled
                        </span>
                    )}
                </div>

                {status.can_manage ? (
                    <div>
                        <Button
                            type="button"
                            variant={status.configured ? 'outline' : 'default'}
                            size="sm"
                            onClick={handleGenerate}
                        >
                            {status.configured
                                ? 'Create new keys'
                                : 'Generate notification keys'}
                        </Button>
                    </div>
                ) : (
                    !status.configured && (
                        <p className="text-xs text-slate-500">
                            Only the main (mother) admin can create the keys.
                        </p>
                    )
                )}

                {status.configured && (
                    <div className="space-y-1 border-t border-slate-100 pt-3">
                        <p className="text-xs font-semibold text-slate-700">
                            This browser
                        </p>
                        {permission === 'unsupported' && (
                            <p className="text-xs text-slate-500">
                                This browser does not support notifications.
                            </p>
                        )}
                        {permission === 'denied' && (
                            <p className="text-xs text-slate-500">
                                Notifications are blocked for this site. Allow
                                them in the browser's site settings (the lock
                                icon next to the address), then reload.
                            </p>
                        )}
                        {permission === 'granted' && isSubscribed && (
                            <p className="text-xs font-medium text-emerald-700">
                                Notifications are ON for this browser.
                            </p>
                        )}
                        {(permission === 'default' ||
                            (permission === 'granted' && !isSubscribed)) && (
                            <Button
                                type="button"
                                size="sm"
                                disabled={enabling}
                                onClick={() => void handleEnable()}
                            >
                                {enabling
                                    ? 'Enabling…'
                                    : 'Enable notifications on this browser'}
                            </Button>
                        )}
                        {enableMessage && (
                            <p className="text-xs font-medium text-red-600">
                                {enableMessage}
                            </p>
                        )}
                        <div className="pt-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleTest}
                            >
                                Send test notification
                            </Button>
                        </div>
                        <p className="text-xs text-slate-500">
                            {last === null
                                ? 'No push has been sent yet.'
                                : `Last push (${last.kind === 'test' ? 'test' : 'SMS'}, ${new Date(last.at).toLocaleString()}): ${
                                      last.failed.length > 0
                                          ? `FAILED - ${last.failed[0]}`
                                          : last.sent > 0
                                            ? `delivered to ${last.sent} browser(s)`
                                            : last.expired > 0
                                              ? 'the browser sign-up had expired'
                                              : 'no browser to send to'
                                  }`}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function SmsForwarderSettings({
    maskedToken,
    fullToken,
    forwarderStatus,
    pushStatus,
}: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as
        | { success?: string; newToken?: string }
        | undefined;

    const [showToken, setShowToken] = useState(false);

    const origin = typeof window === 'undefined' ? '' : window.location.origin;
    const endpoints = [
        { label: 'Receive SMS', path: '/api/sms/receive' },
        { label: 'Heartbeat', path: '/api/sms/heartbeat' },
    ];

    const handleRegenerate = () => {
        if (
            !confirm(
                'Are you sure? You will need to update the token in your Android app.',
            )
        ) {
            return;
        }

        router.post(route('admin.settings.sms-forwarder.regenerate'));
    };

    const copyToClipboard = (text: string) => {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);

            return;
        }

        const el = document.createElement('textarea');
        el.value = text;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.focus();
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    };

    const inputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-[#c8102e] bg-slate-50 text-slate-700';

    return (
        <>
            <Head title="SMS Forwarder Settings — Admin" />
            <div className="space-y-5">
                <h1 className="text-xl font-bold text-slate-900">
                    SMS Forwarder Settings
                </h1>

                <PhoneStatusCard status={forwarderStatus} />

                <SmsNotificationsCard status={pushStatus} />

                {flash?.newToken && (
                    <div className="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div className="flex items-start gap-2">
                            <svg
                                className="mt-0.5 size-5 shrink-0 text-amber-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                                />
                            </svg>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-semibold text-amber-800">
                                    New token generated — copy it now!
                                </p>
                                <p className="mt-0.5 text-xs text-amber-600">
                                    Update your Android app with this token. It
                                    will be hidden again after you leave this
                                    page.
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                readOnly
                                className={inputClass}
                                value={flash.newToken}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    copyToClipboard(flash!.newToken!)
                                }
                                className="shrink-0"
                            >
                                Copy
                            </Button>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Bearer Token
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <p className="text-xs text-slate-500">
                                This token authenticates your Android SMS
                                forwarder app. Set it as the Bearer token in
                                your app's HTTP header.
                            </p>

                            <div className="space-y-2">
                                <label className="text-sm font-medium text-slate-700">
                                    Current Token
                                </label>
                                <div className="flex items-center gap-2">
                                    <input
                                        readOnly
                                        className={inputClass}
                                        value={
                                            showToken && fullToken
                                                ? fullToken
                                                : maskedToken
                                        }
                                    />
                                    {fullToken && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setShowToken((v) => !v)
                                                }
                                                className="shrink-0"
                                            >
                                                {showToken
                                                    ? 'Hide'
                                                    : 'Show Full Token'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    copyToClipboard(fullToken)
                                                }
                                                className="shrink-0"
                                            >
                                                Copy
                                            </Button>
                                        </>
                                    )}
                                </div>
                                <p className="text-xs text-slate-400">
                                    {fullToken
                                        ? 'Only the last 8 characters are shown for security.'
                                        : 'You have view-only access. Ask the owner for update access to reveal or regenerate this token.'}
                                </p>
                            </div>

                            {fullToken && (
                                <div className="border-t border-slate-100 pt-2">
                                    <Button
                                        type="button"
                                        onClick={handleRegenerate}
                                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                    >
                                        Regenerate Token
                                    </Button>
                                    <p className="mt-2 text-xs text-slate-400">
                                        Regenerating will invalidate the old
                                        token. You must update your Android app
                                        immediately.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Setup in your Android app
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="space-y-2">
                                {endpoints.map((endpoint) => (
                                    <div
                                        key={endpoint.path}
                                        className="space-y-1"
                                    >
                                        <p className="text-xs font-medium text-slate-700">
                                            {endpoint.label}
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <code className="min-w-0 flex-1 truncate rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">
                                                POST {origin}
                                                {endpoint.path}
                                            </code>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    copyToClipboard(
                                                        `${origin}${endpoint.path}`,
                                                    )
                                                }
                                                className="shrink-0"
                                            >
                                                Copy
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <ol className="list-decimal space-y-1.5 border-t border-slate-100 pt-3 pl-4 text-xs text-slate-500">
                                <li>Copy the token on the left.</li>
                                <li>
                                    In the app, set it as the Bearer token in
                                    the HTTP header and use the URLs above.
                                </li>
                                <li>
                                    After regenerating, update the app
                                    immediately. The old token stops working.
                                </li>
                            </ol>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
