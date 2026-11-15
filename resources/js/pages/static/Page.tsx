import { Head, Link } from '@inertiajs/react';
import { Calendar, FileText, MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import { PageHero } from '@/components/shared/PageHero';

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
    if (typeof window === 'undefined') {
        return [];
    }

    const doc = new DOMParser().parseFromString(html, 'text/html');
    const sections: Section[] = [];
    let current: Section | null = null;

    for (const el of Array.from(doc.body.children)) {
        if (el.tagName === 'H2') {
            const heading = el.textContent ?? '';
            const id = slugify(heading) || `section-${sections.length + 1}`;
            current = {
                id,
                heading,
                number: sections.length + 1,
                bodyHtml: '',
            };
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
        // eslint-disable-next-line react-hooks/set-state-in-effect -- reads browser-only APIs after mount so SSR markup matches hydration
        setSections(parsed);

        if (parsed.length > 0) {
            setActiveId(parsed[0].id);
        }
    }, [page.content]);

    useEffect(() => {
        const measure = () => {
            const header = document.querySelector('header');

            if (header) {
                setSidebarTop(header.offsetHeight + 16);
            }
        };
        measure();
        window.addEventListener('resize', measure);

        return () => window.removeEventListener('resize', measure);
    }, []);

    useEffect(() => {
        if (sections.length === 0) {
            return;
        }

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

        if (!el) {
            return;
        }

        const top =
            el.getBoundingClientRect().top + window.scrollY - SCROLL_OFFSET + 8;
        window.scrollTo({ top, behavior: 'smooth' });
        setActiveId(id);
    };

    const formattedDate = new Date(page.updated_at).toLocaleDateString(
        'en-GB',
        {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        },
    );

    return (
        // No min-h-screen: a short policy page used to be stretched to a full screen, leaving a white block above the footer.
        <div className="bg-[var(--color-surface)]">
            <Head title={page.meta_title || page.title} />

            {/* Hero */}
            <div className="pt-4 lg:pt-6">
                <PageHero
                    badge={{ icon: FileText, label: 'Legal' }}
                    eyebrow="ShoppyDrive policies"
                    title={page.title}
                    chips={[
                        {
                            icon: Calendar,
                            label: `Last updated: ${formattedDate}`,
                        },
                    ]}
                />
            </div>

            {/* Body: compact spacing (owner request 2026-11-15) */}
            <div className="mx-auto max-w-7xl px-4 pt-5 pb-10 sm:px-6 lg:px-8 lg:pt-6 lg:pb-12">
                <div className="lg:grid lg:grid-cols-[240px_1fr] lg:gap-10 xl:grid-cols-[260px_1fr]">
                    {/* Sticky sidebar */}
                    {sections.length > 0 && (
                        <aside
                            className="hidden lg:sticky lg:block lg:self-start"
                            style={{ top: sidebarTop }}
                        >
                            <p className="mb-2 px-2.5 text-[10px] font-bold tracking-[0.25em] text-[var(--color-text-secondary)] uppercase">
                                Contents
                            </p>

                            <nav>
                                <div className="space-y-0.5">
                                    {sections.map(({ id, heading, number }) => {
                                        const active = activeId === id;

                                        return (
                                            <button
                                                key={id}
                                                onClick={() => scrollTo(id)}
                                                className={`group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-left text-[13px] transition-all ${
                                                    active
                                                        ? 'bg-[var(--color-primary)]/8 font-semibold text-[var(--color-primary)]'
                                                        : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]'
                                                }`}
                                            >
                                                <span
                                                    className={`inline-flex size-5 shrink-0 items-center justify-center rounded-md text-[10px] font-bold transition-all ${
                                                        active
                                                            ? 'bg-[var(--color-primary)] text-white shadow-[var(--color-primary)]/30 shadow-sm'
                                                            : 'bg-[var(--color-surface-alt)] text-[var(--color-text-secondary)] group-hover:bg-[var(--color-border)]'
                                                    }`}
                                                >
                                                    {number}
                                                </span>
                                                <span className="leading-snug">
                                                    {heading}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>

                                <div className="mt-5 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4">
                                    <div className="flex items-center gap-2.5">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary)]/10">
                                            <MessageCircle className="size-4 text-[var(--color-primary)]" />
                                        </span>
                                        <p className="text-sm font-semibold text-[var(--color-text-primary)]">
                                            Have a question?
                                        </p>
                                    </div>
                                    <p className="mt-2 text-xs leading-5 text-[var(--color-text-secondary)]">
                                        Our support team can clarify anything in
                                        this document.
                                    </p>
                                    <Link
                                        href="/contact"
                                        className="mt-3 flex h-8 items-center justify-center rounded-lg bg-[var(--color-primary)] text-xs font-semibold text-white transition hover:bg-[var(--color-primary-hover)]"
                                    >
                                        Contact us
                                    </Link>
                                </div>
                            </nav>
                        </aside>
                    )}

                    {/* Sections: 24px + divider + 24px between them (was 64 + 64); the body lines up under the heading text. */}
                    <div className="min-w-0 divide-y divide-[var(--color-border)]">
                        {sections.map((section) => (
                            <div
                                key={section.id}
                                id={section.id}
                                className="py-6 first:pt-0 last:pb-0"
                            >
                                {/* Section heading row */}
                                <div className="mb-2.5 flex items-center gap-3">
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary)] text-xs font-bold text-white shadow-[var(--color-primary)]/20 shadow-sm">
                                        {section.number}
                                    </span>
                                    <h2 className="text-lg font-bold tracking-tight text-[var(--color-text-primary)] sm:text-xl">
                                        {section.heading}
                                    </h2>
                                </div>

                                {/* Section body: no extra space after its last block, so the gap to the next section stays even. */}
                                <div
                                    className="prose prose-base max-w-none sm:pl-10 prose-headings:font-semibold prose-headings:tracking-tight prose-headings:text-[var(--color-text-primary)] prose-h3:mt-5 prose-h3:mb-2 prose-h3:text-base prose-h3:font-semibold prose-p:mt-0 prose-p:mb-3 prose-p:leading-7 prose-p:text-[var(--color-text-secondary)] prose-a:font-semibold prose-a:text-[var(--color-primary)] prose-a:no-underline hover:prose-a:underline prose-strong:font-semibold prose-strong:text-[var(--color-text-primary)] prose-ul:mt-2 prose-ul:mb-3 prose-ul:space-y-1 prose-li:my-0 prose-li:leading-7 prose-li:text-[var(--color-text-secondary)] [&_li::marker]:text-[var(--color-primary)] [&>*:last-child]:mb-0"
                                    dangerouslySetInnerHTML={{
                                        __html: section.bodyHtml,
                                    }}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
