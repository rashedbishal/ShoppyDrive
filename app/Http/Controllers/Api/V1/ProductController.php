<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->active()
            ->published()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }
}
