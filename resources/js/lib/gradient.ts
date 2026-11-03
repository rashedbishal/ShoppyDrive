export type GradientStop = {
    color: string;
    opacity: number;
};

const GRADIENT_PATTERN =
    /^linear-gradient\(to right, ?rgba\((\d{1,3}), ?(\d{1,3}), ?(\d{1,3}), ?([\d.]+)\), ?rgba\((\d{1,3}), ?(\d{1,3}), ?(\d{1,3}), ?([\d.]+)\)\)$/;

function rgbToHex(r: number, g: number, b: number): string {
    return `#${[r, g, b].map((n) => n.toString(16).padStart(2, '0')).join('')}`;
}

function hexToRgb(hex: string): [number, number, number] {
    const normalized = hex.replace('#', '');

    return [
        parseInt(normalized.slice(0, 2), 16) || 0,
        parseInt(normalized.slice(2, 4), 16) || 0,
        parseInt(normalized.slice(4, 6), 16) || 0,
    ];
}

/** Banner overlay accents are stored as a literal `linear-gradient(...)` CSS value and applied via
 * inline style, never as a Tailwind class string — runtime values from the database can't be picked
 * up by Tailwind's static source scan, so dynamic `from-*`/`via-*`/`to-*` classes silently render
 * with no generated CSS. This matches the "start color" / "end color" the admin's gradient picker exposes. */
export function buildGradient(from: GradientStop, to: GradientStop): string {
    const [fr, fg, fb] = hexToRgb(from.color);
    const [tr, tg, tb] = hexToRgb(to.color);

    return `linear-gradient(to right, rgba(${fr}, ${fg}, ${fb}, ${(from.opacity / 100).toFixed(2)}), rgba(${tr}, ${tg}, ${tb}, ${(to.opacity / 100).toFixed(2)}))`;
}

export function parseGradient(
    value: string,
): { from: GradientStop; to: GradientStop } | null {
    const match = value.match(GRADIENT_PATTERN);

    if (!match) {
        return null;
    }

    const [, r1, g1, b1, a1, r2, g2, b2, a2] = match;

    return {
        from: {
            color: rgbToHex(Number(r1), Number(g1), Number(b1)),
            opacity: Math.round(Number(a1) * 100),
        },
        to: {
            color: rgbToHex(Number(r2), Number(g2), Number(b2)),
            opacity: Math.round(Number(a2) * 100),
        },
    };
}

/** True only for the CSS `linear-gradient(...)` format the picker writes. Older banners saved before
 * this picker existed stored raw Tailwind utility classes (e.g. "from-secondary/90 via-secondary/70
 * to-secondary/20") — those never worked reliably as dynamic classes and aren't valid CSS, so callers
 * should fall back to `DEFAULT_BANNER_GRADIENT` rather than rendering them. */
export function isCssGradient(
    value: string | null | undefined,
): value is string {
    return (
        typeof value === 'string' && value.trim().startsWith('linear-gradient(')
    );
}

export const DEFAULT_BANNER_GRADIENT = buildGradient(
    { color: '#1a1a2e', opacity: 90 },
    { color: '#1a1a2e', opacity: 20 },
);
