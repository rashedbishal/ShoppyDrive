<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function show(Category $category, Request $request): Response
    {
        $products = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->when($request->brand_id, fn ($q) => $q->where('brand_id', $request->brand_id))
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('category/show', [
            'category' => $category,
            'products' => $products,
        ]);
    }
}
