import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    maskedToken: string;
    fullToken: string | null;
};

export default function SmsForwarderSettings({
    maskedToken,
    fullToken,
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

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
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
