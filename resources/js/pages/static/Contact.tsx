import { Head, useForm } from '@inertiajs/react';
import {
    Clock,
    Facebook,
    Instagram,
    Mail,
    MapPin,
    MessageSquare,
    Phone,
    Send,
    Youtube,
} from 'lucide-react';
import { TikTokLogo, XLogo } from '@/components/icons/brand-icons';
import InputError from '@/components/input-error';
import { PageHero } from '@/components/shared/PageHero';

type ContactContentProps = {
    hero_eyebrow: string;
    hero_title: string;
    hero_title_highlight: string;
    hero_description: string;
    phone: string;
    email: string;
    address: string;
    support_hours: string;
    social_links: {
        facebook?: string | null;
        instagram?: string | null;
        twitter?: string | null;
        youtube?: string | null;
        tiktok?: string | null;
    };
};

type Props = {
    content: ContactContentProps;
};

function ContactContent({ content }: Props): React.JSX.Element {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        recentlySuccessful,
    } = useForm({
        name: '',
        email: '',
        subject: '',
        message: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('contact.store'), { onSuccess: () => reset() });
    };

    const contactInfo = [
        {
            icon: Phone,
            title: 'Phone',
            value: content.phone,
            href: `tel:${content.phone.replace(/[^+\d]/g, '')}`,
        },
        {
            icon: Mail,
            title: 'Email',
            value: content.email,
            href: `mailto:${content.email}`,
        },
        { icon: MapPin, title: 'Location', value: content.address, href: null },
        {
            icon: Clock,
            title: 'Support Hours',
            value: content.support_hours,
            href: null,
        },
    ];

    const socialLinks = [
        {
            icon: Facebook,
            label: 'Facebook',
            href: content.social_links.facebook,
            color: '#1877F2',
        },
        {
            icon: Instagram,
            label: 'Instagram',
            href: content.social_links.instagram,
            color: '#E1306C',
        },
        {
            icon: XLogo,
            label: 'X',
            href: content.social_links.twitter,
            color: '#000000',
        },
        {
            icon: Youtube,
            label: 'YouTube',
            href: content.social_links.youtube,
            color: '#FF0000',
        },
        {
            icon: TikTokLogo,
            label: 'TikTok',
            href: content.social_links.tiktok,
            color: '#000000',
        },
    ].filter((link) => link.href);

    return (
        <>
            <Head title="Contact Us | ShoppyDrive" />

            {/* Hero */}
            <div className="pt-6 lg:pt-8">
                <PageHero
                    badge={{ icon: MessageSquare, label: content.hero_eyebrow }}
                    title={
                        content.hero_title_highlight ? (
                            <>
                                {content.hero_title}{' '}
                                <span className="text-[var(--color-primary)]">
                                    {content.hero_title_highlight}
                                </span>
                            </>
                        ) : (
                            content.hero_title
                        )
                    }
                    description={content.hero_description}
                />
            </div>

            <section className="pt-8 pb-12 lg:pt-10 lg:pb-20">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-10 lg:grid-cols-[1fr_1.5fr] lg:items-start">
                        {/* Contact info */}
                        <div className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                {contactInfo.map(
                                    ({ icon: Icon, title, value, href }) => (
                                        <div
                                            key={title}
                                            className="flex items-start gap-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4"
                                        >
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[var(--color-primary)]/10">
                                                <Icon className="size-5 text-[var(--color-primary)]" />
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold tracking-[0.15em] text-[var(--color-text-muted)] uppercase">
                                                    {title}
                                                </p>
                                                {href ? (
                                                    <a
                                                        href={href}
                                                        className="mt-0.5 text-sm font-medium text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)]"
                                                    >
                                                        {value}
                                                    </a>
                                                ) : (
                                                    <p className="mt-0.5 text-sm font-medium text-[var(--color-text-primary)]">
                                                        {value}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>

                            {/* Social */}
                            {socialLinks.length > 0 && (
                                <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-5">
                                    <p className="mb-4 text-sm font-semibold text-[var(--color-text-primary)]">
                                        Follow us
                                    </p>
                                    <div className="flex gap-3">
                                        {socialLinks.map(
                                            ({
                                                icon: Icon,
                                                label,
                                                href,
                                                color,
                                            }) => (
                                                <a
                                                    key={label}
                                                    href={href!}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    aria-label={label}
                                                    className="flex size-10 items-center justify-center rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] transition hover:scale-105"
                                                >
                                                    <Icon
                                                        className="size-4"
                                                        style={{ color }}
                                                    />
                                                </a>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Contact form */}
                        <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-6 sm:p-8">
                            {recentlySuccessful ? (
                                <div className="flex flex-col items-center gap-4 py-8 text-center">
                                    <div className="flex size-16 items-center justify-center rounded-full bg-[var(--color-success)]/10">
                                        <Send className="size-8 text-[var(--color-success)]" />
                                    </div>
                                    <h3 className="text-xl font-bold text-[var(--color-text-primary)]">
                                        Message sent!
                                    </h3>
                                    <p className="text-sm text-[var(--color-text-secondary)]">
                                        We'll get back to you within 24 hours.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <h2 className="mb-6 text-xl font-bold text-[var(--color-text-primary)]">
                                        Send us a message
                                    </h2>
                                    <form
                                        onSubmit={handleSubmit}
                                        className="space-y-4"
                                    >
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-1.5">
                                                <label className="text-sm font-medium text-[var(--color-text-primary)]">
                                                    Full name
                                                </label>
                                                <input
                                                    type="text"
                                                    value={data.name}
                                                    onChange={(e) =>
                                                        setData(
                                                            'name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Your name"
                                                    className="h-11 w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 text-sm text-[var(--color-text-primary)] transition placeholder:text-[var(--color-text-muted)] focus:border-[var(--color-primary)] focus:ring-1 focus:ring-[var(--color-primary)] focus:outline-none"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <label className="text-sm font-medium text-[var(--color-text-primary)]">
                                                    Email
                                                </label>
                                                <input
                                                    type="email"
                                                    value={data.email}
                                                    onChange={(e) =>
                                                        setData(
                                                            'email',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="you@example.com"
                                                    className="h-11 w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 text-sm text-[var(--color-text-primary)] transition placeholder:text-[var(--color-text-muted)] focus:border-[var(--color-primary)] focus:ring-1 focus:ring-[var(--color-primary)] focus:outline-none"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.email}
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-1.5">
                                            <label className="text-sm font-medium text-[var(--color-text-primary)]">
                                                Subject
                                            </label>
                                            <input
                                                type="text"
                                                value={data.subject}
                                                onChange={(e) =>
                                                    setData(
                                                        'subject',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="How can we help?"
                                                className="h-11 w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 text-sm text-[var(--color-text-primary)] transition placeholder:text-[var(--color-text-muted)] focus:border-[var(--color-primary)] focus:ring-1 focus:ring-[var(--color-primary)] focus:outline-none"
                                                required
                                            />
                                            <InputError
                                                message={errors.subject}
                                            />
                                        </div>

                                        <div className="space-y-1.5">
                                            <label className="text-sm font-medium text-[var(--color-text-primary)]">
                                                Message
                                            </label>
                                            <textarea
                                                value={data.message}
                                                onChange={(e) =>
                                                    setData(
                                                        'message',
                                                        e.target.value,
                                                    )
                                                }
                                                rows={5}
                                                placeholder="Tell us more about your question or concern..."
                                                className="w-full resize-none rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3 text-sm text-[var(--color-text-primary)] transition placeholder:text-[var(--color-text-muted)] focus:border-[var(--color-primary)] focus:ring-1 focus:ring-[var(--color-primary)] focus:outline-none"
                                                required
                                            />
                                            <InputError
                                                message={errors.message}
                                            />
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[var(--color-primary)] text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] disabled:opacity-60"
                                        >
                                            {processing ? (
                                                <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                            ) : (
                                                <>
                                                    <Send className="size-4" />
                                                    <span>Send Message</span>
                                                </>
                                            )}
                                        </button>
                                    </form>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}

export default function Contact({ content }: Props): React.JSX.Element {
    return <ContactContent content={content} />;
}
