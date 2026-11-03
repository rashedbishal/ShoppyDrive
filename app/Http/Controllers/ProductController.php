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
        // FSA-05: inactive, draft and future-scheduled products don't exist
        // as far as the storefront is concerned.
        abort_unless($product->isPubliclyVisible(), 404);

        $product->load([
            'category',
            'brand',
            'images',
            'media',
            'variants',
            'tags',
            // Capped at the 50 most recent for display only — the
            // aggregates below are computed independently of this limit.
            'reviews' => fn ($q) => $q->where('is_approved', true)->with('user:id,name,avatar')->latest()->limit(50),
        ]);

        // FSA-18: the review list above is capped at 50 for display, but the
        // header rating and the Reviews tab count must reflect every
        // approved review. Same withCount()/withAvg() convention as
        // ProductListingController/HomeController/etc., just via
        // loadCount()/loadAvg() since $product is already resolved.
        $product->loadCount(['reviews as reviews_count' => fn ($q) => $q->where('is_approved', true)]);
        $product->loadAvg(['reviews as average_rating' => fn ($q) => $q->where('is_approved', true)], 'rating');

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

        // FSA-18: a `GROUP BY rating` over every approved review for this
        // product, independent of the 50-review display cap above — not
        // the capped $product->reviews collection, which previously made
        // this wrong (and silently drifting, since it's the 50 *most
        // recent*) for any product with more than 50 approved reviews.
        $approvedRatingCounts = $product->reviews()
            ->where('is_approved', true)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $ratingBreakdown = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $ratingBreakdown[$star] = (int) ($approvedRatingCounts[$star] ?? 0);
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
