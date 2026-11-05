import { useEffect, useRef, useState } from 'react';

export type SmsToast = {
    key: number;
    id: number;
    sender: string;
    content: string;
    parsed_transaction_id: string | null;
    parsed_amount: number | null;
};

const STORAGE_KEY = 'admin_sms_last_id';
const POLL_INTERVAL = 5000;

export function useSmsPolling() {
    const [toasts, setToasts] = useState<SmsToast[]>([]);
    // FSA-07: count of SMS unmatched past the admin-reconciliation threshold
    // (ReceivedSmsController::UNMATCHED_STALE_MINUTES), piggybacking on this
    // same poll — not a new endpoint or table.
    const [unmatchedCount, setUnmatchedCount] = useState(0);
    // OPS-005: true once the forwarder phone hasn't sent anything in a long
    // time (ReceivedSmsController::FORWARDER_SILENT_MINUTES) — the only
    // liveness signal for this single point of failure.
    const [forwarderSeemsDown, setForwarderSeemsDown] = useState(false);
    // true after the first successful response — prevents toasting stale SMS on startup
    const initializedRef = useRef(false);

    const dismiss = (key: number) =>
        setToasts((prev) => prev.filter((t) => t.key !== key));

    useEffect(() => {
        let mounted = true;

        const poll = async () => {
            const lastId = parseInt(
                localStorage.getItem(STORAGE_KEY) ?? '0',
                10,
            );

            let res: Response;

            try {
                res = await fetch(`/admin/sms/poll?after=${lastId}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
            } catch {
                return; // network error — try next interval
            }

            // Guard: non-JSON response (e.g. redirect to login page)
            if (
                !res.ok ||
                !res.headers.get('content-type')?.includes('application/json')
            ) {
                return;
            }

            let data: {
                sms: Omit<SmsToast, 'key'>[];
                unmatched_count?: number;
                forwarder_seems_down?: boolean;
            };

            try {
                data = await res.json();
            } catch {
                return;
            }

            if (!mounted) {
                return;
            }

            if (typeof data.unmatched_count === 'number') {
                setUnmatchedCount(data.unmatched_count);
            }

            if (typeof data.forwarder_seems_down === 'boolean') {
                setForwarderSeemsDown(data.forwarder_seems_down);
            }

            const isFirstPoll = !initializedRef.current;
            // Mark as initialized on the FIRST successful response,
            // regardless of whether any SMS were returned.
            initializedRef.current = true;

            const { sms } = data;

            if (!sms.length) {
                return;
            }

            // Advance the cursor
            const maxId = Math.max(...sms.map((s) => s.id));
            localStorage.setItem(STORAGE_KEY, String(maxId));

            // Never toast on the first poll — those are existing SMS, not new arrivals
            if (isFirstPoll) {
                return;
            }

            setToasts((prev) => [
                ...prev,
                ...sms.map((s) => ({ ...s, key: Date.now() + s.id })),
            ]);
        };

        poll();
        const id = setInterval(poll, POLL_INTERVAL);

        return () => {
            mounted = false;
            clearInterval(id);
        };
    }, []);

    return { toasts, dismiss, unmatchedCount, forwarderSeemsDown };
}
