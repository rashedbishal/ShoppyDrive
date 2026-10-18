import { Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowRight, ChevronRight, FolderOpen, ShoppingBag } from 'lucide-react';
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

type MegaMenuProps = {
    isOpen: boolean;
    onClose: () => void;
};

export default function MegaMenu({ isOpen, onClose }: MegaMenuProps): React.JSX.Element {
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
    const activeCategory = activeCategoryIndex >= 0 ? navCategories[activeCategoryIndex] : null;
    const activeColor = activeCategory
        ? resolveCategoryColor(activeCategory.color, activeCategoryIndex)
        : null;

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
                // Check if the click was on the trigger button (handled by Header)
                const target = event.target as HTMLElement;

                if (!target.closest('button')?.textContent?.includes('All Categories')) {
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
                        <div className="relative z-10 flex flex-col items-center gap-2 px-4 py-14 text-[var(--color-text-muted)]">
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
                                    <p className="px-2.5 pt-1 pb-2 text-[11px] font-semibold tracking-[0.2em] text-[var(--color-text-muted)] uppercase">
                                        Browse Categories
                                    </p>
                                    <div className="space-y-0.5">
                                        {navCategories.map((category, index) => {
                                            const isActive = category.id === activeCategory?.id;
                                            const color = resolveCategoryColor(category.color, index);

                                            return (
                                                <motion.div
                                                    key={category.id}
                                                    initial={{ opacity: 0, x: -12 }}
                                                    animate={{ opacity: 1, x: 0 }}
                                                    transition={{ duration: 0.25, delay: Math.min(index * 0.035, 0.3) }}
                                                >
                                                    <Link
                                                        href={`/category/${category.slug}`}
                                                        onClick={onClose}
                                                        onMouseEnter={() => setActiveCategoryId(category.id)}
                                                        onFocus={() => setActiveCategoryId(category.id)}
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
                                                                transition={{ type: 'spring', stiffness: 500, damping: 40 }}
                                                            />
                                                        )}
                                                        <span
                                                            style={{ '--category-color': color } as React.CSSProperties}
                                                            className={cn(
                                                                'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-lg ring-1 transition-all duration-300',
                                                                'bg-[color-mix(in_srgb,var(--category-color)_12%,white)] text-[var(--category-color)] ring-[color-mix(in_srgb,var(--category-color)_18%,white)]',
                                                                isActive &&
                                                                    'bg-[var(--category-color)] text-white ring-[var(--category-color)]',
                                                            )}
                                                        >
                                                            <Suspense fallback={<ShoppingBag className="size-4" strokeWidth={1.75} />}>
                                                                <CategoryIcon icon={category.icon} className="size-4" />
                                                            </Suspense>
                                                        </span>
                                                        <span className="relative z-10 min-w-0 flex-1 truncate font-semibold">
                                                            {category.name}
                                                        </span>
                                                        {category.sub_categories.length > 0 && (
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
                                        })}
                                    </div>
                                </div>

                                {/* Subcategory showcase — collapsed to 0 width until a category is hovered */}
                                <motion.div
                                    className="overflow-hidden"
                                    initial={false}
                                    animate={{ width: activeCategory ? PANE_WIDTH : 0 }}
                                    transition={{ duration: 0.55, ease: [0.4, 0, 0.2, 1] }}
                                >
                                    <div
                                        className="scrollbar-hide h-full overflow-y-auto p-6"
                                        style={{ width: PANE_WIDTH, '--category-color': activeColor ?? 'var(--color-primary)' } as React.CSSProperties}
                                    >
                                        <AnimatePresence mode="wait">
                                            {activeCategory && (
                                                <motion.div
                                                    key={activeCategory.id}
                                                    initial={{ opacity: 0, x: 12 }}
                                                    animate={{ opacity: 1, x: 0 }}
                                                    exit={{ opacity: 0, x: -8 }}
                                                    transition={{ duration: 0.35, delay: 0.1, ease: 'easeOut' }}
                                                >
                                                    <div className="mb-5 flex items-center justify-between gap-4">
                                                        <div>
                                                            <h3 className="font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)]">
                                                                {activeCategory.name}
                                                            </h3>
                                                            {activeCategory.description && (
                                                                <p className="mt-1 max-w-md text-sm text-[var(--color-text-secondary)]">
                                                                    {activeCategory.description}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <Link
                                                            href={`/category/${activeCategory.slug}`}
                                                            onClick={onClose}
                                                            className="group inline-flex shrink-0 items-center gap-1.5 rounded-full border border-[var(--color-border)] px-4 py-2 text-sm font-semibold text-[var(--color-text-primary)] transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
                                                        >
                                                            Shop all
                                                            <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                                                        </Link>
                                                    </div>

                                                    {activeCategory.sub_categories.length > 0 ? (
                                                        <div className="grid grid-cols-2 gap-1">
                                                            {activeCategory.sub_categories.map((sub, i) => (
                                                                <motion.div
                                                                    key={sub.id}
                                                                    initial={{ opacity: 0, y: 8 }}
                                                                    animate={{ opacity: 1, y: 0 }}
                                                                    transition={{ duration: 0.2, delay: Math.min(i * 0.03, 0.24) }}
                                                                >
                                                                    <Link
                                                                        href={`/category/${sub.slug}`}
                                                                        onClick={onClose}
                                                                        className="group flex items-center justify-between gap-2 rounded-xl px-3.5 py-3 text-sm font-medium text-[var(--color-text-secondary)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--category-color)]"
                                                                    >
                                                                        <span className="truncate">{sub.name}</span>
                                                                        <ArrowRight className="size-3.5 shrink-0 text-[var(--color-text-muted)] opacity-0 transition group-hover:translate-x-0.5 group-hover:text-[var(--category-color)] group-hover:opacity-100" />
                                                                    </Link>
                                                                </motion.div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <div className="rounded-2xl border border-dashed border-[var(--color-border)] p-8 text-center text-sm text-[var(--color-text-muted)]">
                                                            No subcategories yet — browse the full collection instead.
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
