<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttributeValueRequest;
use App\Models\AttributeValue;
use Illuminate\Http\RedirectResponse;

class AttributeValueController extends Controller
{
    public function store(AttributeValueRequest $request): RedirectResponse
    {
        AttributeValue::create($request->validated());

        return redirect()->route('admin.attributes.index')->with('success', 'Value added.');
    }

    public function update(AttributeValueRequest $request, AttributeValue $attributeValue): RedirectResponse
    {
        $attributeValue->update($request->validated());

        return redirect()->route('admin.attributes.index')->with('success', 'Value updated.');
    }

    public function destroy(AttributeValue $attributeValue): RedirectResponse
    {
        $productCount = $attributeValue->products()->count();
        $variantCount = $attributeValue->colorVariants()->count() + $attributeValue->sizeVariants()->count();

        if ($productCount > 0 || $variantCount > 0) {
            return redirect()->route('admin.attributes.index')
                ->with('error', "Can't delete \"{$attributeValue->value}\" — it's used by {$productCount} product(s). Remove it from those products first.");
        }

        $attributeValue->delete();

        return redirect()->route('admin.attributes.index')->with('success', 'Value deleted.');
    }
}
