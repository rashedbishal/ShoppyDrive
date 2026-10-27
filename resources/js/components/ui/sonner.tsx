import { Link } from '@inertiajs/react';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { Modal } from '@/components/ui/modal';
import { useUiStore } from '@/stores/uiStore';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();
    const blockedDeleteProducts = useUiStore(
        (state) => state.blockedDeleteProducts,
    );
    const hideBlockedDeleteProducts = useUiStore(
        (state) => state.hideBlockedDeleteProducts,
    );

    useFlashToast();

    return (
        <>
            <Sonner
                theme={appearance}
                className="toaster group"
                position="bottom-right"
                style={
                    {
                        '--normal-bg': 'var(--popover)',
                        '--normal-text': 'var(--popover-foreground)',
                        '--normal-border': 'var(--border)',
                    } as React.CSSProperties
                }
                {...props}
            />
            <Modal
                isOpen={blockedDeleteProducts !== null}
                onClose={hideBlockedDeleteProducts}
                title="Products using this value"
                size="sm"
            >
                <ul className="space-y-1">
                    {(blockedDeleteProducts ?? []).map((product) => (
                        <li key={product.id}>
                            <Link
                                href={route('admin.products.edit', product.id)}
                                onClick={hideBlockedDeleteProducts}
                                className="block rounded-lg px-3 py-2 text-sm font-medium text-text-primary hover:bg-surface-alt"
                            >
                                {product.name}
                            </Link>
                        </li>
                    ))}
                </ul>
            </Modal>
        </>
    );
}

export { Toaster };
