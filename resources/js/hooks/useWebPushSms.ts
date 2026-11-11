import { useCallback, useEffect, useState } from 'react';
import { getXsrfToken } from '@/lib/csrf';
import pushSubscriptions from '@/routes/admin/push-subscriptions';

export type SmsToast = {
    key: number;
    id: number;
    sender: string;
    content: string;
    parsed_transaction_id: string | null;
    parsed_amount: number | null;
};

type PushMessage = {
    source: 'admin-push-sw';
    payload: {
        data?: {
            type?: string;
            id?: number;
            sender?: string;
            content?: string;
            parsed_transaction_id?: string | null;
            parsed_amount?: number | null;
        };
    };
};

function isPushMessage(value: unknown): value is PushMessage {
    return (
        typeof value === 'object' &&
        value !== null &&
        (value as { source?: unknown }).source === 'admin-push-sw'
    );
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);

    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
}

/** Rejects with `message` if `promise` has not settled in `ms`, so no step can hang silently. */
function withTimeout<T>(
    promise: Promise<T>,
    ms: number,
    message: string,
): Promise<T> {
    return new Promise<T>((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error(message)), ms);

        promise.then(
            (value) => {
                clearTimeout(timer);
                resolve(value);
            },
            (error) => {
                clearTimeout(timer);
                reject(error);
            },
        );
    });
}

export type EnableResult = { ok: true } | { ok: false; message: string };

export type PushPermissionState =
    | 'unsupported'
    | 'default'
    | 'denied'
    | 'granted';

/**
 * PERF-006 (web push follow-up): replaces useSmsPolling's 15-second fetch
 * loop. A new SMS is now pushed the instant it arrives (see
 * App\Notifications\NewSmsReceived) instead of discovered on the next tick;
 * this hook only registers the service worker, manages the subscription,
 * and turns an incoming push into the same toast list the old poll did —
 * it makes no requests of its own on an interval.
 */
