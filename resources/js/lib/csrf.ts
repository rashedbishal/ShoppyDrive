/**
 * Laravel issues CSRF protection via the `XSRF-TOKEN` cookie (readable,
 * not HttpOnly) rather than a `<meta name="csrf-token">` tag — this app's
 * `resources/views/app.blade.php` never renders one. Any `fetch()` call
 * outside Inertia's own router must read this cookie and send it back as
 * `X-XSRF-TOKEN`, mirroring what `app.tsx`'s cart-sync call already does.
 */
export function getXsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
