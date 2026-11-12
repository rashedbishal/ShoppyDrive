import type { SVGAttributes } from 'react';

/**
 * The generic mark, shown only where no logo has been uploaded (Admin ›
 * Settings) — a plain shopping bag, filled with the current text colour.
 * It replaced the Laravel starter kit's logo, which appeared in the page
 * loader and the site logo whenever the uploaded one wasn't available.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M8 7V6a4 4 0 1 1 8 0v1h2.1a1 1 0 0 1 1 .92l1 12.5A2.4 2.4 0 0 1 17.7 23H6.3a2.4 2.4 0 0 1-2.4-2.58l1-12.5A1 1 0 0 1 5.9 7H8Zm2 0h4V6a2 2 0 1 0-4 0v1Zm-1.5 3.25a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Zm7 0a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Z"
            />
        </svg>
    );
}
