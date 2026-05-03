import { Link } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, Sparkles } from 'lucide-react';
import { AnimatePresence, motion } from 'framer-motion';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    featuredStorePicks,
    storeCategories,
} from '@/components/layout/navigation';

type MegaMenuProps = {
    isOpen: boolean;
    onClose: () => void;
};

export default function MegaMenu({ isOpen, onClose }: MegaMenuProps): React.JSX.Element {
    return (
        <AnimatePresence>
            {isOpen ? (
                <motion.div
                    className="absolute inset-x-0 top-full z-40 hidden border-b border-[var(--color-border)] bg-surface/95 shadow-[var(--shadow-dropdown)] backdrop-blur-xl lg:block"
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -10 }}
                    transition={{ duration: 0.22 }}
                    onMouseLeave={onClose}
                >
                    <div className="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[1.25fr_0.75fr] lg:px-8">
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {storeCategories.map((category) => (
                                <Link
                                    key={category.label}
                                    href={category.href}
                                    className="group rounded-2xl border border-[var(--color-border)] bg-surface p-4 shadow-[var(--shadow-card)] transition hover:-translate-y-0.5 hover:shadow-[var(--shadow-card-hover)]"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="space-y-2">
                                            <div className="inline-flex items-center gap-2">
                                                <span className="size-2 rounded-full bg-[var(--color-primary)]" />
                                                <p className="text-sm font-semibold text-[var(--color-text-primary)]">
                                                    {category.label}
                                                </p>
                                            </div>
                                            <p className="text-sm leading-6 text-[var(--color-text-secondary)]">
                                                {category.description}
                                            </p>
                                        </div>
                                        <ArrowRight className="mt-1 size-4 text-[var(--color-text-muted)] transition group-hover:translate-x-0.5 group-hover:text-[var(--color-primary)]" />
                                    </div>
                                </Link>
                            ))}
                        </div>

                        <div className="space-y-4 rounded-3xl bg-[linear-gradient(180deg,_rgba(26,26,46,0.98),_rgba(26,26,46,0.92))] p-5 text-white shadow-[var(--shadow-modal)]">
                            <div className="flex items-center justify-between gap-3">
                                <div className="space-y-1">
                                    <div className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-white/80">
                                        <Sparkles className="size-3.5" />
                                        Featured picks
                                    </div>
                                    <h3 className="text-xl font-semibold tracking-tight">
                                        Shop the bestsellers
                                    </h3>
                                </div>
                                <Badge variant="amber" size="sm">
                                    Curated
                                </Badge>
                            </div>

                            <div className="space-y-3">
                                {featuredStorePicks.map((pick) => (
                                    <Link
                                        key={pick.label}
                                        href={pick.href}
                                        className="flex items-start justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:bg-white/10"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <p className="font-semibold text-white">
                                                    {pick.label}
                                                </p>
                                                {pick.badge ? (
                                                    <Badge className="bg-white/10 text-white" size="sm">
                                                        {pick.badge}
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="text-sm leading-6 text-white/70">
                                                {pick.description}
                                            </p>
                                        </div>
                                        <ArrowRight className="mt-1 size-4 shrink-0 text-white/60" />
                                    </Link>
                                ))}
                            </div>

                            <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <div className="flex items-start gap-3">
                                    <BadgeCheck className="mt-0.5 size-5 text-[var(--color-accent)]" />
                                    <div className="space-y-1">
                                        <p className="font-semibold">
                                            Built for trust and conversion
                                        </p>
                                        <p className="text-sm leading-6 text-white/70">
                                            Strong category discovery, persuasive product cards, and quick access to buying paths.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Button asChild fullWidth className="bg-white text-[var(--color-secondary)] hover:bg-white/90">
                                <Link href="/products">Browse all products</Link>
                            </Button>
                        </div>
                    </div>
                </motion.div>
            ) : null}
        </AnimatePresence>
    );
}

