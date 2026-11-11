/**
 * The standard limits for photos attached to a review. They mirror the server
 * (App\Support\ReviewImageSettings), which always has the final say; checking
 * here just tells the customer straight away instead of after the upload.
 */
export const REVIEW_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
export const REVIEW_IMAGE_MIN_SIDE = 200;
export const REVIEW_IMAGE_STORED_MAX_SIDE = 1600;
export const REVIEW_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const REVIEW_IMAGE_ACCEPT = REVIEW_IMAGE_TYPES.join(',');

/** The one line that states the rules wherever photos can be added. */
export function reviewImageRulesText(max: number): string {
    return `Up to ${max} ${max === 1 ? 'photo' : 'photos'} · JPG, PNG or WebP · 5 MB each · at least ${REVIEW_IMAGE_MIN_SIDE} × ${REVIEW_IMAGE_MIN_SIDE} px`;
}

/** Why a chosen file can't be used, or null when it is fine. */
export function reviewImageProblem(file: File): string | null {
    if (!REVIEW_IMAGE_TYPES.includes(file.type)) {
        return `"${file.name}" is not a JPG, PNG or WebP image.`;
    }

    if (file.size > REVIEW_IMAGE_MAX_BYTES) {
        return `"${file.name}" is larger than 5 MB.`;
    }

    return null;
}
