<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Arr;

/**
 * PERF-002: what a product card needs, instead of the whole product record.
 *
 * A card's product is more than what it shows: adding to cart hands this same
 * object to the cart drawer, the cart page, checkout and analytics, so every
 * field any of them reads stays. Before adding to or removing from these
 * lists, check those readers, not only ProductCard.tsx.
 *
 * Variants stay whole because cart pricing and stock read them directly.
 */
class ProductCardData
{
    /** @var list<string> */
    private const array PRODUCT_FIELDS = [
        'id', 'name', 'slug', 'sku', 'short_description', 'is_digital',
        'price', 'compare_price', 'stock_quantity', 'available_stock', 'low_stock_threshold',
        'average_rating', 'reviews_count',
        'in_flash_sale', 'flash_sale_price', 'flash_sale_ends_at',
        'free_delivery', 'free_delivery_min_qty', 'has_color_variant', 'has_size_variant', 'cry_baby_enabled',
        'category_id', 'sub_category_id', 'brand_id',
        'variants',
    ];

    /** @var list<string> */
    private const array RELATED_FIELDS = ['id', 'name', 'slug'];

    /** @var list<string> */
    private const array IMAGE_FIELDS = ['id', 'path', 'alt', 'type', 'is_primary', 'is_hover', 'sort_order'];

    /**
     * Relations come through only if the caller loaded them.
     *
     * @return array<string, mixed>
     */
    public static function make(Product $product): array
    {
        $data = $product->toArray();
        $card = Arr::only($data, self::PRODUCT_FIELDS);

        foreach (['category', 'brand'] as $relation) {
            if (is_array($data[$relation] ?? null)) {
                $card[$relation] = Arr::only($data[$relation], self::RELATED_FIELDS);
            }
        }

        if (is_array($data['images'] ?? null)) {
            $card['images'] = array_map(fn (array $image): array => Arr::only($image, self::IMAGE_FIELDS), $data['images']);

            $primaryImage = collect($card['images'])->firstWhere('is_primary', true) ?? ($card['images'][0] ?? null);
            $card['primary_image_url'] = $primaryImage['path'] ?? null;
        }

        return $card;
    }
}
