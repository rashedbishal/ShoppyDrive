import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { NewsletterStatus } from '@/components/shared/NewsletterThankYouModal';

const STATUSES: NewsletterStatus[] = [
    'subscribed',
    'resubscribed',
    'already_subscribed',
];

/**
 * Shared by the storefront's newsletter forms (footer and Home page): posts
 * the email and, on success, opens NewsletterThankYouModal with the outcome
 * NewsletterController::subscribe() flashed (`newsletter.status`).
 */
export function useNewsletterSubscribe() {
    const form = useForm<{ email: string }>({ email: '' });
    const [thankYou, setThankYou] = useState<{
        status: NewsletterStatus;
        email: string;
    } | null>(null);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const email = form.data.email.trim();

        form.post(route('newsletter.subscribe'), {
            preserveScroll: true,
            onSuccess: (page) => {
                const flashed = (
                    page.flash as { newsletter?: { status?: string } } | null
                )?.newsletter?.status;

                setThankYou({
                    status: STATUSES.includes(flashed as NewsletterStatus)
                        ? (flashed as NewsletterStatus)
                        : 'subscribed',
                    email,
                });
                form.reset('email');
            },
        });
    };

    return {
        form,
        submit,
        thankYou,
        closeThankYou: () => setThankYou(null),
    };
}
