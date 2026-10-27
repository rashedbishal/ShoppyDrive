<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttributeValueRequest;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttributeValueController extends Controller
{
    public function store(AttributeValueRequest $request): RedirectResponse
    {
        AttributeValue::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Value added.']);

        return redirect()->route('admin.attributes.index');
    }

    public function update(AttributeValueRequest $request, AttributeValue $attributeValue): RedirectResponse
    {
        $attributeValue->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Value updated.']);

        return redirect()->route('admin.attributes.index');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:attribute_values,id'],
        ])['ids'];

        foreach ($ids as $index => $id) {
            AttributeValue::whereKey($id)->update(['sort_order' => $index]);
        }

        return redirect()->route('admin.attributes.index');
    }

    public function destroy(AttributeValue $attributeValue): RedirectResponse
    {
        $products = $attributeValue->products()->get(['products.id', 'products.name', 'products.slug'])
            ->merge($attributeValue->colorVariants()->with('product:id,name,slug')->get()->pluck('product')->filter())
            ->merge($attributeValue->sizeVariants()->with('product:id,name,slug')->get()->pluck('product')->filter())
            ->unique('id')
            ->values();

        if ($products->isNotEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Can't delete \"{$attributeValue->value}\" — it's used by {$products->count()} product(s). Remove it from those products first.",
                'products' => $products->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                ])->all(),
            ]);

            return redirect()->route('admin.attributes.index');
        }

        $attributeValue->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Value deleted.']);

        return redirect()->route('admin.attributes.index');
    }
}
