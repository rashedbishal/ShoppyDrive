import React from 'react';

interface CheckoutLayoutProps {
    children: React.ReactNode;
}

export default function CheckoutLayout({ children }: CheckoutLayoutProps): React.JSX.Element {
    return (
        <div className="min-h-screen bg-[var(--color-surface-alt)] flex flex-col">
            <main className="flex-1 py-8 md:py-12">
                <div className="mx-auto max-w-6xl px-4">
                    {children}
                </div>
            </main>

            {/* Simple Footer */}
            <footer className="bg-white border-t border-[var(--color-border)] py-8 mt-auto">
                <div className="mx-auto max-w-6xl px-4 text-center">
                    <p className="text-sm text-[var(--color-text-muted)] mb-4">
                        &copy; {new Date().getFullYear()} ShoppyDrive. All rights reserved.
                    </p>
                    <div className="flex items-center justify-center gap-6 opacity-50 grayscale">
                        {/* Placeholder for payment logos */}
                        <span className="text-[10px] font-bold uppercase tracking-tighter">SSLCommerz</span>
                        <span className="text-[10px] font-bold uppercase tracking-tighter">bKash</span>
                        <span className="text-[10px] font-bold uppercase tracking-tighter">Nagad</span>
                        <span className="text-[10px] font-bold uppercase tracking-tighter">Visa</span>
                        <span className="text-[10px] font-bold uppercase tracking-tighter">Mastercard</span>
                    </div>
                </div>
            </footer>
        </div>
    );
}
