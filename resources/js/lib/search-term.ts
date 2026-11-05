// Mirrors App\Support\SearchTerm, which is what actually protects the server.
// These only spare ordinary visitors from sending requests that can't match.
export const SEARCH_TERM_MAX_LENGTH = 64;

export function normalizeSearchTerm(value: string): string {
    return value
        .replace(/[\s\p{Z}\p{Cc}]+/gu, ' ')
        .trim()
        .slice(0, SEARCH_TERM_MAX_LENGTH)
        .trim();
}

/** Needs two letters or numbers. Marks count, because Bangla vowel signs are marks. */
export function isSearchableTerm(term: string): boolean {
    return (term.match(/[\p{L}\p{N}\p{M}]/gu) ?? []).length >= 2;
}
