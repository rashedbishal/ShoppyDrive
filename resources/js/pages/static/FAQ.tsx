import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronDown, HelpCircle, MessageCircle, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { PageMeta } from '@/components/seo/PageMeta';
import { PageHero } from '@/components/shared/PageHero';

interface FaqItem {
    question: string;
    answer: string;
}

interface FaqGroup {
    title: string;
    items: FaqItem[];
}

interface PageProps {
    page: {
        title: string;
        content: string;
        meta_title?: string;
        meta_description?: string;
    };
}

function parseHtmlToGroups(html: string): FaqGroup[] {
    if (typeof window === 'undefined') return [];

    const doc = new DOMParser().parseFromString(html, 'text/html');
    const groups: FaqGroup[] = [];
    let currentGroup: FaqGroup | null = null;
    let currentItem: FaqItem | null = null;

    for (const el of Array.from(doc.body.children)) {
        if (el.tagName === 'H2') {
            currentGroup = { title: el.textContent ?? '', items: [] };
            groups.push(currentGroup);
            currentItem = null;
        } else if (el.tagName === 'H3' && currentGroup) {
            currentItem = { question: el.textContent ?? '', answer: '' };
            currentGroup.items.push(currentItem);
        } else if ((el.tagName === 'P' || el.tagName === 'UL') && currentItem) {
            currentItem.answer += el.outerHTML;
        }
    }

    return groups;
}

