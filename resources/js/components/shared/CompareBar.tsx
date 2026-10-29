import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { GitCompare, X, ArrowRight, Trash2 } from 'lucide-react';
import React from 'react';

import { Button } from '@/components/ui/button';
import { useUiStore } from '@/stores/uiStore';
import type { Product } from '@/types';

interface CompareBarProps {
    products: Product[];
}

export function CompareBar({
    products = [],
}: CompareBarProps): React.JSX.Element {
    const removeCompareItem = useUiStore((state) => state.removeCompareItem);
    const clearCompareItems = useUiStore((state) => state.clearCompareItems);

    if (products.length === 0) {
        return <></>;
    }

    return (
        <AnimatePresence>
            <motion.div
                initial={{ y: 100 }}
                animate={{ y: 0 }}
                exit={{ y: 100 }}
                className="fixed inset-x-0 bottom-0 z-[150] px-4 pb-4 md:pb-8"
            >
                <div className="container mx-auto max-w-5xl">
                    <div className="flex flex-col items-center gap-6 rounded-3xl border border-white/10 bg-[var(--color-secondary)] p-4 text-white shadow-[0_20px_60px_rgba(0,0,0,0.3)] md:flex-row md:p-6">
                        <div className="flex items-center gap-4 border-b border-white/10 pb-4 md:border-r md:border-b-0 md:pr-8 md:pb-0">
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-amber-400">
                                <GitCompare size={24} />
                            </div>
                            <div>
                                <h3 className="mb-1 text-lg leading-none font-bold">
                                    Compare Products
                                </h3>
                                <p className="text-xs font-medium text-blue-100">
                                    Select up to 3 items
                                </p>
                            </div>
                        </div>

                        <div className="scrollbar-hide flex flex-1 items-center gap-4 overflow-x-auto py-1">
                            {products.map((product) => (
                                <div
                                    key={product.id}
                                    className="group relative shrink-0"
                                >
                                    <div className="h-14 w-14 overflow-hidden rounded-xl border border-white/10 bg-white/10 md:h-16 md:w-16">
                                        <img
                                            src={
                                                product.primary_image_url ||
                                                product.images[0]?.path
                                            }
                                            alt={product.name}
                                            className="h-full w-full object-cover"
                                        />
                                    </div>
                                    <button
                                        onClick={() =>
                                            removeCompareItem(product.id)
                                        }
                                        className="absolute -top-2 -right-2 flex h-6 w-6 scale-75 items-center justify-center rounded-full bg-[var(--color-error)] text-white opacity-0 shadow-lg transition-all group-hover:scale-100 group-hover:opacity-100"
                                    >
                                        <X size={12} strokeWidth={3} />
                                    </button>
                                </div>
                            ))}

                            {[...Array(Math.max(0, 3 - products.length))].map(
                                (_, i) => (
                                    <div
                                        key={i}
                                        className="flex h-14 w-14 items-center justify-center rounded-xl border-2 border-dashed border-white/10 text-white/20 md:h-16 md:w-16"
                                    >
                                        <GitCompare size={20} />
                                    </div>
                                ),
                            )}
                        </div>

                        <div className="flex items-center gap-4">
                            <button
                                onClick={clearCompareItems}
                                className="flex items-center gap-1.5 text-xs font-bold text-blue-200 transition-colors hover:text-white"
                            >
                                <Trash2 size={14} />
                                Clear
                            </button>
                            <Button
                                asChild
                                className="rounded-xl bg-white px-8 font-bold text-[var(--color-secondary)] hover:bg-amber-400 hover:text-[var(--color-secondary)]"
                            >
                                <Link href={route('compare')}>
                                    Compare Now
                                    <ArrowRight size={16} className="ml-2" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </motion.div>
        </AnimatePresence>
    );
}
