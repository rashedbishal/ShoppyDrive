/**
 * FE-012: cleans a Bangladeshi mobile number as it is typed or pasted.
 * Mirrors App\Support\BangladeshiPhone::normalize() on the server, which
 * remains the authority: Bangla digits (০–৯) become 0–9; spaces, dashes,
 * dots and brackets are dropped; a +880 / 00880 / 880 prefix becomes the
 * local leading 0; the result is capped at 11 digits.
 */
const BANGLA_ZERO = 0x09e6;

export function normalizeBdPhoneInput(raw: string): string {
    let value = raw
        .replace(/[০-৯]/g, (digit) => String(digit.charCodeAt(0) - BANGLA_ZERO))
        .replace(/[\s\-.()]/g, '');

    if (value.startsWith('+880')) {
        value = `0${value.slice(4)}`;
    } else if (value.startsWith('00880')) {
        value = `0${value.slice(5)}`;
    } else if (value.startsWith('880') && value.length >= 13) {
        value = `0${value.slice(3)}`;
    }

    return value.replace(/\D/g, '').slice(0, 11);
}
