/**
 * A unique id for client-side list keys.
 *
 * `crypto.randomUUID()` only exists in secure contexts (HTTPS, or HTTP on
 * localhost). The dev server is browsed over a LAN IP, and calling it there
 * throws, which silently breaks whatever click handler used it. Falls back to
 * `crypto.getRandomValues()` and finally `Math.random()`. Not for anything
 * security-sensitive.
 */
export function uniqueId(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.getRandomValues === 'function'
    ) {
        return Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) =>
            byte.toString(16).padStart(2, '0'),
        ).join('');
    }

    return `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
}
