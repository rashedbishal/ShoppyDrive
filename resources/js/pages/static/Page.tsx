import { Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, FileText, MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import { PageMeta } from '@/components/seo/PageMeta';

interface Section {
    id: string;
    heading: string;
    number: number;
    bodyHtml: string;
}

interface PageProps {
    page: {
        title: string;
        content: string;
        meta_title?: string;
        meta_description?: string;
        updated_at: string;
    };
}

function slugify(text: string): string {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function parseContent(html: string): Section[] {
    if (typeof window === 'undefined') return [];

    const doc = new DOMParser().parseFromString(html, 'text/html');
    const sections: Section[] = [];
    let current: Section | null = null;

    for (const el of Array.from(doc.body.children)) {
        if (el.tagName === 'H2') {
            const heading = el.textContent ?? '';
            const id = slugify(heading) || `section-${sections.length + 1}`;
            current = { id, heading, number: sections.length + 1, bodyHtml: '' };
            sections.push(current);
        } else if (current) {
            current.bodyHtml += el.outerHTML;
        }
    }

    return sections;
}

const SCROLL_OFFSET = 112;

export default function Page({ page }: PageProps): React.JSX.Element {
    const [sections, setSections] = useState<Section[]>([]);
    const [activeId, setActiveId] = useState<string>('');
    const [sidebarTop, setSidebarTop] = useState(176);

    useEffect(() => {
        const parsed = parseContent(page.content);
        setSections(parsed);
        if (parsed.length > 0) setActiveId(parsed[0].id);
    }, [page.content]);

    useEffect(() => {
        const measure = () => {
            const header = document.querySelector('header');
            if (header) setSidebarTop(header.offsetHeight + 16);
        };
        measure();
        window.addEventListener('resize', measure);
        return () => window.removeEventListener('resize', measure);
    }, []);

    useEffect(() => {
        if (sections.length === 0) return;

        const onScroll = () => {
            const scrollY = window.scrollY + SCROLL_OFFSET;
            let current = sections[0].id;

            for (const { id } of sections) {
                const el = document.getElementById(id);
                if (el && el.offsetTop <= scrollY) {
                    current = id;
                }
            }

            setActiveId(current);
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        return () => window.removeEventListener('scroll', onScroll);
    }, [sections]);

    const scrollTo = (id: string) => {
        const el = document.getElementById(id);
        if (!el) return;
        const top = el.getBoundingClientRect().top + window.scrollY - SCROLL_OFFSET + 8;
        window.scrollTo({ top, behavior: 'smooth' });
        setActiveId(id);
    };

    const formattedDate = new Date(page.updated_at).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <div className="min-h-screen bg-[var(--color-surface)]">
            <PageMeta title={page.meta_title || page.title} description={page.meta_description} />

            {/* Hero */}
            <section className="relative overflow-hidden bg-gradient-to-br from-[#0f0f18] to-[var(--color-surface)] py-16 lg:py-24">
                <div className="pointer-events-none absolute -left-32 top-0 h-96 w-96 rounded-full bg-[var(--color-primary)] opacity-[0.07] blur-3xl" />
                <div className="pointer-events-none absolute bottom-0 right-0 h-72 w-72 rounded-full bg-[var(--color-primary)] opacity-[0.04] blur-3xl" />

                <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Link
                        href="/"
                        className="mb-6 inline-flex items-center gap-1.5 text-sm text-[var(--color-text-muted)] transition hover:text-[var(--color-primary)]"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to home
                    </Link>

                    <div className="inline-flex items-center gap-2 rounded-full border border-[var(--color-primary)]/25 bg-[var(--color-primary)]/10 px-4 py-2 text-sm font-medium text-[var(--color-primary)]">
                        <FileText className="size-4" />
                        Legal
                    </div>

                    <h1 className="mt-4 max-w-2xl text-2xl font-bold tracking-tight text-white sm:text-4xl lg:text-6xl">
                        {page.title}
                    </h1>

                    <div className="mt-5 flex items-center gap-2 text-sm text-white/40">
                        <Calendar className="size-4" />
                        <span>
                            Last updated:{' '}
                            <span className="text-white/60">{formattedDate}</span>
                        </span>
                    </div>
                </div>
            </section>

            {/* Body */}
            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
                <div className="lg:grid lg:grid-cols-[280px_1fr] lg:gap-14 xl:grid-cols-[300px_1fr]">

                    {/* Sticky sidebar */}
                    {sections.length > 0 && (
                        <aside
                            className="hidden lg:sticky lg:block lg:self-start"
                            style={{ top: sidebarTop }}
                        >
                            <p className="mb-4 text-[10px] font-bold uppercase tracking-[0.25em] text-[var(--color-text-muted)]">
                                Contents
                            </p>

                            <nav>
                                <div className="space-y-1">
                                    {sections.map(({ id, heading, number }) => {
                                        const active = activeId === id;
                                        return (
                                            <button
                                                key={id}
                                                onClick={() => scrollTo(id)}
                                                className={`group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition-all ${
                                                    active
                                                        ? 'bg-[var(--color-primary)]/8 font-semibold text-[var(--color-primary)]'
                                                        : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]'
                                                }`}
                                            >
                                                <span
                                                    className={`inline-flex size-6 shrink-0 items-center justify-center rounded-lg text-[10px] font-bold transition-all ${
                                                        active
                                                            ? 'bg-[var(--color-primary)] text-white shadow-sm shadow-[var(--color-primary)]/30'
                                                            : 'bg-[var(--color-surface-alt)] text-[var(--color-text-muted)] group-hover:bg-[var(--color-border)]'
                                                    }`}
                                                >
                                                    {number}
                                                </span>
                                                <span className="leading-snug">{heading}</span>
                                            </button>
                                        );
                                    })}
                                </div>

                                <div className="mt-8 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-5">
                                    <div className="mb-3 flex size-9 items-center justify-center rounded-xl bg-[var(--color-primary)]/10">
                                        <MessageCircle className="size-4 text-[var(--color-primary)]" />
                                    </div>
                                    <p className="text-sm font-semibold text-[var(--color-text-primary)]">Have a question?</p>
                                    <p className="mt-1 text-xs leading-5 text-[var(--color-text-secondary)]">
                                        Our support team can clarify anything in this document.
                                    </p>
                                    <Link
                                        href="/contact"
                                        className="mt-4 flex h-9 items-center justify-center rounded-xl bg-[var(--color-primary)] text-xs font-semibold text-white transition hover:bg-[var(--color-primary-hover)]"
                                    >
                                        Contact us
                                    </Link>
                                </div>
                            </nav>
                        </aside>
                    )}

                    {/* Sections */}
                    <div className="min-w-0">
                        {sections.map((section, i) => (
                            <div
                                key={section.id}
                                id={section.id}
                                className={`${i < sections.length - 1 ? 'mb-16 border-b border-[var(--color-border)] pb-16' : ''}`}
                            >
                                {/* Section heading row */}
                                <div className="mb-8 flex items-center gap-4">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[var(--color-primary)] text-sm font-bold text-white shadow-sm shadow-[var(--color-primary)]/20">
                                        {section.number}
                                    </span>
                                    <h2 className="text-xl font-bold tracking-tight text-[var(--color-text-primary)] sm:text-2xl">
                                        {section.heading}
                                    </h2>
                                </div>

                                {/* Section body */}
                                <div
                                    className="prose prose-base max-w-none
                                        prose-headings:font-semibold prose-headings:tracking-tight prose-headings:text-[var(--color-text-primary)]
                                        prose-h3:mt-8 prose-h3:mb-3 prose-h3:text-base prose-h3:font-semibold
                                        prose-p:mt-0 prose-p:mb-5 prose-p:text-[var(--color-text-secondary)] prose-p:leading-8
                                        prose-a:text-[var(--color-primary)] prose-a:no-underline prose-a:font-semibold hover:prose-a:underline
                                        prose-strong:text-[var(--color-text-primary)] prose-strong:font-semibold
                                        prose-ul:mt-4 prose-ul:mb-5 prose-ul:space-y-2
                                        prose-li:text-[var(--color-text-secondary)] prose-li:leading-7
                                        [&_li::marker]:text-[var(--color-primary)]"
                                    dangerouslySetInnerHTML={{ __html: section.bodyHtml }}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
