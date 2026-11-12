import type {
    VariantAttributeOption,
    VariantRow,
} from '@/components/admin/ProductVariantManager';

/**
 * Builds the "relate this media to…" dropdown options for the admin media
 * uploader. Images are chosen per attribute value — one Red photo serves
 * Red/S, Red/M and Red/L — for every variant attribute of this product that
 * allows images (Catalog › Attributes › "Images per value"). Only values some
 * variant actually uses are listed, each once, as "value:{id}".
 */
export function buildMediaTagOptions(
    variants: VariantRow[],
    attributes: VariantAttributeOption[],
    selectedAttributeIds: number[],
): { value: string; label: string }[] | undefined {
    const usedValueIds = new Set(variants.flatMap((row) => row.value_ids));
    const options = selectedAttributeIds
        .map((id) => attributes.find((attribute) => attribute.id === id))
        .filter(
            (attribute): attribute is VariantAttributeOption =>
                attribute !== undefined && attribute.has_images,
        )
        .flatMap((attribute) =>
            attribute.values
                .filter((value) => usedValueIds.has(value.id))
                .map((value) => ({
                    value: `value:${value.id}`,
                    label: `${attribute.name}: ${value.value}`,
                })),
        );

    return options.length > 0 ? options : undefined;
}
