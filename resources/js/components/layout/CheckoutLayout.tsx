import React from 'react';

interface CheckoutLayoutProps {
    children: React.ReactNode;
}

export default function CheckoutLayout({
    children,
}: CheckoutLayoutProps): React.JSX.Element {
    return (
        <div className="flex min-h-screen flex-col bg-[var(--color-surface-alt)]">
            <div className="flex-1 py-8 md:py-12">
                <div className="mx-auto max-w-6xl px-4">{children}</div>
            </div>

            {/* Simple Footer */}
            <footer className="mt-auto border-t border-[var(--color-border)] bg-white py-8">
                <div className="mx-auto max-w-6xl px-4 text-center">
                    <p className="mb-4 text-sm text-[var(--color-text-secondary)]">
                        &copy; {new Date().getFullYear()} ShoppyDrive. All
                        rights reserved.
                    </p>
                    {/* BIZ-031: only these two payment methods are actually accepted — SSLCommerz/Visa/Mastercard were a placeholder left in. */}
                    <div className="flex items-center justify-center gap-6 opacity-50 grayscale">
                        <span className="text-[10px] font-bold tracking-tighter uppercase">
                            bKash
                        </span>
                        <span className="text-[10px] font-bold tracking-tighter uppercase">
                            Nagad
                        </span>
                        <span className="text-[10px] font-bold tracking-tighter uppercase">
                            Cash on Delivery
                        </span>
                    </div>
                </div>
            </footer>
        </div>
    );
}
