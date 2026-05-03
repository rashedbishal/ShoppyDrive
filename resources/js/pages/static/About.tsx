import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Award, Globe, Heart, Package, ShieldCheck, Star, Truck, Users, Zap } from 'lucide-react';

const stats = [
    { value: '50,000+', label: 'Happy Customers' },
    { value: '1,000+', label: 'Products Listed' },
    { value: '64', label: 'Districts Covered' },
    { value: '99%', label: 'Satisfaction Rate' },
];

const values = [
    { icon: ShieldCheck, title: 'Trust & Transparency', desc: 'Honest pricing, verified products, and clear return policies — no hidden surprises.' },
    { icon: Zap, title: 'Speed & Convenience', desc: 'From browsing to doorstep, we make every step fast and frictionless.' },
    { icon: Heart, title: 'Customer First', desc: 'Your satisfaction is our priority. 24/7 support in Bangla and English.' },
    { icon: Globe, title: 'Made for Bangladesh', desc: 'Built for local buyers with BDT pricing, bKash, Nagad, and nationwide delivery.' },
];

const team = [
    { name: 'Rahima Islam', role: 'CEO & Founder', initials: 'RI' },
    { name: 'Karim Hassan', role: 'Head of Operations', initials: 'KH' },
    { name: 'Nadia Chowdhury', role: 'Head of Product', initials: 'NC' },
    { name: 'Arif Rahman', role: 'Tech Lead', initials: 'AR' },
];

function AboutContent(): React.JSX.Element {
    return (
        <>
            <Head title="About Us | ShoppyDrive" />

            {/* Hero */}
            <section className="relative overflow-hidden bg-gradient-to-br from-[#0f0f18] to-[var(--color-surface)] py-20 lg:py-28">
                <div className="pointer-events-none absolute -left-40 top-0 h-96 w-96 rounded-full bg-[var(--color-primary)] opacity-8 blur-3xl" />
                <div className="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-[var(--color-accent)] opacity-5 blur-3xl" />
                <div className="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                    <div className="inline-flex items-center gap-2 rounded-full border border-[var(--color-primary)]/25 bg-[var(--color-primary)]/10 px-4 py-2 text-sm font-medium text-[var(--color-primary)]">
                        <Star className="size-4" />
                        Our Story
                    </div>
                    <h1 className="mt-4 text-4xl font-bold text-[var(--color-text-primary)] sm:text-5xl lg:text-6xl">
                        Bangladesh's Smartest<br />
                        <span className="text-[var(--color-primary)]">Shopping Experience</span>
                    </h1>
                    <p className="mx-auto mt-6 max-w-2xl text-lg text-[var(--color-text-secondary)]">
                        ShoppyDrive was built to make premium shopping accessible to every Bangladeshi — with honest prices, genuine products, and world-class delivery.
                    </p>
                    <div className="mt-8 flex items-center justify-center gap-4">
                        <Link href="/products" className="flex h-12 items-center gap-2 rounded-xl bg-[var(--color-primary)] px-6 font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">
                            Shop Now <ArrowRight className="size-4" />
                        </Link>
                        <Link href="/contact" className="flex h-12 items-center gap-2 rounded-xl border border-[var(--color-border)] px-6 text-sm font-medium text-[var(--color-text-secondary)] transition hover:text-[var(--color-text-primary)]">
                            Contact Us
                        </Link>
                    </div>
                </div>
            </section>

            {/* Stats */}
            <section className="border-y border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-2 gap-8 lg:grid-cols-4">
                        {stats.map(({ value, label }) => (
                            <div key={label} className="text-center">
                                <p className="text-3xl font-black text-[var(--color-primary)] sm:text-4xl">{value}</p>
                                <p className="mt-1 text-sm text-[var(--color-text-secondary)]">{label}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Mission */}
            <section className="py-16 lg:py-24">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div className="space-y-6">
                            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--color-primary)]">Our Mission</p>
                            <h2 className="text-3xl font-bold text-[var(--color-text-primary)] sm:text-4xl">
                                Empowering Bangladeshis to shop with confidence
                            </h2>
                            <p className="text-[var(--color-text-secondary)] leading-7">
                                We started ShoppyDrive with a simple vision: create a shopping platform that Bangladeshis can truly trust. No misleading prices, no counterfeit products, no complicated processes.
                            </p>
                            <p className="text-[var(--color-text-secondary)] leading-7">
                                From Dhaka to Cox's Bazar, we deliver genuine products at fair prices with a shopping experience that feels premium — regardless of what device you're using.
                            </p>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            {[
                                { icon: Package, label: '1,000+ Products' },
                                { icon: Truck, label: 'Nationwide Delivery' },
                                { icon: Users, label: '50K+ Customers' },
                                { icon: Award, label: 'Quality Assured' },
                            ].map(({ icon: Icon, label }) => (
                                <div key={label} className="flex flex-col items-center gap-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-6 text-center">
                                    <div className="flex size-12 items-center justify-center rounded-xl bg-[var(--color-primary)]/10">
                                        <Icon className="size-6 text-[var(--color-primary)]" />
                                    </div>
                                    <p className="text-sm font-semibold text-[var(--color-text-primary)]">{label}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* Values */}
            <section className="border-t border-[var(--color-border)] bg-[var(--color-surface-alt)] py-16 lg:py-24">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-12 text-center">
                        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--color-primary)]">What We Stand For</p>
                        <h2 className="mt-2 text-3xl font-bold text-[var(--color-text-primary)] sm:text-4xl">Our core values</h2>
                    </div>
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {values.map(({ icon: Icon, title, desc }) => (
                            <div key={title} className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6 space-y-4">
                                <div className="flex size-11 items-center justify-center rounded-xl bg-[var(--color-primary)]/10">
                                    <Icon className="size-5 text-[var(--color-primary)]" />
                                </div>
                                <h3 className="font-semibold text-[var(--color-text-primary)]">{title}</h3>
                                <p className="text-sm leading-7 text-[var(--color-text-secondary)]">{desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Team */}
            <section className="py-16 lg:py-24">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-12 text-center">
                        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--color-primary)]">The People Behind ShoppyDrive</p>
                        <h2 className="mt-2 text-3xl font-bold text-[var(--color-text-primary)] sm:text-4xl">Meet our team</h2>
                    </div>
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {team.map(({ name, role, initials }) => (
                            <div key={name} className="flex flex-col items-center gap-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-6 text-center">
                                <div className="flex size-16 items-center justify-center rounded-full bg-gradient-to-br from-[var(--color-primary)] to-[var(--color-primary-hover)] text-xl font-bold text-white">
                                    {initials}
                                </div>
                                <div>
                                    <p className="font-semibold text-[var(--color-text-primary)]">{name}</p>
                                    <p className="text-sm text-[var(--color-text-muted)]">{role}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className="border-t border-[var(--color-border)] bg-[var(--color-surface-alt)] py-16">
                <div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 className="text-3xl font-bold text-[var(--color-text-primary)]">Ready to start shopping?</h2>
                    <p className="mx-auto mt-3 max-w-xl text-[var(--color-text-secondary)]">
                        Join thousands of happy customers and discover why ShoppyDrive is Bangladesh's most trusted online store.
                    </p>
                    <div className="mt-8 flex items-center justify-center gap-4">
                        <Link href="/products" className="flex h-12 items-center gap-2 rounded-xl bg-[var(--color-primary)] px-8 font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">
                            Browse Products <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </div>
            </section>
        </>
    );
}

export default function About(): React.JSX.Element {
    return (
        <AboutContent />
    );
}
