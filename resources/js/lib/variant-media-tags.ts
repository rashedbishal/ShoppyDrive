import type { VariantRow } from '@/components/admin/ProductVariantManager';

type AttributeValueOption = { id: number; value: string };

/**
 * Builds the "relate this media to…" dropdown options for the admin media
 * uploader from the currently configured variant rows. Tag values follow the
 * server's convention: "color:{id}", "size:{id}", or "pair:{colorId}-{sizeId}".
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

    if (hasColorVariant && hasSizeVariant) {
        return variants.map((v) => ({
            value: `pair:${v.color_value_id}-${v.size_value_id}`,
            label: `${colorLabel(v.color_value_id)} / ${sizeLabel(v.size_value_id)}`,
        }));
    }

    if (hasColorVariant) {
        return variants.map((v) => ({
            value: `color:${v.color_value_id}`,
            label: colorLabel(v.color_value_id),
        }));
    }

    return variants.map((v) => ({
        value: `size:${v.size_value_id}`,
        label: sizeLabel(v.size_value_id),
    }));
}
