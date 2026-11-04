import { isVideoUrl } from '@/lib/utils';

type MediaItem = File | string;

type ProductMediaFormState = {
    images: MediaItem[];
    media_tags: (string | null)[];
    primary_image_index: number | null;
    hover_image_index: number | null;
};

const isVideo = (item: MediaItem): boolean =>
    typeof item === 'string'
        ? isVideoUrl(item)
        : item.type.startsWith('video/');

/** Position of the media flagged as primary/hover when a form opens, or null. */
export function mediaFlagIndex<
    Key extends string,
    Item extends Record<Key, boolean>,
>(media: Item[], flag: Key): number | null {
    const index = media.findIndex((item) => item[flag]);

    return index === -1 ? null : index;
}

/**
 * Carries each item's tag and default/hover selection over to a new image
 * list. They're stored by position, so they follow the item itself when
 * others are added or removed. A removed default passes to the first image.
 */
export function remapProductMedia(
    previous: ProductMediaFormState,
    images: MediaItem[],
): ProductMediaFormState {
    const newIndexOf = (index: number | null): number =>
        index === null ? -1 : images.indexOf(previous.images[index]);

    const keptPrimary = newIndexOf(previous.primary_image_index ?? 0);
    const firstImage = images.findIndex((item) => !isVideo(item));
    const primary = keptPrimary !== -1 ? keptPrimary : Math.max(firstImage, 0);
    const hover = newIndexOf(previous.hover_image_index);

    return {
        images,
        media_tags: images.map((item) => {
            const previousIndex = previous.images.indexOf(item);

            return previousIndex === -1
                ? null
                : (previous.media_tags[previousIndex] ?? null);
        }),
        primary_image_index: images.length > 0 ? primary : null,
        hover_image_index: hover === -1 || hover === primary ? null : hover,
    };
}
