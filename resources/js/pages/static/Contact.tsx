import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Clock, Facebook, Instagram, Mail, MapPin, MessageSquare, Phone, Send, Twitter } from 'lucide-react';
import InputError from '@/components/input-error';

const contactInfo = [
    { icon: Phone, title: 'Phone', value: '+880 1700-000000', href: 'tel:+8801700000000' },
    { icon: Mail, title: 'Email', value: 'support@shoppydrive.com', href: 'mailto:support@shoppydrive.com' },
    { icon: MapPin, title: 'Location', value: 'Dhaka, Bangladesh', href: null },
    { icon: Clock, title: 'Support Hours', value: 'Sat–Thu, 9am–9pm BST', href: null },
];

const socialLinks = [
    { icon: Facebook, label: 'Facebook', href: 'https://facebook.com/shoppydrive', color: '#1877F2' },
    { icon: Instagram, label: 'Instagram', href: 'https://instagram.com/shoppydrive', color: '#E1306C' },
    { icon: Twitter, label: 'Twitter', href: 'https://twitter.com/shoppydrive', color: '#1DA1F2' },
];

function ContactContent(): React.JSX.Element {
    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        name: '',
        email: '',
        subject: '',
        message: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('contact.store'), { onSuccess: () => reset() });
    };

    return (
        <>
            <Head title="Contact Us | ShoppyDrive" />

            {/* Hero */}
            <section className="relative overflow-hidden bg-gradient-to-br from-[#0f0f18] to-[var(--color-surface)] py-16 lg:py-20">
                <div className="pointer-events-none absolute -right-20 top-0 h-72 w-72 rounded-full bg-[var(--color-primary)] opacity-8 blur-3xl" />
                <div className="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                    <div className="inline-flex items-center gap-2 rounded-full border border-[var(--color-primary)]/25 bg-[var(--color-primary)]/10 px-4 py-2 text-sm font-medium text-[var(--color-primary)]">
                        <MessageSquare className="size-4" />
                        Get in touch
                    </div>
                    <h1 className="mt-4 text-4xl font-bold text-[var(--color-text-primary)] sm:text-5xl">
                        We're here to <span className="text-[var(--color-primary)]">help</span>
                    </h1>
                    <p className="mx-auto mt-4 max-w-xl text-[var(--color-text-secondary)]">
                        Have a question, complaint, or just want to say hello? Our team is ready to assist you.
                    </p>
                </div>
            </section>

            <section className="py-12 lg:py-20">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-10 lg:grid-cols-[1fr_1.5fr] lg:items-start">

                        {/* Contact info */}
                        <div className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                {contactInfo.map(({ icon: Icon, title, value, href }) => (
                                    <div key={title} className="flex items-start gap-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[var(--color-primary)]/10">
                                            <Icon className="size-5 text-[var(--color-primary)]" />
                                        </div>
                                        <div>
                                            <p className="text-xs font-semibold uppercase tracking-[0.15em] text-[var(--color-text-muted)]">{title}</p>
                                            {href ? (
                                                <a href={href} className="mt-0.5 text-sm font-medium text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)]">{value}</a>
                                            ) : (
                                                <p className="mt-0.5 text-sm font-medium text-[var(--color-text-primary)]">{value}</p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Social */}
                            <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-5">
                                <p className="mb-4 text-sm font-semibold text-[var(--color-text-primary)]">Follow us</p>
                                <div className="flex gap-3">
                                    {socialLinks.map(({ icon: Icon, label, href, color }) => (
                                        <a
                                            key={label}
                                            href={href}
                                            target="_blank"
                                            rel="noreferrer"
                                            aria-label={label}
                                            className="flex size-10 items-center justify-center rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] transition hover:scale-105"
                                        >
                                            <Icon className="size-4" style={{ color }} />
                                        </a>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Contact form */}
                        <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-6 sm:p-8">
                            {recentlySuccessful ? (
                                <div className="flex flex-col items-center gap-4 py-8 text-center">
                                    <div className="flex size-16 items-center justify-center rounded-full bg-[var(--color-success)]/10">
                                        <Send className="size-8 text-[var(--color-success)]" />
                                    </div>
                                    <h3 className="text-xl font-bold text-[var(--color-text-primary)]">Message sent!</h3>
                                    <p className="text-sm text-[var(--color-text-secondary)]">We'll get back to you within 24 hours.</p>
                                </div>
                            ) : (
                                <>
                                    <h2 className="mb-6 text-xl font-bold text-[var(--color-text-primary)]">Send us a message</h2>
                                    <form onSubmit={handleSubmit} className="space-y-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-1.5">
                                                <label className="text-sm font-medium text-[var(--color-text-primary)]">Full name</label>
                                                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Your name" className="h-11 w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 text-sm text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)] transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]" required />
                                                <InputError message={errors.name} />
                                            </div>
                                            <div className="space-y-1.5">
                                                <label className="text-sm font-medium text-[var(--color-text-primary)]">Email</label>
                                                <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="you@example.com" className="h-11 w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 text-sm text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)] transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]" required />
                                                <InputError message={errors.email} />
                                            </div>
                                        </div>

                                        <div className="space-y-1.5">
                                            <label className="text-sm font-medium text-[var(--color-text-primary)]">Subject</label>
                                            <input type="text" value={data.subject} onChange={(e) => setData('subject', e.target.value)} placeholder="How can we help?" className="h-11 w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 text-sm text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)] transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]" required />
                                            <InputError message={errors.subject} />
                                        </div>

                                        <div className="space-y-1.5">
                                            <label className="text-sm font-medium text-[var(--color-text-primary)]">Message</label>
                                            <textarea value={data.message} onChange={(e) => setData('message', e.target.value)} rows={5} placeholder="Tell us more about your question or concern..." className="w-full resize-none rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3 text-sm text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)] transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]" required />
                                            <InputError message={errors.message} />
                                        </div>

                                        <button type="submit" disabled={processing} className="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[var(--color-primary)] text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] disabled:opacity-60">
                                            {processing ? <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" /> : <><Send className="size-4" /><span>Send Message</span></>}
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

export default function Contact(): React.JSX.Element {
    return (
        <ContactContent />
    );
}
