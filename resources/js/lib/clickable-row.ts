import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';

const INTERACTIVE =
    'a, button, input, select, textarea, label, [role="checkbox"], [data-no-row-click]';

/**
 * Makes a whole list row open `href` when clicked, without hijacking the
 * controls inside it (checkboxes, edit/delete buttons, links) or a text
 * selection. The row's own Edit link stays for keyboard and screen readers.
 */
export function clickableRow(href: string | null): {
    onClick?: (event: MouseEvent<HTMLElement>) => void;
    'data-clickable-row'?: true;
} {
    if (href === null) {
        return {};
    }

    return {
        'data-clickable-row': true,
        onClick: (event) => {
            const target = event.target as HTMLElement;

            if (
                target.closest(INTERACTIVE) ||
                window.getSelection()?.toString()
            ) {
                return;
            }

            router.visit(href);
        },
    };
}
