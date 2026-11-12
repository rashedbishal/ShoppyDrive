import type { VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';

/**
 * Where a rejected form submission leaves the visitor.
 *
 * Inertia's default is to scroll to the top after every visit, including a
 * form POST the server sends straight back with validation errors (or an
 * out-of-stock / coupon / rate-limit message) — so a customer who pressed a
 * button near the bottom of a long form landed at the top, away from the
 * message explaining what went wrong. Two global pieces fix that for every
 * form without touching each one:
 *
 * 1. `preserveScrollOnErrors` (createInertiaApp `defaults.visitOptions`):
 *    a submission keeps its scroll position when the response has errors.
 *    A successful one that lands on a new page still starts at the top, and
 *    a form that sets `preserveScroll` itself is left exactly as it is.
 * 2. `registerFormErrorScroll`: after a rejected submission, smoothly
 *    scrolls the first visible error on the page to the middle of the
 *    screen — a field marked `aria-invalid`, the field an error key names
 *    (by `name` or `id`), or an element marked `data-form-error` (field
 *    messages, error banners). With nothing on the page to show (a toast
 *    only), the page simply stays where it was.
 */

/** Marks an on-page error message or banner as a scroll target. */
export const FORM_ERROR_ATTRIBUTE = 'data-form-error';

export function preserveScrollOnErrors(
    _href: string,
    options: VisitOptions,
): Partial<VisitOptions> {
    const method = (options.method ?? 'get').toLowerCase();

    if (method === 'get' || options.preserveScroll !== undefined) {
        return {};
    }

    return { preserveScroll: 'errors' };
}

/** `shipping.name` is usually rendered as `shipping[name]`. */
function bracketName(key: string): string {
    const [first, ...rest] = key.split('.');

    return first + rest.map((part) => `[${part}]`).join('');
}

function fieldSelectors(key: string): string[] {
    const escapedKey = CSS.escape(key);
    const bracketed = bracketName(key);

    return [
        `[name="${CSS.escape(bracketed)}"]`,
        `[name="${CSS.escape(bracketed)}[]"]`,
        `#${escapedKey}`,
    ];
}

function isVisible(element: Element): boolean {
    return (
        element.getClientRects().length > 0 &&
        // Toasts are already on screen; they're never a scroll target.
        element.closest('[data-sonner-toaster]') === null
    );
}

function firstErrorElement(errorKeys: string[]): HTMLElement | null {
    const candidates = new Set<Element>(
        document.querySelectorAll(
            `[${FORM_ERROR_ATTRIBUTE}], [aria-invalid="true"]`,
        ),
    );

    for (const key of errorKeys) {
        for (const selector of fieldSelectors(key)) {
            try {
                document
                    .querySelectorAll(selector)
                    .forEach((element) => candidates.add(element));
            } catch {
                // An error key that can't form a valid selector: skip it.
            }
        }
    }

    let first: HTMLElement | null = null;
    let firstTop = Infinity;

    candidates.forEach((element) => {
        if (!(element instanceof HTMLElement) || !isVisible(element)) {
            return;
        }

        const top = element.getBoundingClientRect().top;

        if (top < firstTop) {
            first = element;
            firstTop = top;
        }
    });

    return first;
}

function scrollToFirstError(errorKeys: string[]): void {
    const target = firstErrorElement(errorKeys);

    if (target === null) {
        return;
    }

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    target.scrollIntoView({
        behavior: reduceMotion ? 'auto' : 'smooth',
        block: 'center',
    });

    // Put the cursor in the field on desktop; on a phone that would pop the
    // keyboard up over the message the visitor needs to read.
    const isTextField =
        target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement ||
        target instanceof HTMLSelectElement;

    if (isTextField && window.matchMedia('(pointer: fine)').matches) {
        target.focus({ preventScroll: true });
    }
}

/**
 * For validation done in the browser, which never reaches the server and so
 * never fires the visit events above: call right after the errors are set and
 * the first error on screen (inside a modal too) is scrolled into view.
 */
export function scrollToFirstFormError(): void {
    requestAnimationFrame(() =>
        requestAnimationFrame(() => scrollToFirstError([])),
    );
}

/** Call once at start-up; returns a function that removes the listeners. */
export function registerFormErrorScroll(): () => void {
    let isSubmission = false;
    let errorKeys: string[] | null = null;

    const removers = [
        router.on('start', (event) => {
            const { visit } = event.detail;
            // Background and partial reloads manage their own feedback.
            isSubmission =
                visit.method !== 'get' &&
                !visit.async &&
                visit.only.length === 0;
            errorKeys = null;
        }),
        router.on('error', (event) => {
            if (isSubmission) {
                errorKeys = Object.keys(event.detail.errors ?? {});
            }
        }),
        router.on('success', (event) => {
            const flash = event.detail.page.props.flash as
                | { error?: string | null }
                | undefined;

            // A refusal sent back as a page-level error banner only.
            if (isSubmission && flash?.error) {
                errorKeys = [];
            }
        }),
        router.on('finish', () => {
            const keys = errorKeys;
            errorKeys = null;

            if (keys === null) {
                return;
            }

            // Two frames: let React paint the error messages first.
            requestAnimationFrame(() =>
                requestAnimationFrame(() => scrollToFirstError(keys)),
            );
        }),
    ];

    return () => removers.forEach((remove) => remove());
}