function AccordionItem({
    question,
    answer,
    isOpen,
    onToggle,
}: {
    question: string;
    answer: string;
    isOpen: boolean;
    onToggle: () => void;
}): React.JSX.Element {
    return (
        <div
            className={`overflow-hidden rounded-2xl border transition-all duration-200 ${
                isOpen
                    ? 'border-[var(--color-primary)]/30 bg-[var(--color-primary)]/[0.03] shadow-sm'
                    : 'border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-border-strong)]'
            }`}
        >
            <button
                onClick={onToggle}
                aria-expanded={isOpen}
                className="flex w-full items-center justify-between gap-6 px-6 py-5 text-left"
            >
                <span
                    className={`text-sm font-semibold leading-snug transition-colors sm:text-base ${
                        isOpen ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-primary)]'
                    }`}
                >
                    {question}
                </span>
                <span
                    className={`flex size-7 shrink-0 items-center justify-center rounded-lg transition-all duration-200 ${
                        isOpen
                            ? 'bg-[var(--color-primary)] text-white'
                            : 'bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]'
                    }`}
                >
                    <ChevronDown
                        className={`size-4 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                    />
                </span>
            </button>

            <AnimatePresence initial={false}>
                {isOpen && (
                    <motion.div
                        key="answer"
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.22, ease: [0.4, 0, 0.2, 1] }}
                    >
                        <div
                            className="border-t border-[var(--color-border)] px-6 py-5 text-sm leading-7 text-[var(--color-text-secondary)]
                                [&_a]:font-semibold [&_a]:text-[var(--color-primary)] [&_a:hover]:underline
                                [&_strong]:font-semibold [&_strong]:text-[var(--color-text-primary)]
                                [&_ul]:mt-2 [&_ul]:space-y-1.5 [&_ul]:pl-4
                                [&_li]:leading-6 [&_li::marker]:text-[var(--color-primary)]"
                            dangerouslySetInnerHTML={{ __html: answer }}
                        />
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

export default function FAQ({ page }: PageProps): React.JSX.Element {
    const [groups, setGroups] = useState<FaqGroup[]>([]);
    const [openItems, setOpenItems] = useState<Record<string, boolean>>({});
    const [query, setQuery] = useState('');

    useEffect(() => {
        setGroups(parseHtmlToGroups(page.content));
    }, [page.content]);

    const filtered = useMemo(() => {
        if (!query.trim()) return groups;
        const q = query.toLowerCase();
        return groups
            .map((g) => ({
                ...g,
                items: g.items.filter(
                    (item) =>
                        item.question.toLowerCase().includes(q) ||
                        item.answer.toLowerCase().includes(q),
                ),
            }))
            .filter((g) => g.items.length > 0);
    }, [groups, query]);

    const totalQuestions = groups.reduce((sum, g) => sum + g.items.length, 0);

    const toggle = (groupTitle: string, index: number) => {
        const key = `${groupTitle}-${index}`;
        setOpenItems((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    return (
        <div className="min-h-screen bg-[var(--color-surface)]">
            <PageMeta title={page.meta_title || page.title} description={page.meta_description} />

            {/* Hero */}
            <div className="pt-6 lg:pt-8">
                <PageHero
                    badge={{ icon: HelpCircle, label: 'Help Centre' }}
                    eyebrow="Support"
                    title={
                        <>
                            Frequently Asked{' '}
                            <span className="text-[var(--color-primary)]">Questions</span>
                        </>
                    }
                    description={
                        <>
                            Everything you need to know about shopping, delivery, and returns. Can't find an answer?{' '}
                            <Link href="/contact" className="text-[var(--color-primary)] hover:underline">
                                Contact our team
                            </Link>
                            .
                        </>
                    }
                    chips={[{ icon: MessageCircle, label: `${totalQuestions} answered questions` }]}
                    aside={
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <input
                                type="text"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder={`Search ${totalQuestions} questions…`}
                                aria-label="Search questions"
                                className="h-11 w-full rounded-full border border-[var(--color-border)] bg-[var(--color-surface)] pl-11 pr-16 text-sm text-[var(--color-text-primary)] shadow-sm placeholder:text-[var(--color-text-muted)] transition focus:border-[var(--color-primary)]/40 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/15"
                            />
                            {query && (
                                <button
                                    type="button"
                                    onClick={() => setQuery('')}
                                    className="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-[var(--color-text-muted)] transition hover:text-[var(--color-text-primary)]"
                                >
                                    Clear
                                </button>
                            )}
                        </div>
                    }
                />
            </div>

            {/* Accordions */}
            <section className="mx-auto max-w-3xl px-4 pt-8 pb-12 sm:px-6 lg:pb-16">
                {filtered.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 py-20 text-center">
                        <div className="flex size-16 items-center justify-center rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                            <Search className="size-6 text-[var(--color-text-muted)]" />
                        </div>
                        <p className="font-semibold text-[var(--color-text-primary)]">
                            No results for &ldquo;{query}&rdquo;
                        </p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                            Try a different search term or{' '}
                            <Link href="/contact" className="text-[var(--color-primary)] hover:underline">
                                contact us
                            </Link>{' '}
                            directly.
                        </p>
                        <button
                            onClick={() => setQuery('')}
                            className="mt-1 text-sm font-semibold text-[var(--color-primary)] hover:underline"
                        >
                            Clear search
                        </button>
                    </div>
                ) : (
                    <div className="space-y-10">
                        {filtered.map((group) => (
                            <div key={group.title}>
                                {/* Group header */}
                                <div className="mb-5 flex items-center gap-3">
                                    <span className="flex size-9 items-center justify-center rounded-xl bg-[var(--color-primary)]/10">
                                        <HelpCircle className="size-4 text-[var(--color-primary)]" />
                                    </span>
                                    <h2 className="text-base font-bold text-[var(--color-text-primary)] sm:text-lg">
                                        {group.title}
                                    </h2>
                                    <span className="ml-auto rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-2.5 py-0.5 text-xs font-medium text-[var(--color-text-muted)]">
                                        {group.items.length}
                                    </span>
                                </div>

                                <div className="space-y-2">
                                    {group.items.map((item, i) => (
                                        <AccordionItem
                                            key={i}
                                            question={item.question}
                                            answer={item.answer}
                                            isOpen={!!openItems[`${group.title}-${i}`]}
                                            onToggle={() => toggle(group.title, i)}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* CTA */}
                <div className="mt-16 flex flex-col items-center gap-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-6 py-10 text-center">
                    <div className="flex size-12 items-center justify-center rounded-2xl bg-[var(--color-primary)]/10">
                        <MessageCircle className="size-5 text-[var(--color-primary)]" />
                    </div>
                    <div className="space-y-1">
                        <h3 className="font-bold text-[var(--color-text-primary)]">
                            Still have questions?
                        </h3>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                            Our support team is available every day from 9 AM to 9 PM (BST).
                        </p>
                    </div>
                    <Link
                        href="/contact"
                        className="flex h-10 items-center gap-2 rounded-xl bg-[var(--color-primary)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]"
                    >
                        <MessageCircle className="size-4" />
                        Contact Support
                    </Link>
                </div>
            </section>
        </div>
    );
}
