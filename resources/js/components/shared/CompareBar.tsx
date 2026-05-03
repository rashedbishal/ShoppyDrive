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

export function CompareBar({ products = [] }: CompareBarProps): React.JSX.Element {
    const removeCompareItem = useUiStore((state) => state.removeCompareItem);
    const clearCompareItems = useUiStore((state) => state.clearCompareItems);

    if (products.length === 0) return <></>;

    return (
        <AnimatePresence>
            <motion.div
                initial={{ y: 100 }}
                animate={{ y: 0 }}
                exit={{ y: 100 }}
                className="fixed bottom-0 inset-x-0 z-[150] px-4 pb-4 md:pb-8"
            >
                <div className="container mx-auto max-w-5xl">
                    <div className="bg-[var(--color-secondary)] text-white rounded-3xl p-4 md:p-6 shadow-[0_20px_60px_rgba(0,0,0,0.3)] border border-white/10 flex flex-col md:flex-row items-center gap-6">
                        <div className="flex items-center gap-4 border-b md:border-b-0 md:border-r border-white/10 pb-4 md:pb-0 md:pr-8">
                            <div className="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-amber-400">
                                <GitCompare size={24} />
                            </div>
                            <div>
                                <h3 className="text-lg font-bold leading-none mb-1">Compare Products</h3>
                                <p className="text-xs text-blue-100 font-medium">Select up to 3 items</p>
                            </div>
                        </div>

                        <div className="flex-1 flex items-center gap-4 overflow-x-auto scrollbar-hide py-1">
                            {products.map((product) => (
                                <div key={product.id} className="relative shrink-0 group">
                                    <div className="w-14 h-14 md:w-16 md:h-16 rounded-xl overflow-hidden bg-white/10 border border-white/10">
                                        <img 
                                            src={product.primary_image_url || product.images[0]?.path} 
                                            alt={product.name} 
                                            className="w-full h-full object-cover" 
                                        />
                                    </div>
                                    <button 
                                        onClick={() => removeCompareItem(product.id)}
                                        className="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-[var(--color-error)] text-white flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 transition-all scale-75 group-hover:scale-100"
                                    >
                                        <X size={12} strokeWidth={3} />
                                    </button>
                                </div>
                            ))}
                            
                            {[...Array(Math.max(0, 3 - products.length))].map((_, i) => (
                                <div key={i} className="w-14 h-14 md:w-16 md:h-16 rounded-xl border-2 border-dashed border-white/10 flex items-center justify-center text-white/20">
                                    <GitCompare size={20} />
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center gap-4">
                            <button 
                                onClick={clearCompareItems}
                                className="text-xs font-bold text-blue-200 hover:text-white flex items-center gap-1.5 transition-colors"
                            >
                                <Trash2 size={14} />
                                Clear
                            </button>
                            <Button asChild className="rounded-xl font-bold bg-white text-[var(--color-secondary)] hover:bg-amber-400 hover:text-[var(--color-secondary)] px-8">
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
