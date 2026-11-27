import type { ExternalToast, toast as sonnerToast } from 'sonner';

/**
 * F1-04: the storefront's toast calls, without sonner in every page's first
 * download. The <Toaster> is loaded lazily (app.tsx) and calls
 * markToasterMounted() once it is listening; every toast raised before that
 * (e.g. a flash message on the first page) waits in this promise queue and
 * is shown then, in order, so none is lost. Callers here never used the id
 * sonner returns, so these calls return nothing.
 */
type Message = Parameters<typeof sonnerToast>[0];
type Kind = 'success' | 'error' | 'info' | 'warning';

let markReady: () => void = () => {};
const toasterReady = new Promise<void>((resolve) => {
    markReady = resolve;
});
let sonner: Promise<typeof sonnerToast> | null = null;

function loadToast(): Promise<typeof sonnerToast> {
    sonner ??= toasterReady
        .then(() => import('sonner'))
        .then((module) => module.toast);

    return sonner;
}

function show(
    kind: Kind | null,
    message: Message,
    options?: ExternalToast,
): void {
    void loadToast().then((toast) => {
        if (kind === null) {
            toast(message, options);
        } else {
            toast[kind](message, options);
        }
    });
}

/** Called by the lazily loaded Toaster once it is subscribed. */
export function markToasterMounted(): void {
    markReady();
}

export const toast = Object.assign(
    (message: Message, options?: ExternalToast): void =>
        show(null, message, options),
    {
        success: (message: Message, options?: ExternalToast): void =>
            show('success', message, options),
        error: (message: Message, options?: ExternalToast): void =>
            show('error', message, options),
        info: (message: Message, options?: ExternalToast): void =>
            show('info', message, options),
        warning: (message: Message, options?: ExternalToast): void =>
            show('warning', message, options),
    },
);
