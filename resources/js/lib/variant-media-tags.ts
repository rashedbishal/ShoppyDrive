import type { VariantRow } from '@/components/admin/ProductVariantManager';

type AttributeValueOption = { id: number; value: string };

/**
 * Builds the "relate this media to…" dropdown options for the admin media
 * uploader from the currently configured variant rows. Tag values follow the
 * server's convention: "color:{id}" or "size:{id}". Images are chosen per
 * colour whenever the product has colours (with or without sizes): one Red
 * image serves Red/S, Red/M and Red/L, since size doesn't change the look.
 * Each colour or size is listed once, in variant order.
 */
export function buildMediaTagOptions(
    variants: VariantRow[],
    colorValues: AttributeValueOption[],
    sizeValues: AttributeValueOption[],
    hasColorVariant: boolean,
    hasSizeVariant: boolean,
): { value: string; label: string }[] | undefined {
    if (!hasColorVariant && !hasSizeVariant) {
        return undefined;
    }

    const colorLabel = (id: number | null) =>
        colorValues.find((c) => c.id === id)?.value ?? '—';
    const sizeLabel = (id: number | null) =>
        sizeValues.find((s) => s.id === id)?.value ?? '—';

    const uniqueOptions = (
        options: { value: string; label: string }[],
    ): { value: string; label: string }[] =>
        options.filter(
            (option, index) =>
                options.findIndex((o) => o.value === option.value) === index,
        );

    if (hasColorVariant) {
        return uniqueOptions(
            variants
                .filter((v) => v.color_value_id != null)
                .map((v) => ({
                    value: `color:${v.color_value_id}`,
                    label: colorLabel(v.color_value_id),
                })),
        );
    }

    return uniqueOptions(
        variants
            .filter((v) => v.size_value_id != null)
            .map((v) => ({
                value: `size:${v.size_value_id}`,
                label: sizeLabel(v.size_value_id),
            })),
    );
}
