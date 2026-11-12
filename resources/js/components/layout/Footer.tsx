import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Facebook,
    Instagram,
    Mail,
    MapPin,
    Phone,
    Shield,
    Star,
    Truck,
    Youtube,
    Zap,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { TikTokLogo, XLogo } from '@/components/icons/brand-icons';
import {
    footerCustomerLinks,
    footerQuickLinks,
} from '@/components/layout/navigation';
import NewsletterThankYouModal from '@/components/shared/NewsletterThankYouModal';
import { useNewsletterSubscribe } from '@/hooks/use-newsletter-subscribe';
import type { SharedPageProps } from '@/types';

/**
 * BIZ-032: the delivery and support badges come from the real settings
 * (Admin free-delivery threshold and Contact Page support hours) instead of
 * hard-coded claims that could contradict them.
 */
function trustBadgesFor(settings: SharedPageProps['settings']) {
    const threshold = Number(settings.free_delivery_threshold ?? 0);

    return [
        threshold > 0
            ? {
                  icon: Truck,
                  label: 'Free Delivery',
                  sub: `Orders over ${settings.currency_symbol}${Math.round(threshold).toLocaleString('en-US')}`,
              }
            : {
                  icon: Truck,
                  label: 'Nationwide Delivery',
                  sub: 'All 64 districts',
              },
        { icon: Shield, label: 'Secure Payment', sub: 'SSL Encrypted' },
        { icon: Star, label: 'Verified Products', sub: 'Quality Assured' },
        {
            icon: Zap,
            label: 'Customer Support',
            sub: settings.support_hours ?? 'Every day, 9 AM–9 PM',
        },
    ];
}

const policyLinks = [
    { label: 'Privacy Policy', href: '/privacy-policy' },
    { label: 'Terms of Service', href: '/terms' },
    { label: 'Return Policy', href: '/return-policy' },
    { label: 'Refund Policy', href: '/refund-policy' },
    { label: 'Shipping Policy', href: '/shipping-policy' },
    { label: 'FAQ', href: '/faq' },
];

