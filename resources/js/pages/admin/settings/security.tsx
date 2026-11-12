import { Head, useForm } from '@inertiajs/react';
import { Activity, Info, Lock, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type LimitField =
    | 'orders_per_network_times'
    | 'orders_per_network_minutes'
    | 'orders_per_phone_times'
    | 'orders_per_phone_minutes'
    | 'payment_per_order_times'
    | 'payment_per_order_minutes'
    | 'payment_per_network_times'
    | 'payment_per_network_minutes'
    | 'signups_per_network_times'
    | 'signups_per_network_minutes'
    | 'password_reset_per_network_times'
    | 'password_reset_per_network_minutes'
    | 'password_reset_per_email_times'
    | 'password_reset_per_email_minutes'
    | 'coupon_checks_times'
    | 'coupon_checks_minutes'
    | 'sign_in_per_network_times'
    | 'sign_in_per_network_minutes'
    | 'contact_messages_times'
    | 'contact_messages_minutes'
    | 'newsletter_signups_times'
    | 'newsletter_signups_minutes'
    | 'newsletter_checks_times'
    | 'newsletter_checks_minutes'
    | 'newsletter_manage_per_network_times'
    | 'newsletter_manage_per_network_minutes'
    | 'newsletter_manage_per_email_times'
    | 'newsletter_manage_per_email_minutes'
    | 'checkout_tracking_times'
    | 'checkout_tracking_minutes'
    | 'order_tracking_times'
    | 'order_tracking_minutes'
    | 'search_suggestions_times'
    | 'search_suggestions_minutes'
    | 'product_browsing_times'
    | 'product_browsing_minutes'
    | 'invoice_downloads_times'
    | 'invoice_downloads_minutes'
    | 'reviews_times'
    | 'reviews_minutes'
    | 'password_checks_times'
    | 'password_checks_minutes'
    | 'account_actions_times'
    | 'account_actions_minutes';

type Limits = Record<LimitField, number>;

type Props = {
    limits: Limits;
    bounds: Record<LimitField, { default: number; min: number; max: number }>;
    canEdit: boolean;
    visitorIp: string;
    heartbeatPingUrl: string | null;
    heartbeatConfigured: boolean;
};

type LimitRule = {
    label: string;
    noun: string;
    times: LimitField;
    minutes: LimitField;
};

type LimitSection = {
    title: string;
    why: string;
    rules: LimitRule[];
};

const SECTIONS: LimitSection[] = [
    {
        title: 'Placing orders',
        why: 'Stops fake orders that use up your stock and send order emails to strangers. Covers the normal checkout and landing pages.',
        rules: [
            {
                label: 'From the same device or internet connection',
                noun: 'orders',
                times: 'orders_per_network_times',
                minutes: 'orders_per_network_minutes',
            },
            {
                label: 'Using the same phone number',
                noun: 'orders',
                times: 'orders_per_phone_times',
                minutes: 'orders_per_phone_minutes',
            },
        ],
    },
    {
        title: 'bKash / Nagad payment checks',
        why: "Stops people from guessing transaction IDs to claim someone else's payment.",
        rules: [
            {
                label: 'For the same order',
                noun: 'tries',
                times: 'payment_per_order_times',
                minutes: 'payment_per_order_minutes',
            },
            {
                label: 'From the same device or internet connection',
                noun: 'tries',
                times: 'payment_per_network_times',
                minutes: 'payment_per_network_minutes',
            },
        ],
    },
    {
        title: 'New customer accounts',
        why: 'Stops mass fake sign-ups. Each sign-up also sends a verification email, so this protects your email reputation too.',
        rules: [
            {
                label: 'From the same internet connection',
                noun: 'new accounts',
                times: 'signups_per_network_times',
                minutes: 'signups_per_network_minutes',
            },
        ],
    },
    {
        title: 'Password reset emails',
        why: "Stops someone using your site to flood people's inboxes with reset emails.",
        rules: [
            {
                label: 'From the same internet connection',
                noun: 'requests',
                times: 'password_reset_per_network_times',
                minutes: 'password_reset_per_network_minutes',
            },
            {
                label: 'For the same email address',
                noun: 'requests',
                times: 'password_reset_per_email_times',
                minutes: 'password_reset_per_email_minutes',
            },
        ],
    },
    {
        title: 'Coupon codes',
        why: 'Stops people from guessing coupon codes by trying lots of them.',
        rules: [
            {
                label: 'For the same shopper',
                noun: 'tries',
                times: 'coupon_checks_times',
                minutes: 'coupon_checks_minutes',
            },
        ],
    },
    {
        title: 'Customer sign-in',
        why: 'Stops one connection from trying passwords on many different accounts. Each account also has its own fixed limit of 5 tries per minute.',
        rules: [
            {
                label: 'From the same internet connection',
                noun: 'tries',
                times: 'sign_in_per_network_times',
                minutes: 'sign_in_per_network_minutes',
            },
        ],
    },
];

const EXTRA_SECTIONS: LimitSection[] = [
    {
        title: 'Contact form',
        why: 'Stops spam messages from flooding your contact inbox.',
        rules: [
            {
                label: 'For the same shopper',
                noun: 'messages',
                times: 'contact_messages_times',
                minutes: 'contact_messages_minutes',
            },
        ],
    },
    {
        title: 'Newsletter',
        why: 'Stops spam sign-ups, and stops someone from removing lots of other people from your list.',
        rules: [
            {
                label: 'Joining or leaving, for the same shopper',
                noun: 'requests',
                times: 'newsletter_signups_times',
                minutes: 'newsletter_signups_minutes',
            },
            {
                label: 'Opening an unsubscribe confirmation link',
                noun: 'opens',
                times: 'newsletter_checks_times',
                minutes: 'newsletter_checks_minutes',
            },
            {
                label: 'Requesting an unsubscribe-management email, from the same internet connection',
                noun: 'requests',
                times: 'newsletter_manage_per_network_times',
                minutes: 'newsletter_manage_per_network_minutes',
            },
            {
                label: 'Requesting an unsubscribe-management email, for the same email address',
                noun: 'requests',
                times: 'newsletter_manage_per_email_times',
                minutes: 'newsletter_manage_per_email_minutes',
            },
        ],
    },
    {
        title: 'Unfinished checkout saving',
        why: 'While customers fill in checkout, the site quietly saves their details so you can follow up on orders they never finished. This stops bots from filling that list with junk.',
        rules: [
            {
                label: 'For the same shopper',
                noun: 'saves',
                times: 'checkout_tracking_times',
                minutes: 'checkout_tracking_minutes',
            },
        ],
    },
    {
        title: 'Order tracking',
        why: "Stops people from guessing the phone number or email of someone else's order to see its details.",
        rules: [
            {
                label: 'For the same shopper',
                noun: 'lookups',
                times: 'order_tracking_times',
                minutes: 'order_tracking_minutes',
            },
        ],
    },
    {
        title: 'Search and browsing',
        why: 'Stops copying tools from overloading your site by loading huge numbers of pages very fast.',
        rules: [
            {
                label: 'Search suggestions while typing',
                noun: 'suggestions',
                times: 'search_suggestions_times',
                minutes: 'search_suggestions_minutes',
            },
            {
                label: 'Product list, category and search result pages',
                noun: 'pages',
                times: 'product_browsing_times',
                minutes: 'product_browsing_minutes',
            },
        ],
    },
    {
        title: 'Invoice downloads',
        why: 'Making a PDF invoice is heavy work for the server. This stops repeated downloads from slowing your site down.',
        rules: [
            {
                label: 'For the same shopper',
                noun: 'downloads',
                times: 'invoice_downloads_times',
                minutes: 'invoice_downloads_minutes',
            },
        ],
    },
    {
        title: 'Product reviews',
        why: 'Stops one customer account from posting lots of reviews in a short time.',
        rules: [
            {
                label: 'For the same customer',
                noun: 'reviews',
                times: 'reviews_times',
                minutes: 'reviews_minutes',
            },
        ],
    },
    {
        title: 'Password confirmation',
        why: 'Before sensitive account changes, customers re-enter their password. This stops someone at an unattended, signed-in computer from guessing it.',
        rules: [
            {
                label: 'For the same customer',
                noun: 'tries',
                times: 'password_checks_times',
                minutes: 'password_checks_minutes',
            },
        ],
    },
    {
        title: 'Account actions',
        why: "A safety net for everything customers do inside their account — saving addresses, wishlist, editing their profile, cancelling or returning orders. Only stops runaway scripts; a real customer won't come close.",
        rules: [
            {
                label: 'For the same customer',
                noun: 'actions',
                times: 'account_actions_times',
                minutes: 'account_actions_minutes',
            },
        ],
    },
];

function CronMonitoringCard({
    pingUrl,
    configured,
    canEdit,
}: {
    pingUrl: string | null;
    configured: boolean;
    canEdit: boolean;
}) {
    const form = useForm<{ heartbeat_ping_url: string }>({
        heartbeat_ping_url: pingUrl ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(route('admin.settings.security.heartbeat.update'), {
            preserveScroll: true,
        });
    };

    return (
        <Card className="shadow-sm">
            <CardHeader className="border-b border-slate-100">
                <CardTitle className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <Activity className="size-4 text-[#c8102e]" />
                    Cron &amp; queue monitoring
                    <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                            configured
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-amber-50 text-amber-800'
                        }`}
                    >
                        {configured ? 'Active' : 'Not set up'}
                    </span>
                </CardTitle>
                <p className="text-xs text-slate-600">
                    If your hosting's scheduled task (cron) stops, customer
                    emails and courier updates silently stop too, and the site
                    cannot warn you because it is the thing that stopped. Create
                    a free "heartbeat" monitor at a service such as
                    Healthchecks.io or UptimeRobot, set it to expect a ping
                    every few minutes, and paste its ping URL below. The site
                    pings it every minute while everything is running, and the
                    service alerts you when the pings stop.
                </p>
            </CardHeader>
            <CardContent className="pt-3">
                {canEdit ? (
                    <form onSubmit={handleSubmit} className="space-y-3">
                        <input
                            maxLength={300}
                            type="url"
                            value={form.data.heartbeat_ping_url}
                            onChange={(e) =>
                                form.setData(
                                    'heartbeat_ping_url',
                                    e.target.value,
                                )
                            }
                            placeholder="https://hc-ping.com/your-unique-id"
                            className="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-[#c8102e] focus:ring-2 focus:ring-[#c8102e]/15 focus:outline-none"
                        />
                        {form.errors.heartbeat_ping_url && (
                            <p className="text-xs font-medium text-red-600">
                                {form.errors.heartbeat_ping_url}
                            </p>
                        )}
                        <p className="text-xs text-slate-600">
                            Leave empty to switch this off. Treat the URL like a
                            password: anyone who has it can fake a healthy
                            signal.
                        </p>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {form.processing
                                ? 'Saving…'
                                : 'Save monitoring URL'}
                        </Button>
                    </form>
                ) : (
                    <p className="text-sm text-slate-700">
                        {configured
                            ? 'A monitoring URL is set up.'
                            : 'No monitoring URL is set up yet.'}{' '}
                        Only the main (mother) admin can change it.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function SectionCards({
    sections,
    data,
    bounds,
    errors,
    disabled,
    setValue,
}: {
    sections: LimitSection[];
    data: Limits;
    bounds: Props['bounds'];
    errors: Partial<Record<LimitField, string>>;
    disabled: boolean;
    setValue: (field: LimitField, value: number) => void;
}) {
    // Two independent stacks, each section going to the shorter one (a header
    // counts as roughly one rule), so uneven cards never stretch a grid row.
    const columns: LimitSection[][] = [[], []];
    const heights = [0, 0];

    sections.forEach((section) => {
        const target = heights[1] < heights[0] ? 1 : 0;
        columns[target].push(section);
        heights[target] += section.rules.length + 1;
    });

    return (
        <div className="grid items-start gap-4 lg:grid-cols-2">
            {columns.map((column, index) => (
                <div key={index} className="space-y-4">
                    {column.map((section) => (
                        <Card key={section.title} className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-900">
                                    {section.title}
                                </CardTitle>
                                <p className="text-xs text-slate-600">
                                    {section.why}
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-3">
                                {section.rules.map((rule) => (
                                    <RuleRow
                                        key={rule.times}
                                        rule={rule}
                                        data={data}
                                        bounds={bounds}
                                        errors={errors}
                                        disabled={disabled}
                                        setValue={setValue}
                                    />
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            ))}
        </div>
    );
}

const numberInputClass =
    'h-9 w-20 rounded-lg border bg-white px-2.5 text-center text-sm font-semibold text-slate-900 focus:ring-2 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500';

function NumberBox({
    field,
    value,
    bounds,
    error,
    disabled,
    onChange,
}: {
    field: LimitField;
    value: number;
    bounds: { min: number; max: number };
    error?: string;
    disabled: boolean;
    onChange: (value: number) => void;
}) {
    return (
        <input
            id={field}
            type="number"
            inputMode="numeric"
            min={bounds.min}
            max={bounds.max}
            step={1}
            value={Number.isNaN(value) ? '' : value}
            disabled={disabled}
            onChange={(e) => onChange(e.target.valueAsNumber)}
            title={`Between ${bounds.min} and ${bounds.max}`}
            className={`${numberInputClass} ${
                error
                    ? 'border-red-400 focus:ring-red-500/20'
                    : 'border-slate-300 focus:border-[#c8102e] focus:ring-[#c8102e]/15'
            }`}
        />
    );
}

function RuleRow({
    rule,
    data,
    bounds,
    errors,
    disabled,
    setValue,
}: {
    rule: LimitRule;
    data: Limits;
    bounds: Props['bounds'];
    errors: Partial<Record<LimitField, string>>;
    disabled: boolean;
    setValue: (field: LimitField, value: number) => void;
}) {
    const fieldErrors = [errors[rule.times], errors[rule.minutes]].filter(
        Boolean,
    );

    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
            <p className="mb-2.5 text-sm font-semibold text-slate-800">
                {rule.label}
            </p>
            <div className="grid grid-cols-[2.75rem_5rem_1fr] items-center gap-2 text-sm text-slate-700 sm:flex sm:flex-wrap">
                <span>Allow</span>
                <NumberBox
                    field={rule.times}
                    value={data[rule.times]}
                    bounds={bounds[rule.times]}
                    error={errors[rule.times]}
                    disabled={disabled}
                    onChange={(value) => setValue(rule.times, value)}
                />
                <span>{rule.noun}</span>
                <span>every</span>
                <NumberBox
                    field={rule.minutes}
                    value={data[rule.minutes]}
                    bounds={bounds[rule.minutes]}
                    error={errors[rule.minutes]}
                    disabled={disabled}
                    onChange={(value) => setValue(rule.minutes, value)}
                />
                <span>minutes</span>
            </div>
            <p className="mt-2 text-xs text-slate-600">
                Allowed: {bounds[rule.times].min}–{bounds[rule.times].max}{' '}
                {rule.noun}, over {bounds[rule.minutes].min}–
                {bounds[rule.minutes].max} minutes. Recommended:{' '}
                {bounds[rule.times].default} every{' '}
                {bounds[rule.minutes].default} minutes.
            </p>
            {fieldErrors.map((message) => (
                <p
                    key={message}
                    className="mt-1 text-xs font-medium text-red-600"
                >
                    {message}
                </p>
            ))}
        </div>
    );
}

export default function SecuritySettings({
    limits,
    bounds,
    canEdit,
    visitorIp,
    heartbeatPingUrl,
    heartbeatConfigured,
}: Props) {
    const { data, setData, put, processing, errors } = useForm<Limits>(limits);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.security.update'), { preserveScroll: true });
    };

    const resetToRecommended = () => {
        setData(
            Object.fromEntries(
                Object.entries(bounds).map(([field, b]) => [field, b.default]),
            ) as Limits,
        );
    };

    return (
        <>
            <Head title="Security — Admin" />
            <div className="space-y-4">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#c8102e]/10 text-[#c8102e]">
                        <ShieldCheck className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Security
                        </h1>
                        <p className="text-sm text-slate-600">
                            Protect your shop from bots by limiting how often
                            the same person can repeat an action. Real customers
                            almost never reach these limits. If they do, they
                            simply see a message asking them to wait a little.
                        </p>
                    </div>
                </div>

                {!canEdit && (
                    <div className="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <Lock className="mt-0.5 size-4 shrink-0" />
                        Only the main (mother) admin can change these settings.
                        You can view them here.
                    </div>
                )}

                <div className="flex items-start gap-2.5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <p>
                        Your internet address, as this site sees it:{' '}
                        <span className="font-mono font-semibold">
                            {visitorIp}
                        </span>
                        . If every visitor shows the same address (for example
                        your hosting company's), tell your developer before
                        lowering these numbers — otherwise real customers could
                        end up sharing one limit.
                    </p>
                </div>

                <CronMonitoringCard
                    pingUrl={heartbeatPingUrl}
                    configured={heartbeatConfigured}
                    canEdit={canEdit}
                />

                <form onSubmit={handleSubmit} className="space-y-5">
                    <h2 className="pt-1 text-xs font-bold tracking-wider text-slate-700 uppercase">
                        Most important
                    </h2>
                    <SectionCards
                        sections={SECTIONS}
                        data={data}
                        bounds={bounds}
                        errors={errors}
                        disabled={!canEdit}
                        setValue={(field, value) => setData(field, value)}
                    />

                    <h2 className="pt-3 text-xs font-bold tracking-wider text-slate-700 uppercase">
                        Extra protection
                    </h2>
                    <SectionCards
                        sections={EXTRA_SECTIONS}
                        data={data}
                        bounds={bounds}
                        errors={errors}
                        disabled={!canEdit}
                        setValue={(field, value) => setData(field, value)}
                    />

                    <p className="text-xs text-slate-600">
                        Admin sign-in has its own fixed protection that can't be
                        changed here, so these settings can never make this page
                        easier to break into.
                    </p>

                    {canEdit && (
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                            >
                                {processing ? 'Saving…' : 'Save'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetToRecommended}
                            >
                                Use recommended values
                            </Button>
                        </div>
                    )}
                </form>
            </div>
        </>
    );
}
