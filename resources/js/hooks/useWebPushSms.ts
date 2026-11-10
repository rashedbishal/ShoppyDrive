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

    /** Must be called from a user gesture (a button click) — browsers refuse the permission prompt otherwise. */
    const enableNotifications = useCallback(async () => {
        if (permission === 'unsupported' || !vapidPublicKey) {
            return;
        }

        const result = await Notification.requestPermission();
        setPermission(result as PushPermissionState);

        if (result !== 'granted') {
            return;
        }

        const registration = await navigator.serviceWorker.register(
            '/admin-push-sw.js',
            {
                scope: '/admin/',
            },
        );
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            // TS's DOM lib types Uint8Array generically over ArrayBufferLike,
            // which it won't narrow to the plain BufferSource this API wants —
            // the value itself is a perfectly normal Uint8Array at runtime.
            applicationServerKey: urlBase64ToUint8Array(
                vapidPublicKey,
            ) as BufferSource,
        });

        await fetch(pushSubscriptions.store.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(subscription.toJSON()),
        });

        setIsSubscribed(true);
    }, [permission, vapidPublicKey]);

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
