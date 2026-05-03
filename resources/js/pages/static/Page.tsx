import { Head } from '@inertiajs/react';
import React from 'react';
import { motion } from 'framer-motion';

import { PageMeta } from '@/components/seo/PageMeta';

interface PageProps {
    page: {
        title: string;
        content: string;
        meta_title?: string;
        meta_description?: string;
        updated_at: string;
    };
}

export default function Page({ page }: PageProps): React.JSX.Element {
    return (
        <div className="bg-white min-h-screen pb-20">
            <PageMeta 
                title={page.meta_title || page.title} 
                description={page.meta_description}
            />

            {/* Page Header */}
            <header className="bg-[var(--color-surface-alt)] py-16 md:py-24 border-b border-[var(--color-border)]">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        className="max-w-4xl"
                    >
                        <h1 className="text-4xl md:text-5xl font-serif font-bold text-[var(--color-text-primary)] leading-tight mb-4">
                            {page.title}
                        </h1>
                        <p className="text-sm text-[var(--color-text-muted)] font-medium uppercase tracking-widest">
                            Last Updated: {new Date(page.updated_at).toLocaleDateString('en-BD', { day: 'numeric', month: 'long', year: 'numeric' })}
                        </p>
                    </motion.div>
                </div>
            </header>

            {/* Page Content */}
            <main className="container mx-auto px-4 py-16 md:py-24">
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.2 }}
                    className="max-w-4xl prose prose-slate md:prose-lg mx-auto prose-headings:font-serif prose-headings:text-[var(--color-text-primary)] prose-p:text-[var(--color-text-secondary)] prose-strong:text-[var(--color-text-primary)] prose-a:text-[var(--color-primary)] prose-a:font-bold prose-img:rounded-[32px] prose-img:shadow-xl"
                >
                    <div dangerouslySetInnerHTML={{ __html: page.content }} />
                </motion.div>
            </main>
        </div>
    );
}
