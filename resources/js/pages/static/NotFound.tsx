import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Home, Search } from 'lucide-react';

function NotFoundContent(): React.JSX.Element {
    return (
        <>
            <Head title="404 — Page Not Found | ShoppyDrive" />
            <div className="flex min-h-[70vh] flex-col items-center justify-center px-4 py-20 text-center">
                {/* Large 404 */}
                <div className="relative mb-8 select-none">
                    <span className="text-[10rem] font-black leading-none text-[var(--color-surface-alt)] lg:text-[14rem]">
                        404
                    </span>
                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div className="size-32 rounded-full bg-[var(--color-primary)] opacity-10 blur-3xl lg:size-48" />
                    </div>
                </div>

                <div className="max-w-lg space-y-4">
                    <h1 className="text-2xl font-bold text-[var(--color-text-primary)] sm:text-3xl">
                        Oops! Page not found
                    </h1>
                    <p className="text-[var(--color-text-secondary)]">
                        The page you're looking for doesn't exist or has been moved. Let's get you back on track.
                    </p>
                </div>

                <div className="mt-10 flex flex-col items-center gap-3 sm:flex-row">
                    <Link
                        href="/"
                        className="flex h-11 items-center gap-2 rounded-xl bg-[var(--color-primary)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]"
                    >
                        <Home className="size-4" />
                        Back to Home
                    </Link>
                    <Link
                        href="/products"
                        className="flex h-11 items-center gap-2 rounded-xl border border-[var(--color-border)] px-6 text-sm font-medium text-[var(--color-text-secondary)] transition hover:border-[var(--color-border-strong)] hover:text-[var(--color-text-primary)]"
                    >
                        <Search className="size-4" />
                        Browse Products
                    </Link>
                </div>

                <div className="mt-16 grid gap-4 sm:grid-cols-3">
                    {[
                        { label: 'Shop All Products', href: '/products' },
                        { label: 'Flash Sales', href: '/flash-sales' },
                        { label: 'Contact Support', href: '/contact' },
                    ].map(({ label, href }) => (
                        <Link
                            key={href}
                            href={href}
                            className="flex items-center justify-between gap-2 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-4 py-3 text-sm text-[var(--color-text-secondary)] transition hover:border-[var(--color-primary)]/40 hover:text-[var(--color-text-primary)]"
                        >
                            {label}
                            <ArrowRight className="size-3.5 shrink-0" />
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

export default function NotFound(): React.JSX.Element {
    return (
        <NotFoundContent />
    );
}