export function useWebPushSms(vapidPublicKey: string | null) {
    const [toasts, setToasts] = useState<SmsToast[]>([]);
    const [permission, setPermission] = useState<PushPermissionState>(
        'Notification' in window &&
            'serviceWorker' in navigator &&
            'PushManager' in window
            ? (Notification.permission as PushPermissionState)
            : 'unsupported',
    );
    const [isSubscribed, setIsSubscribed] = useState(false);

    const dismiss = useCallback(
        (key: number) => setToasts((prev) => prev.filter((t) => t.key !== key)),
        [],
    );

    useEffect(() => {
        if (permission === 'unsupported' || !vapidPublicKey) {
            return;
        }

        let mounted = true;

        navigator.serviceWorker
            .register('/admin-push-sw.js', { scope: '/admin/' })
            .then(async (registration) => {
                const existing =
                    await registration.pushManager.getSubscription();

                if (mounted) {
                    setIsSubscribed(existing !== null);
                }
            })
            .catch(() => {
                // No service worker, no push — the "Enable notifications"
                // button simply won't work; nothing else in the admin
                // relies on this succeeding.
            });

        const onMessage = (event: MessageEvent) => {
            if (!isPushMessage(event.data)) {
                return;
            }

            const { data } = event.data.payload;

            if (
                !data ||
                data.type !== 'new-sms' ||
                typeof data.id !== 'number'
            ) {
                return;
            }

            const smsId = data.id;

            setToasts((prev) => [
                ...prev,
                {
                    key: Date.now() + smsId,
                    id: smsId,
                    sender: data.sender ?? '',
                    content: data.content ?? '',
                    parsed_transaction_id: data.parsed_transaction_id ?? null,
                    parsed_amount: data.parsed_amount ?? null,
                },
            ]);
        };

        navigator.serviceWorker.addEventListener('message', onMessage);

        return () => {
            mounted = false;
            navigator.serviceWorker.removeEventListener('message', onMessage);
        };
    }, [permission, vapidPublicKey]);

    /**
     * Must be called from a user gesture (a button click) — browsers refuse
     * the permission prompt otherwise. Resolves with the reason when it did
     * not work, so the caller can show it instead of failing silently.
     */
    const enableNotifications = useCallback(
        async (onStep?: (text: string) => void): Promise<EnableResult> => {
            if (permission === 'unsupported' || !vapidPublicKey) {
                return {
                    ok: false,
                    message:
                        'This browser does not support notifications, or the notification keys are not set up.',
                };
            }

            const step = (text: string) => onStep?.(text);

            try {
                step('1/5 Asking the browser for permission…');
                const result = await withTimeout(
                    Notification.requestPermission(),
                    60_000,
                    'The browser did not answer the permission question. Look for a bell or lock icon at the right end of the address bar, allow notifications there, then press the button again.',
                );
                setPermission(result as PushPermissionState);

                if (result !== 'granted') {
                    return {
                        ok: false,
                        message:
                            'Notifications were not allowed. Click the lock or bell icon next to the address bar, set Notifications to Allow, then press the button again.',
                    };
                }

                step('2/5 Starting the notification worker…');
                await withTimeout(
                    navigator.serviceWorker.register('/admin-push-sw.js', {
                        scope: '/admin/',
                    }),
                    15_000,
                    'The notification worker file (/admin-push-sw.js) could not be loaded.',
                );
                // subscribe() needs an active service worker, which a first-time
                // registration does not have yet.
                const registration = await withTimeout(
                    navigator.serviceWorker.ready,
                    15_000,
                    'The notification worker did not become active. Reload the page and try again.',
                );

                // A sign-up made with an older key pair (e.g. before "Create new
                // keys") makes subscribe() throw, so drop it first.
                const stale = await registration.pushManager.getSubscription();

                if (stale) {
                    await stale.unsubscribe();
                }

                step("3/5 Signing up with the browser's push service…");
                const subscription = await withTimeout(
                    registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        // TS's DOM lib types Uint8Array generically over ArrayBufferLike,
                        // which it won't narrow to the plain BufferSource this API wants —
                        // the value itself is a perfectly normal Uint8Array at runtime.
                        applicationServerKey: urlBase64ToUint8Array(
                            vapidPublicKey,
                        ) as BufferSource,
                    }),
                    25_000,
                    "The browser's push service did not answer. Chrome needs to reach Google's push service for this: check your internet or firewall, and that Chrome's \"Use Google services for push messaging\" is not turned off.",
                );

                step('4/5 Saving this browser on the server…');
                const response = await withTimeout(
                    fetch(pushSubscriptions.store.url(), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-XSRF-TOKEN': getXsrfToken(),
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(subscription.toJSON()),
                    }),
                    15_000,
                    'The server did not answer when saving this browser.',
                );

                if (!response.ok) {
                    await subscription.unsubscribe();

                    return {
                        ok: false,
                        message: `The server did not accept this browser (HTTP ${response.status}). Reload the page and try again.`,
                    };
                }

                step('5/5 Done.');
                setIsSubscribed(true);

                return { ok: true };
            } catch (error) {
                return {
                    ok: false,
                    message: `Could not turn notifications on: ${error instanceof Error ? error.message : String(error)}`,
                };
            }
        },
        [permission, vapidPublicKey],
    );

    const disableNotifications = useCallback(async () => {
        if (permission === 'unsupported') {
            return;
        }

        const registration =
            await navigator.serviceWorker.getRegistration('/admin/');
        const subscription = await registration?.pushManager.getSubscription();

        if (!subscription) {
            setIsSubscribed(false);

            return;
        }

        const endpoint = subscription.endpoint;
        await subscription.unsubscribe();

        await fetch(pushSubscriptions.destroy.url(), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ endpoint }),
        });

        setIsSubscribed(false);
    }, [permission]);

    return {
        toasts,
        dismiss,
        permission,
        isSubscribed,
        enableNotifications,
        disableNotifications,
    };
}