export default function Footer(): React.JSX.Element {
    const { settings } = usePage<SharedPageProps>().props;
    const {
        form,
        submit: handleSubmit,
        thankYou,
        closeThankYou,
    } = useNewsletterSubscribe();

    const socialLinks = [
        {
            label: 'Facebook',
            href: settings.social_links?.facebook,
            Icon: Facebook,
        },
        {
            label: 'Instagram',
            href: settings.social_links?.instagram,
            Icon: Instagram,
        },
        { label: 'X', href: settings.social_links?.twitter, Icon: XLogo },
        {
            label: 'YouTube',
            href: settings.social_links?.youtube,
            Icon: Youtube,
        },
        {
            label: 'TikTok',
            href: settings.social_links?.tiktok,
            Icon: TikTokLogo,
        },
    ].filter((s) => typeof s.href === 'string' && s.href.length > 0);

    return (
        <footer className="border-t border-[var(--color-border)] bg-[var(--color-surface)]">
            {/* Trust badges strip */}
            <div className="border-b border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                <div className="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {trustBadgesFor(settings).map(
                            ({ icon: Icon, label, sub }) => (
                                <div
                                    key={label}
                                    className="flex items-center gap-3"
                                >
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl border border-[var(--color-border)] bg-[var(--color-primary-light)]">
                                        <Icon className="size-5 text-[var(--color-primary)]" />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-[var(--color-text-primary)]">
                                            {label}
                                        </p>
                                        <p className="truncate text-xs text-[var(--color-text-muted)]">
                                            {sub}
                                        </p>
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                </div>
            </div>

            {/* Main footer */}
            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div className="grid gap-10 lg:grid-cols-[1.4fr_0.9fr_0.9fr_1.1fr]">
                    {/* Brand column */}
                    <div className="space-y-6">
                        <Link
                            href={route('home')}
                            className="inline-flex items-center gap-3"
                        >
                            {settings.logo ? (
                                <img
                                    src={settings.logo}
                                    alt={settings.site_name}
                                    className="h-14 w-auto object-contain"
                                />
                            ) : (
                                <span className="flex size-10 items-center justify-center rounded-xl bg-[var(--color-primary)] text-white">
                                    <AppLogoIcon className="size-5 fill-current text-white" />
                                </span>
                            )}
                            <span className="text-xl font-bold tracking-tight text-[var(--color-text-primary)]">
                                {settings.site_name}
                            </span>
                        </Link>

                        <p className="max-w-xs text-sm leading-7 text-[var(--color-text-secondary)]">
                            Bangladesh's premier online shopping destination.
                            Premium products, unbeatable prices, and fast
                            nationwide delivery.
                        </p>

                        <div className="space-y-2.5 text-sm">
                            {settings.contact_phone ? (
                                <a
                                    href={`tel:${settings.contact_phone}`}
                                    className="flex items-center gap-2.5 text-[var(--color-text-secondary)] transition hover:text-[var(--color-primary)]"
                                >
                                    <Phone className="size-4 shrink-0" />
                                    {settings.contact_phone}
                                </a>
                            ) : null}
                            {settings.contact_email ? (
                                <a
                                    href={`mailto:${settings.contact_email}`}
                                    className="flex items-center gap-2.5 text-[var(--color-text-secondary)] transition hover:text-[var(--color-primary)]"
                                >
                                    <Mail className="size-4 shrink-0" />
                                    {settings.contact_email}
                                </a>
                            ) : null}
                            <div className="flex items-start gap-2.5 text-[var(--color-text-secondary)]">
                                <MapPin className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    {settings.contact_address ||
                                        'Dhaka, Bangladesh'}
                                </span>
                            </div>
                        </div>

                        {/* Social links */}
                        {socialLinks.length > 0 ? (
                            <div className="flex items-center gap-2">
                                {socialLinks.map(({ label, href, Icon }) => (
                                    <a
                                        key={label}
                                        href={href as string}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-label={label}
                                        className="flex size-9 items-center justify-center rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] text-[var(--color-text-muted)] transition hover:border-[var(--color-primary)] hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
                                    >
                                        <Icon className="size-4" />
                                    </a>
                                ))}
                            </div>
                        ) : null}
                    </div>

                    {/* Quick links */}
                    <div className="space-y-4">
                        <h3 className="text-xs font-bold tracking-[0.2em] text-[var(--color-text-muted)] uppercase">
                            Quick Links
                        </h3>
                        <nav className="space-y-2.5">
                            {footerQuickLinks.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.href}
                                    className="flex items-center gap-1.5 text-sm text-[var(--color-text-secondary)] transition hover:translate-x-1 hover:text-[var(--color-primary)]"
                                >
                                    <ArrowRight className="size-3 opacity-0 transition-opacity group-hover:opacity-100" />
                                    {link.label}
                                </Link>
                            ))}
                        </nav>
                    </div>

                    {/* Customer care */}
                    <div className="space-y-4">
                        <h3 className="text-xs font-bold tracking-[0.2em] text-[var(--color-text-muted)] uppercase">
                            Customer Care
                        </h3>
                        <nav className="space-y-2.5">
                            {footerCustomerLinks.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.href}
                                    className="flex items-center gap-1.5 text-sm text-[var(--color-text-secondary)] transition hover:translate-x-1 hover:text-[var(--color-primary)]"
                                >
                                    {link.label}
                                </Link>
                            ))}
                        </nav>
                    </div>

                    {/* Newsletter */}
                    <div className="space-y-5 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-6">
                        <div className="space-y-1.5">
                            <h3 className="text-base font-bold text-[var(--color-text-primary)]">
                                Stay in the loop
                            </h3>
                            <p className="text-sm text-[var(--color-text-secondary)]">
                                Get deals, new arrivals and sale alerts straight
                                to your inbox.
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-3">
                            <div className="flex overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] focus-within:border-[var(--color-primary)] focus-within:ring-1 focus-within:ring-[var(--color-primary)]">
                                <input
                                    maxLength={150}
                                    type="email"
                                    autoComplete="email"
                                    placeholder="your@email.com"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    className="min-w-0 flex-1 bg-transparent px-4 py-3 text-sm text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)] focus:outline-none"
                                    required
                                    aria-label="Email address"
                                    aria-invalid={
                                        form.errors.email ? true : undefined
                                    }
                                />
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="flex items-center gap-1.5 bg-[var(--color-primary)] px-4 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] disabled:opacity-60"
                                >
                                    {form.processing
                                        ? 'Subscribing…'
                                        : 'Subscribe'}
                                </button>
                            </div>
                            {form.errors.email ? (
                                <p
                                    className="text-xs font-medium text-[var(--color-error)]"
                                    role="alert"
                                >
                                    {form.errors.email}
                                </p>
                            ) : (
                                <p className="text-xs text-[var(--color-text-muted)]">
                                    No spam. Unsubscribe any time via the link
                                    in our emails.
                                </p>
                            )}
                        </form>

                        {/* Payment methods */}
                        <div className="space-y-2">
                            <p className="text-[11px] font-semibold tracking-[0.2em] text-[var(--color-text-muted)] uppercase">
                                We Accept
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {['Cash on Delivery', 'bKash', 'Nagad'].map(
                                    (method) => (
                                        <span
                                            key={method}
                                            className="inline-flex items-center rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--color-text-secondary)]"
                                        >
                                            {method}
                                        </span>
                                    ),
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Bottom bar */}
                <div className="mt-12 flex flex-col gap-4 border-t border-[var(--color-border)] pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-sm text-[var(--color-text-muted)]">
                            &copy; {new Date().getFullYear()}{' '}
                            <span className="text-[var(--color-text-secondary)]">
                                {settings.site_name}
                            </span>
                            . All rights reserved.
                        </p>
                        {/* BIZ-034: who the seller is, from Admin › Contact Page. */}
                        {settings.legal_business_name ||
                        settings.trade_licence_number ? (
                            <p className="text-xs text-[var(--color-text-muted)]">
                                {settings.legal_business_name
                                    ? `Sold by ${settings.legal_business_name}`
                                    : null}
                                {settings.legal_business_name &&
                                settings.trade_licence_number
                                    ? ' · '
                                    : null}
                                {settings.trade_licence_number
                                    ? `Trade Licence No. ${settings.trade_licence_number}`
                                    : null}
                            </p>
                        ) : null}
                    </div>
                    <div className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-[var(--color-text-muted)]">
                        {policyLinks.map(({ label, href }) => (
                            <Link
                                key={href}
                                href={href}
                                className="transition hover:text-[var(--color-primary)]"
                            >
                                {label}
                            </Link>
                        ))}
                    </div>
                </div>
            </div>

            <NewsletterThankYouModal
                status={thankYou?.status ?? null}
                email={thankYou?.email ?? ''}
                onClose={closeThankYou}
            />
        </footer>
    );
}
