/**
 * Advisory wording for admin upload fields. These are recommendations that
 * keep pages fast, not limits: the server-side rules for each field are the
 * only thing enforced.
 */

/** Recommended image file size, in KB. */
export const RECOMMENDED_IMAGE_MAX_KB = 100;

/** Recommended video formats and size. */
export const RECOMMENDED_VIDEO = 'MP4 or MOV format, up to 10 MB';

/** e.g. imageRecommendation('1280 × 720 px (16:9)') */
export function imageRecommendation(dimensions: string): string {
    return `Recommended: WebP format, ${dimensions}, under ${RECOMMENDED_IMAGE_MAX_KB} KB.`;
}
