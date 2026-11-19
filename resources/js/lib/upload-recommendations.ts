/**
 * Advisory wording for admin upload fields. These are recommendations that
 * keep pages fast, not limits: the server-side rules for each field are the
 * only thing enforced.
 */

/** Recommended image file size, in KB. */
export const RECOMMENDED_IMAGE_MAX_KB = 100;

/** Recommended video formats and size (DISC-71: product videos are refused above this). */
export const RECOMMENDED_VIDEO =
    'MP4 (H.264) format, 720p or smaller, up to 10 MB';

/** DISC-71: hero slide videos are refused above this. Mirrors HeroSlideController::VIDEO_MAX_MEGABYTES. */
export const HERO_VIDEO_MAX_MB = 2;

/** DISC-71: how an admin with no video tools makes a web-sized video. Mirrors WebVideo::HOW_TO_FIX. */
export const VIDEO_HOW_TO =
    'To make one: open the video in the free HandBrake app, choose the "Fast 720p30" preset, tick "Web Optimized", remove the audio track, and save.';

/** e.g. imageRecommendation('1280 × 720 px (16:9)') */
export function imageRecommendation(dimensions: string): string {
    return `Recommended: WebP format, ${dimensions}, under ${RECOMMENDED_IMAGE_MAX_KB} KB.`;
}
