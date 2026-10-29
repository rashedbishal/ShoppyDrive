import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Star } from 'lucide-react';
import { PageHero } from '@/components/shared/PageHero';
import { CategoryIcon } from '@/lib/category-icons';

type AboutStat = { value: string; label: string };
type AboutFeature = { icon: string; label: string };
type AboutValue = { icon: string; title: string; description: string };
type AboutTeamMember = { name: string; role: string; initials: string };

type AboutContent = {
    hero_eyebrow: string;
    hero_title: string;
    hero_title_highlight: string;
    hero_description: string;
    stats: AboutStat[];
    mission_eyebrow: string;
    mission_title: string;
    mission_body: string;
    mission_features: AboutFeature[];
    values: AboutValue[];
    team: AboutTeamMember[];
    cta_title: string;
    cta_description: string;
};

type Props = {
    content: AboutContent;
};

function AboutPage({ content }: Props): React.JSX.Element {
    return (
        <>
            <Head title="About Us | ShoppyDrive" />

            {/* Hero */}
            <div className="py-6 lg:py-8">
                <PageHero
                    badge={{ icon: Star, label: content.hero_eyebrow }}
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
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/products"
                            className="flex h-10 items-center gap-2 rounded-xl bg-[var(--color-primary)] px-5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]"
                        >
                            Shop Now <ArrowRight className="size-4" />
                        </Link>
                        <Link
                            href="/contact"
                            className="flex h-10 items-center gap-2 rounded-xl border border-[var(--color-border)] px-5 text-sm font-medium text-[var(--color-text-secondary)] transition hover:text-[var(--color-text-primary)]"
                        >
                            Contact Us
                        </Link>
                    </div>
                </PageHero>
            </div>

            {/* Stats */}
            {content.stats.length > 0 && (
                <section className="border-y border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                    <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-8">
                            {content.stats.map(({ value, label }, index) => (
                                <div key={index} className="text-center">
                                    <p className="text-3xl font-black text-[var(--color-primary)] sm:text-4xl">
                                        {value}
                                    </p>
                                    <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                                        {label}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Mission */}
            <section className="py-16 lg:py-24">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div className="space-y-6">
                            {content.mission_eyebrow && (
                                <p className="text-sm font-semibold tracking-[0.2em] text-[var(--color-primary)] uppercase">
                                    {content.mission_eyebrow}
                                </p>
                            )}
                            <h2 className="text-3xl font-bold text-[var(--color-text-primary)] sm:text-4xl">
                                {content.mission_title}
                            </h2>
                            <div
                                className="prose prose-sm max-w-none text-[var(--color-text-secondary)] md:prose-base prose-p:leading-7"
                                dangerouslySetInnerHTML={{
                                    __html: content.mission_body,
                                }}
                            />
                        </div>
                        {content.mission_features.length > 0 && (
                            <div className="grid grid-cols-2 gap-4">
                                {content.mission_features.map(
                                    ({ icon, label }, index) => (
                                        <div
                                            key={index}
                                            className="flex flex-col items-center gap-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-6 text-center"
                                        >
                                            <div className="flex size-12 items-center justify-center rounded-xl bg-[var(--color-primary)]/10">
                                                <CategoryIcon
                                                    icon={icon}
                                                    className="size-6 text-[var(--color-primary)]"
                                                />
                                            </div>
                                            <p className="text-sm font-semibold text-[var(--color-text-primary)]">
                                                {label}
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </section>

            {/* Values */}
            {content.values.length > 0 && (
                <section className="border-t border-[var(--color-border)] bg-[var(--color-surface-alt)] py-16 lg:py-24">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-12 text-center">
                            <p className="text-sm font-semibold tracking-[0.2em] text-[var(--color-primary)] uppercase">
                                What We Stand For
                            </p>
                            <h2 className="mt-2 text-3xl font-bold text-[var(--color-text-primary)] sm:text-4xl">
                                Our core values
                            </h2>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {content.values.map(
                                ({ icon, title, description }, index) => (
                                    <div
                                        key={index}
                                        className="space-y-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6"
                                    >
                                        <div className="flex size-11 items-center justify-center rounded-xl bg-[var(--color-primary)]/10">
                                            <CategoryIcon
                                                icon={icon}
                                                className="size-5 text-[var(--color-primary)]"
                                            />
                                        </div>
                                        <h3 className="font-semibold text-[var(--color-text-primary)]">
                                            {title}
                                        </h3>
                                        <p className="text-sm leading-7 text-[var(--color-text-secondary)]">
                                            {description}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </section>
            )}

            {/* Team */}
            {content.team.length > 0 && (
                <section className="py-16 lg:py-24">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-12 text-center">
                            <p className="text-sm font-semibold tracking-[0.2em] text-[var(--color-primary)] uppercase">
                                The People Behind ShoppyDrive
                            </p>
                            <h2 className="mt-2 text-3xl font-bold text-[var(--color-text-primary)] sm:text-4xl">
                                Meet our team
                            </h2>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {content.team.map(
                                ({ name, role, initials }, index) => (
                                    <div
                                        key={index}
                                        className="flex flex-col items-center gap-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-6 text-center"
                                    >
                                        <div className="flex size-16 items-center justify-center rounded-full bg-gradient-to-br from-[var(--color-primary)] to-[var(--color-primary-hover)] text-xl font-bold text-white">
                                            {initials}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-[var(--color-text-primary)]">
                                                {name}
                                            </p>
                                            <p className="text-sm text-[var(--color-text-muted)]">
                                                {role}
                                            </p>
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </section>
            )}

            {/* CTA */}
            <section className="border-t border-[var(--color-border)] bg-[var(--color-surface-alt)] py-16">
                <div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 className="text-3xl font-bold text-[var(--color-text-primary)]">
                        {content.cta_title}
                    </h2>
                    <p className="mx-auto mt-3 max-w-xl text-[var(--color-text-secondary)]">
                        {content.cta_description}
                    </p>
                    <div className="mt-8 flex items-center justify-center gap-4">
                        <Link
                            href="/products"
                            className="flex h-12 items-center gap-2 rounded-xl bg-[var(--color-primary)] px-8 font-semibold text-white transition hover:bg-[var(--color-primary-hover)]"
                        >
                            Browse Products <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </div>
            </section>
        </>
    );
}

export default function About({ content }: Props): React.JSX.Element {
    return <AboutPage content={content} />;
}
