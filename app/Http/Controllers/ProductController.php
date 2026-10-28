<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Product $product): Response
    {
        $product->load([
            'category',
            'brand',
            'images',
            'media',
            'variants',
            'tags',
            'reviews' => fn ($q) => $q->where('is_approved', true)->with('user:id,name,avatar')->latest()->limit(50),
        ]);

        // Build attributes array from product_attribute_values → attribute
        $attributeValues = $product->attributeValues()->with('attribute')->orderBy('attribute_values.sort_order')->get();
        $attributes = $attributeValues
            ->groupBy('attribute_id')
            ->map(function ($values) {
                $attribute = $values->first()->attribute;

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'type' => $attribute->type instanceof \BackedEnum ? $attribute->type->value : $attribute->type,
                    'values' => $values->map(fn ($v) => [
                        'id' => $v->id,
                        'attribute_id' => $v->attribute_id,
                        'value' => $v->value,
                        'color_hex' => $v->color_hex,
                        'sort_order' => $v->sort_order,
                        'created_at' => $v->created_at,
                        'updated_at' => $v->updated_at,
                    ])->values(),
                    'created_at' => $attribute->created_at,
                    'updated_at' => $attribute->updated_at,
                ];
            })
            ->values();

        $ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($product->reviews as $review) {
            $ratingBreakdown[(int) $review->rating] = ($ratingBreakdown[(int) $review->rating] ?? 0) + 1;
        }

        $relatedProducts = Product::query()
            ->active()
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['images', 'brand', 'variants'])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('is_approved', true)])
            ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->limit(8)
            ->get();

        return Inertia::render('catalog/ProductDetail', [
            'product' => array_merge($product->toArray(), ['attributes' => $attributes, 'images' => ($product->images ?? collect())->map(fn ($img) => array_merge($img->toArray(), ['is_primary' => $img->is_primary]))->values()->toArray()]),
            'relatedProducts' => $relatedProducts,
            'reviews' => $product->reviews,
            'ratingBreakdown' => $ratingBreakdown,
            'shippingMethods' => [],
        ]);
    }
}
