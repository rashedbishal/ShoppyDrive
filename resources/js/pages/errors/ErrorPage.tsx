import { Head, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Hourglass,
    Home,
    LayoutDashboard,
    RefreshCw,
    Search,
    SearchX,
    ServerCrash,
    ShieldAlert,
    TimerReset,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { SharedPageProps } from '@/types';

type ErrorStatus = 403 | 404 | 419 | 429 | 500 | 503;

type ErrorPageProps = {
    status: ErrorStatus;
    isAdminArea?: boolean;
};

type ErrorContent = {
    title: string;
    description: string;
    icon: LucideIcon;
    /** Whether reloading is the most useful next step (expired session, outage…). */
    suggestsReload: boolean;
};

const ERROR_CONTENT: Record<ErrorStatus, ErrorContent> = {
    403: {
        title: 'Access denied',
        description:
            "You don't have permission to view this page. If you think this is a mistake, try signing in with a different account.",
        icon: ShieldAlert,
        suggestsReload: false,
    },
    404: {
        title: 'Page not found',
        description:
            "The page you're looking for may have moved, sold out, or never existed. Let's get you back to shopping.",
        icon: SearchX,
        suggestsReload: false,
    },
    419: {
        title: 'Session expired',
        description:
            'Your session timed out for security reasons. Refresh the page and try that again.',
        icon: TimerReset,
        suggestsReload: true,
    },
    429: {
        title: 'Slow down a little',
        description:
            "You've made too many requests in a short time. Please wait a moment, then try again.",
        icon: Hourglass,
        suggestsReload: true,
    },
    500: {
        title: 'Something went wrong',
        description:
            'An unexpected error happened on our side. Our team has been notified — please try again in a moment.',
        icon: ServerCrash,
        suggestsReload: true,
    },
    503: {
        title: "We'll be right back",
        description:
            "We're doing some quick maintenance to make your shopping better. Please check back in a few minutes.",
        icon: Wrench,
        suggestsReload: true,
    },
};

const QUICK_LINKS = [
    { label: 'Flash Sale', href: '/flash-sales' },
    { label: 'All Products', href: '/products' },
    { label: 'Brands', href: '/brands' },
    { label: 'Help Center', href: '/faq' },
];

/**
 * Themed page for HTTP errors (rendered from the exception handler in
 * AppServiceProvider). Self-contained — no storefront layout — and it
 * tolerates missing shared props, since 500/503 responses skip them.
 */
export default function ErrorPage({
    status,
    isAdminArea = false,
}: ErrorPageProps): React.JSX.Element {
    const { settings } = usePage<Partial<SharedPageProps>>().props;
    const [searchQuery, setSearchQuery] = useState('');

    const content = ERROR_CONTENT[status] ?? ERROR_CONTENT[500];
    const Icon = content.icon;
    const siteName = settings?.site_name ?? 'ShoppyDrive';
    const homeHref = isAdminArea ? '/admin/dashboard' : '/';
    const showStorefrontHelpers = status === 404 && !isAdminArea;

    const goBack = (): void => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = homeHref;
        }
    };

    return (
        <>
            <Head title={`${status} · ${content.title}`} />

            <div className="relative isolate flex min-h-screen flex-col overflow-hidden bg-[var(--color-surface-alt)]">
                {/* Ambient brand glows — static, so they cost nothing after first paint */}
                <div className="pointer-events-none absolute -top-40 -right-32 -z-10 size-[28rem] rounded-full bg-[var(--color-primary)] opacity-[0.07] blur-3xl" />
                <div className="pointer-events-none absolute -bottom-48 -left-32 -z-10 size-[30rem] rounded-full bg-[var(--color-accent)] opacity-[0.10] blur-3xl" />
                <div
                    className="pointer-events-none absolute inset-0 -z-10 [background-image:radial-gradient(var(--color-border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)] [background-size:22px_22px] opacity-[0.35]"
                    aria-hidden="true"
                />

                <header className="mx-auto flex w-full max-w-6xl items-center px-4 py-5 sm:px-6">
                    <a href={homeHref} className="flex items-center gap-2.5">
                        {settings?.logo ? (
                            <img
                                src={settings.logo}
                                alt={siteName}
                                className="h-9 w-auto max-w-[10rem] object-contain"
                            />
                        ) : (
                            <>
                                <span className="flex size-9 items-center justify-center rounded-xl bg-[var(--color-primary)] shadow-sm">
                                    <AppLogoIcon className="size-5 fill-current text-white" />
                                </span>
                                <span className="font-display text-lg font-bold tracking-tight text-[var(--color-text-primary)]">
                                    {siteName}
                                </span>
                            </>
                        )}
                    </a>
                </header>

                <main className="flex flex-1 items-center justify-center px-4 pb-16 sm:px-6">
                    <div className="w-full max-w-xl text-center">
                        <div className="relative mx-auto mb-6 w-fit">
                            <p
                                aria-hidden="true"
                                className="bg-gradient-to-br from-[var(--color-primary)] via-[color-mix(in_srgb,var(--color-primary)_70%,var(--color-accent))] to-[var(--color-accent)] bg-clip-text font-display text-[7rem] leading-none font-black tracking-tighter text-transparent select-none sm:text-[9rem]"
                            >
                                {status}
                            </p>
                            <span className="absolute -right-3 -bottom-2 flex size-14 rotate-6 items-center justify-center rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-primary)] shadow-[var(--shadow-card)] sm:size-16">
                                <Icon className="size-7 sm:size-8" />
                            </span>
                        </div>

                        <p className="mb-2 text-xs font-semibold tracking-[0.22em] text-[var(--color-text-muted)] uppercase">
                            Error {status}
                        </p>
                        <h1 className="font-display text-3xl font-bold tracking-tight text-[var(--color-text-primary)] sm:text-4xl">
                            {content.title}
                        </h1>
                        <p className="mx-auto mt-3 max-w-md text-sm leading-7 text-[var(--color-text-secondary)] sm:text-base">
                            {content.description}
                        </p>

                        {showStorefrontHelpers ? (
                            <form
                                action="/search"
                                method="get"
                                className="mx-auto mt-8 flex max-w-md gap-2"
                            >
                                <label
                                    htmlFor="error-search"
                                    className="sr-only"
                                >
                                    Search products
                                </label>
                                <div className="relative flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                    <input
                                        maxLength={64}
                                        id="error-search"
                                        type="search"
                                        name="q"
                                        value={searchQuery}
                                        onChange={(event) =>
                                            setSearchQuery(event.target.value)
                                        }
                                        placeholder="Search for products…"
                                        className="h-12 w-full rounded-full border border-[var(--color-border)] bg-[var(--color-surface)] pr-4 pl-11 text-sm text-[var(--color-text-primary)] shadow-sm transition outline-none placeholder:text-[var(--color-text-muted)] focus:border-[var(--color-primary)] focus:ring-4 focus:ring-[var(--color-primary)]/10"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    className="h-12 shrink-0 rounded-full bg-[var(--color-secondary)] px-5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                                >
                                    Search
                                </button>
                            </form>
                        ) : null}

                        <div className="mt-8 flex flex-col-reverse items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                            <button
                                type="button"
                                onClick={goBack}
                                className="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-surface)] px-6 text-sm font-semibold text-[var(--color-text-primary)] shadow-sm transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
                            >
                                <ArrowLeft className="size-4" />
                                Go back
                            </button>

                            {content.suggestsReload ? (
                                <button
                                    type="button"
                                    onClick={() => window.location.reload()}
                                    className="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-[var(--color-primary)] px-6 text-sm font-semibold text-white shadow-[var(--color-primary)]/20 shadow-lg transition hover:brightness-110"
                                >
                                    <RefreshCw className="size-4" />
                                    {status === 419
                                        ? 'Refresh page'
                                        : 'Try again'}
                                </button>
                            ) : null}

                            <a
                                href={homeHref}
                                className={
                                    content.suggestsReload
                                        ? 'inline-flex h-12 items-center justify-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-surface)] px-6 text-sm font-semibold text-[var(--color-text-primary)] shadow-sm transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]'
                                        : 'inline-flex h-12 items-center justify-center gap-2 rounded-full bg-[var(--color-primary)] px-6 text-sm font-semibold text-white shadow-[var(--color-primary)]/20 shadow-lg transition hover:brightness-110'
                                }
                            >
                                {isAdminArea ? (
                                    <LayoutDashboard className="size-4" />
                                ) : (
                                    <Home className="size-4" />
                                )}
                                {isAdminArea
                                    ? 'Back to dashboard'
                                    : 'Back to home'}
                            </a>
                        </div>

                        {showStorefrontHelpers ? (
                            <div className="mt-10">
                                <p className="mb-3 text-xs font-semibold tracking-[0.2em] text-[var(--color-text-muted)] uppercase">
                                    Popular right now
                                </p>
                                <div className="flex flex-wrap justify-center gap-2">
                                    {QUICK_LINKS.map((link) => (
                                        <a
                                            key={link.href}
                                            href={link.href}
                                            className="rounded-full border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition hover:border-[var(--color-primary)] hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
                                        >
                                            {link.label}
                                        </a>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </div>
                </main>

                <footer className="pb-6 text-center text-xs text-[var(--color-text-muted)]">
                    © {new Date().getFullYear()} {siteName}
                </footer>
            </div>
        </>
    );
}
