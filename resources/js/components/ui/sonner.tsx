import { Link, router } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    Info,
    LoaderCircle,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { Modal } from '@/components/ui/modal';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useUiStore } from '@/stores/uiStore';

function isAdminPath(path: string): boolean {
    return path === '/admin' || path.startsWith('/admin/');
}

/**
 * Whether the current page is the admin panel. The Toaster sits outside the
 * page tree (app.tsx `withApp`), so it follows Inertia navigations rather than
 * reading the page itself.
 */
function useIsAdminPage(): boolean {
    const [isAdmin, setIsAdmin] = useState(() =>
        typeof window === 'undefined'
            ? false
            : isAdminPath(window.location.pathname),
    );

    useEffect(
        () =>
            router.on('navigate', (event) => {
                const url = (event as CustomEvent).detail?.page?.url as
                    | string
                    | undefined;

                if (url !== undefined) {
                    setIsAdmin(
                        isAdminPath(
                            new URL(url, window.location.origin).pathname,
                        ),
                    );
                }
            }),
        [],
    );

    return isAdmin;
}

/**
 * The storefront's own toast design (resources/css/app.css, `.sd-toast`):
 * the same sonner toasts every caller already raises, unstyled here and given
 * a card look with a blurred spring entrance and exit. The admin panel keeps
 * sonner's default look.
 */
const storefrontToastIcons = {
    success: <CircleCheck strokeWidth={2.25} />,
    error: <CircleX strokeWidth={2.25} />,
    warning: <TriangleAlert strokeWidth={2.25} />,
    info: <Info strokeWidth={2.25} />,
    loading: <LoaderCircle strokeWidth={2.25} className="animate-spin" />,
};

const storefrontToastOptions: ToasterProps['toastOptions'] = {
    unstyled: true,
    classNames: {
        toast: 'sd-toast',
        icon: 'sd-toast-icon',
        content: 'sd-toast-content',
        title: 'sd-toast-title',
        description: 'sd-toast-description',
        closeButton: 'sd-toast-close',
        actionButton: 'sd-toast-action',
        cancelButton: 'sd-toast-cancel',
    },
};

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();
    const isAdminPage = useIsAdminPage();
    const blockedDeleteProducts = useUiStore(
        (state) => state.blockedDeleteProducts,
    );
    const hideBlockedDeleteProducts = useUiStore(
        (state) => state.hideBlockedDeleteProducts,
    );

    useFlashToast();

    return (
        <>
            {isAdminPage ? (
                <Sonner
                    theme={appearance}
                    className="toaster group"
                    position="bottom-right"
                    {...props}
                />
            ) : (
                <Sonner
                    theme="light"
                    className="sd-toaster"
                    position="top-center"
                    offset={20}
                    mobileOffset={12}
                    gap={10}
                    closeButton
                    icons={storefrontToastIcons}
                    toastOptions={storefrontToastOptions}
                    {...props}
                />
            )}
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
