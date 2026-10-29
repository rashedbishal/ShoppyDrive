import React from 'react';

interface CheckoutLayoutProps {
    children: React.ReactNode;
}

export default function CheckoutLayout({
    children,
}: CheckoutLayoutProps): React.JSX.Element {
    return (
        <div className="flex min-h-screen flex-col bg-[var(--color-surface-alt)]">
            <main className="flex-1 py-8 md:py-12">
                <div className="mx-auto max-w-6xl px-4">{children}</div>
            </main>

            {/* Simple Footer */}
            <footer className="mt-auto border-t border-[var(--color-border)] bg-white py-8">
                <div className="mx-auto max-w-6xl px-4 text-center">
                    <p className="mb-4 text-sm text-[var(--color-text-muted)]">
                        &copy; {new Date().getFullYear()} ShoppyDrive. All
                        rights reserved.
                    </p>
                    <div className="flex items-center justify-center gap-6 opacity-50 grayscale">
                        {/* Placeholder for payment logos */}
                        <span className="text-[10px] font-bold tracking-tighter uppercase">
                            SSLCommerz
                        </span>
                        <span className="text-[10px] font-bold tracking-tighter uppercase">
                            bKash
                        </span>
                        <span className="text-[10px] font-bold tracking-tighter uppercase">
                            Nagad
                        </span>
                        <span className="text-[10px] font-bold tracking-tighter uppercase">
                            Visa
                        </span>
                        <span className="text-[10px] font-bold tracking-tighter uppercase">
                            Mastercard
                        </span>
                    </div>
                </div>
            </footer>
        </div>
    );
}
