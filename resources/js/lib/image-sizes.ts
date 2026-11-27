/**
 * IMG-F5: how wide each kind of storefront image is drawn, for `sizes`, so
 * the browser picks the smallest copy (`srcset`, from the server) that is
 * still sharp. When an image has no copies it has no srcset and its plain
 * `src` is used, exactly as before.
 */
export const IMAGE_SIZES = {
    /** Product cards: 2 per row on phones, up to 4–5 on desktop. */
    card: '(min-width: 1280px) 300px, (min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw',
    /** Product page main photo. Must equal App\Support\PreloadImage::PRODUCT_GALLERY_SIZES (a test checks). */
    productGallery:
        '(min-width: 1280px) 720px, (min-width: 1024px) 600px, 100vw',
    /** Gallery strip, cart and menu thumbnails. */
    thumbnail: '96px',
    /** Edge-to-edge banners and the homepage hero (must equal PreloadImage::HERO_SIZES). */
    fullWidth: '100vw',
    /** Blog cover on the post page. */
    blogCover: '(min-width: 1024px) 900px, 100vw',
    /** Blog cards. */
    blogCard: '(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw',
    /** Sub-category tiles. */
    tile: '120px',
} as const;
