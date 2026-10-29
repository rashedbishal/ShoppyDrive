import { usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { X } from 'lucide-react';
import React from 'react';

import { SearchBar } from '@/components/shared/SearchBar';

interface SearchOverlayProps {
    isOpen: boolean;
    onClose: () => void;
}

export function SearchOverlay({
    isOpen,
    onClose,
}: SearchOverlayProps): React.JSX.Element {
    const popularKeywords = (usePage().props as any).popular_keywords as
        | string[]
        | undefined;
    const popular =
        popularKeywords && popularKeywords.length > 0
            ? popularKeywords
            : [
                  'Smartphone',
                  'Headphones',
                  'Laptops',
                  'Watches',
                  'Speakers',
                  'Accessories',
              ];

    // Prevent scrolling when open
    React.useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }

        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [isOpen]);

    return (
        <AnimatePresence>
            {isOpen && (
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    className="fixed inset-0 z-[200] flex flex-col bg-white"
                >
                    <div className="flex items-center justify-between border-b border-[var(--color-border)] p-4">
                        <span className="text-lg font-bold text-[var(--color-text-primary)]">
                            Search Products
                        </span>
                        <button
                            onClick={onClose}
                            className="rounded-full p-2 transition-colors hover:bg-[var(--color-surface-alt)]"
                        >
                            <X size={24} />
                        </button>
                    </div>

                    <div className="flex-1 overflow-y-auto p-4">
                        <div className="mb-8">
                            <SearchBar isMobile className="max-w-none" />
                        </div>

                        {/* Popular Searches / Suggestions */}
                        <div className="space-y-6">
                            <div>
                                <h3 className="mb-4 text-xs font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                    Popular Searches
                                </h3>
                                <div className="flex flex-wrap gap-2">
                                    {popular.map((term) => (
                                        <button
                                            key={term}
                                            className="rounded-full bg-[var(--color-surface-alt)] px-4 py-2 text-sm font-medium text-[var(--color-text-primary)] transition-all hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
                                        >
                                            {term}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <h3 className="mb-4 text-xs font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                    Recent Views
                                </h3>
                                <div className="grid grid-cols-2 gap-4">
                                    {/* This would ideally come from the same localStorage used in ProductDetail */}
                                    <p className="col-span-2 text-sm text-[var(--color-text-muted)] italic">
                                        No recent views yet.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
