import { Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    ArrowRight,
    ChevronRight,
    FolderOpen,
    ShoppingBag,
} from 'lucide-react';
import { lazy, Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { resolveCategoryColor } from '@/lib/category-colors';
import { cn } from '@/lib/utils';
import type { Id, SharedPageProps } from '@/types';

// Lazy-loaded: lucide-react/dynamic's icon registry is large (~50KB gzip) and
// Header/MegaMenu is part of the eagerly-bundled main chunk — deferring this
// import keeps that cost out of every page load, paid only when the menu opens.
const CategoryIcon = lazy(() =>
    import('@/lib/category-icons').then((m) => ({ default: m.CategoryIcon })),
);

const PANE_WIDTH = 420;
const DESCRIPTION_MAX_LENGTH = 90;

/**
 * Keep the flyout's blurb to a one-glance teaser: first sentence only, capped
 * at a word boundary so long admin-written descriptions don't bloat the pane.
 */
function shortenDescription(description: string): string {
    const firstSentence = description.trim().split(/(?<=[.!?।])\s+/)[0] ?? '';

    if (firstSentence.length <= DESCRIPTION_MAX_LENGTH) {
        return firstSentence;
    }

    const cut = firstSentence.slice(0, DESCRIPTION_MAX_LENGTH);
    const lastSpace = cut.lastIndexOf(' ');

    return `${(lastSpace > 40 ? cut.slice(0, lastSpace) : cut).replace(/[\s,;:.-]+$/, '')}…`;
}

type MegaMenuProps = {
    isOpen: boolean;
    onClose: () => void;
};

export default function MegaMenu({
    isOpen,
    onClose,
}: MegaMenuProps): React.JSX.Element {
    const { navCategories } = usePage<SharedPageProps>().props;
    const menuRef = useRef<HTMLDivElement>(null);
    const [activeCategoryId, setActiveCategoryId] = useState<Id | null>(null);
    const [wasOpen, setWasOpen] = useState(isOpen);

    // Only the category rail shows when the menu first opens — the subcategory
    // pane only appears once a category is hovered. Collapse back to that state
    // every time the menu closes, so it always starts fresh next time. Reset
    // during render (React's recommended pattern) rather than in an effect, so
    // it happens before the collapsed state ever paints.
    if (isOpen !== wasOpen) {
        setWasOpen(isOpen);

        if (!isOpen) {
            setActiveCategoryId(null);
        }
    }

    const activeCategoryIndex = useMemo(
        () => navCategories.findIndex((c) => c.id === activeCategoryId),
        [navCategories, activeCategoryId],
    );
    const activeCategory =
        activeCategoryIndex >= 0 ? navCategories[activeCategoryIndex] : null;
    const activeColor = activeCategory
        ? resolveCategoryColor(activeCategory.color, activeCategoryIndex)
        : null;

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (
                menuRef.current &&
                !menuRef.current.contains(event.target as Node)
            ) {
                // Check if the click was on the trigger button (handled by Header)
                const target = event.target as HTMLElement;

                if (
                    !target
                        .closest('button')
                        ?.textContent?.includes('All Categories')
                ) {
                    onClose();
                }
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [isOpen, onClose]);

    return (
        <AnimatePresence>
            {isOpen ? (
                <motion.div
                    ref={menuRef}
                    className="absolute top-full left-0 z-40 mt-2 hidden max-w-[calc(100vw-2rem)] overflow-hidden rounded-[28px] border border-[var(--color-border)] bg-surface/90 shadow-[var(--shadow-dropdown)] backdrop-blur-2xl lg:block"
                    style={{ maxHeight: 'calc(100vh - 5rem)' }}
                    initial={{ opacity: 0, y: -16, scale: 0.98 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: -12, scale: 0.98 }}
                    transition={{ duration: 0.28, ease: [0.16, 1, 0.3, 1] }}
                >
                    {/* Decorative ambient glow — pure polish, no interaction */}
                    <div className="pointer-events-none absolute -top-24 -right-24 size-[420px] rounded-full bg-[var(--color-primary)]/10 blur-[110px]" />
                    <div className="pointer-events-none absolute -bottom-32 -left-24 size-[360px] rounded-full bg-[var(--color-accent)]/10 blur-[110px]" />

                    {navCategories.length === 0 ? (
                        <div className="relative z-10 flex flex-col items-center gap-2 px-4 py-14 text-[var(--color-text-secondary)]">
                            <FolderOpen className="size-8 opacity-40" />
                            <p className="text-sm">No categories yet</p>
                        </div>
                    ) : (
                        <div className="relative z-10">
                            <div
                                className="flex"
                                style={{ maxHeight: 'calc(100vh - 5rem)' }}
                            >
                                {/* Category rail */}
                                <div className="scrollbar-hide w-[260px] shrink-0 overflow-y-auto border-r border-[var(--color-border)]/70 p-2">
                                    <p className="px-2.5 pt-1 pb-2 text-[11px] font-semibold tracking-[0.2em] text-[var(--color-text-secondary)] uppercase">
                                        Browse Categories
                                    </p>
                                    <div className="space-y-0.5">
                                        {navCategories.map(
                                            (category, index) => {
                                                const isActive =
                                                    category.id ===
                                                    activeCategory?.id;
                                                const color =
                                                    resolveCategoryColor(
                                                        category.color,
                                                        index,
                                                    );

                                                return (
                                                    <motion.div
                                                        key={category.id}
                                                        initial={{
                                                            opacity: 0,
                                                            x: -12,
                                                        }}
                                                        animate={{
                                                            opacity: 1,
                                                            x: 0,
                                                        }}
                                                        transition={{
                                                            duration: 0.25,
                                                            delay: Math.min(
                                                                index * 0.035,
                                                                0.3,
                                                            ),
                                                        }}
                                                    >
                                                        <Link
                                                            href={`/category/${category.slug}`}
                                                            onClick={onClose}
                                                            onMouseEnter={() =>
                                                                setActiveCategoryId(
                                                                    category.id,
                                                                )
                                                            }
                                                            onFocus={() =>
                                                                setActiveCategoryId(
                                                                    category.id,
                                                                )
                                                            }
                                                            className={cn(
                                                                'group relative flex items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-sm transition-colors',
                                                                isActive
                                                                    ? 'text-[var(--color-text-primary)]'
                                                                    : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
                                                            )}
                                                        >
                                                            {isActive && (
                                                                <motion.span
                                                                    layoutId="mega-menu-active-pill"
                                                                    className="absolute inset-0 rounded-xl bg-[var(--color-primary-light)]"
                                                                    transition={{
                                                                        type: 'spring',
                                                                        stiffness: 500,
                                                                        damping: 40,
                                                                    }}
                                                                />
                                                            )}
                                                            <span
                                                                style={
                                                                    {
                                                                        '--category-color':
                                                                            color,
                                                                    } as React.CSSProperties
                                                                }
                                                                className={cn(
                                                                    'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-lg ring-1 transition-all duration-300',
                                                                    'bg-[color-mix(in_srgb,var(--category-color)_12%,white)] text-[var(--category-color)] ring-[color-mix(in_srgb,var(--category-color)_18%,white)]',
                                                                    isActive &&
                                                                        'bg-[var(--category-color)] text-white ring-[var(--category-color)]',
                                                                )}
                                                            >
                                                                <Suspense
                                                                    fallback={
                                                                        <ShoppingBag
                                                                            className="size-4"
                                                                            strokeWidth={
                                                                                1.75
                                                                            }
                                                                        />
                                                                    }
                                                                >
                                                                    <CategoryIcon
                                                                        icon={
                                                                            category.icon
                                                                        }
                                                                        className="size-4"
                                                                    />
                                                                </Suspense>
                                                            </span>
                                                            <span className="relative z-10 min-w-0 flex-1 truncate font-semibold">
                                                                {category.name}
                                                            </span>
                                                            {category
                                                                .sub_categories
                                                                .length > 0 && (
                                                                <ChevronRight
                                                                    className={cn(
                                                                        'relative z-10 size-4 shrink-0 transition-transform',
                                                                        isActive
                                                                            ? 'translate-x-0.5 text-[var(--color-primary)]'
                                                                            : 'text-[var(--color-text-muted)]',
                                                                    )}
                                                                />
                                                            )}
                                                        </Link>
                                                    </motion.div>
                                                );
                                            },
                                        )}
                                    </div>
                                </div>

                                {/* Subcategory showcase — collapsed to 0 width until a category is hovered */}
                                <motion.div
                                    className="overflow-hidden"
                                    initial={false}
                                    animate={{
                                        width: activeCategory ? PANE_WIDTH : 0,
                                    }}
                                    transition={{
                                        duration: 0.55,
                                        ease: [0.4, 0, 0.2, 1],
                                    }}
                                >
                                    <div
                                        className="scrollbar-hide h-full overflow-y-auto p-3"
                                        style={
                                            {
                                                width: PANE_WIDTH,
                                                '--category-color':
                                                    activeColor ??
                                                    'var(--color-primary)',
                                            } as React.CSSProperties
                                        }
                                    >
                                        <AnimatePresence mode="wait">
                                            {activeCategory && (
                                                <motion.div
                                                    key={activeCategory.id}
                                                    initial={{
                                                        opacity: 0,
                                                        x: 12,
                                                    }}
                                                    animate={{
                                                        opacity: 1,
                                                        x: 0,
                                                    }}
                                                    exit={{
                                                        opacity: 0,
                                                        x: -8,
                                                        transition: {
                                                            duration: 0.12,
                                                            ease: 'easeIn',
                                                        },
                                                    }}
                                                    transition={{
                                                        duration: 0.25,
                                                        delay: 0.03,
                                                        ease: 'easeOut',
                                                    }}
                                                >
                                                    {/* Header card — tinted with the category's own color */}
                                                    <div className="relative overflow-hidden rounded-2xl bg-[color-mix(in_srgb,var(--category-color)_9%,transparent)] p-4 ring-1 ring-[color-mix(in_srgb,var(--category-color)_16%,transparent)]">
                                                        <div className="pointer-events-none absolute -top-10 -right-10 size-32 rounded-full bg-[var(--category-color)] opacity-15 blur-2xl" />
                                                        <div className="relative flex items-start gap-3">
                                                            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--category-color)] text-white shadow-[0_6px_16px_-6px_var(--category-color)]">
                                                                <Suspense
                                                                    fallback={
                                                                        <ShoppingBag
                                                                            className="size-5"
                                                                            strokeWidth={
                                                                                1.75
                                                                            }
                                                                        />
                                                                    }
                                                                >
                                                                    <CategoryIcon
                                                                        icon={
                                                                            activeCategory.icon
                                                                        }
                                                                        className="size-5"
                                                                    />
                                                                </Suspense>
                                                            </span>
                                                            {activeCategory.image && (
                                                                <img
                                                                    key={
                                                                        activeCategory.id
                                                                    }
                                                                    src={
                                                                        activeCategory.image
                                                                    }
                                                                    alt=""
                                                                    onError={(
                                                                        e,
                                                                    ) => {
                                                                        e.currentTarget.style.display =
                                                                            'none';
                                                                    }}
                                                                    className="size-11 shrink-0 rounded-xl border border-[var(--color-border)] bg-white object-contain p-1.5"
                                                                />
                                                            )}
                                                            <div className="min-w-0 flex-1">
                                                                <h3 className="truncate font-display text-lg leading-tight font-semibold tracking-tight text-[var(--color-text-primary)]">
                                                                    {
                                                                        activeCategory.name
                                                                    }
                                                                </h3>
                                                                {activeCategory.description && (
                                                                    <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                                                        {shortenDescription(
                                                                            activeCategory.description,
                                                                        )}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <Link
                                                            href={`/category/${activeCategory.slug}`}
                                                            prefetch
                                                            onClick={onClose}
                                                            className="group relative mt-3 inline-flex items-center gap-1.5 rounded-full bg-[var(--category-color)] px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:brightness-110"
                                                        >
                                                            Shop all{' '}
                                                            {
                                                                activeCategory.name
                                                            }
                                                            <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                                                        </Link>
                                                    </div>

                                                    {activeCategory
                                                        .sub_categories.length >
                                                    0 ? (
                                                        <>
                                                            <p className="px-2 pt-4 pb-1.5 text-[11px] font-semibold tracking-[0.2em] text-[var(--color-text-secondary)] uppercase">
                                                                Subcategories
                                                                <span className="ml-1.5 tracking-normal text-[var(--category-color)]">
                                                                    {
                                                                        activeCategory
                                                                            .sub_categories
                                                                            .length
                                                                    }
                                                                </span>
                                                            </p>
                                                            <div className="grid grid-cols-2 gap-1">
                                                                {activeCategory.sub_categories.map(
                                                                    (
                                                                        sub,
                                                                        i,
                                                                    ) => (
                                                                        <motion.div
                                                                            key={
                                                                                sub.id
                                                                            }
                                                                            initial={{
                                                                                opacity: 0,
                                                                                y: 6,
                                                                            }}
                                                                            animate={{
                                                                                opacity: 1,
                                                                                y: 0,
                                                                            }}
                                                                            transition={{
                                                                                duration: 0.2,
                                                                                delay: Math.min(
                                                                                    i *
                                                                                        0.025,
                                                                                    0.2,
                                                                                ),
                                                                            }}
                                                                        >
                                                                            <Link
                                                                                href={`/category/${sub.slug}`}
                                                                                prefetch
                                                                                onClick={
                                                                                    onClose
                                                                                }
                                                                                className="group flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-[var(--color-text-secondary)] transition hover:bg-[color-mix(in_srgb,var(--category-color)_8%,transparent)] hover:text-[var(--color-text-primary)]"
                                                                            >
                                                                                <span className="size-1.5 shrink-0 rounded-full bg-[var(--color-border)] transition group-hover:scale-125 group-hover:bg-[var(--category-color)]" />
                                                                                <span className="min-w-0 flex-1 truncate">
                                                                                    {
                                                                                        sub.name
                                                                                    }
                                                                                </span>
                                                                                <ChevronRight className="size-3.5 shrink-0 -translate-x-1 text-[var(--category-color)] opacity-0 transition group-hover:translate-x-0 group-hover:opacity-100" />
                                                                            </Link>
                                                                        </motion.div>
                                                                    ),
                                                                )}
                                                            </div>
                                                        </>
                                                    ) : (
                                                        <div className="mt-3 rounded-2xl border border-dashed border-[var(--color-border)] px-6 py-8 text-center text-sm text-[var(--color-text-secondary)]">
                                                            No subcategories yet
                                                            — browse the full
                                                            collection instead.
                                                        </div>
                                                    )}
                                                </motion.div>
                                            )}
                                        </AnimatePresence>
                                    </div>
                                </motion.div>
                            </div>
                        </div>
                    )}
                </motion.div>
            ) : null}
        </AnimatePresence>
    );
}
