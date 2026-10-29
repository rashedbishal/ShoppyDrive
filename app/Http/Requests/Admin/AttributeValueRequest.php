<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Resolved once and reused below: which attribute (Color/Size) this
        // value belongs to — from the submitted attribute_id on create, or
        // from the existing value's own attribute on update, since update
        // requests don't resubmit attribute_id (see 'sometimes' below).
        $attributeId = $this->filled('attribute_id')
            ? (int) $this->input('attribute_id')
            : $this->route('attribute_value')?->attribute_id;
        $attribute = $attributeId ? Attribute::find($attributeId) : null;
        $currentValueId = $this->route('attribute_value')?->id;
        $isColor = $attribute?->type === AttributeType::COLOR;

        $colorHexRules = [
            Rule::requiredIf(fn (): bool => $isColor),
            'nullable',
            'string',
            'max:9',
        ];

        if ($isColor) {
            $colorHexRules[] = 'regex:/^#[0-9a-fA-F]{6}$/';

            // Only enforced for the Color attribute itself: the add/edit form
            // always submits a color_hex value (even for Size, where it's
            // just an unused default), so checking uniqueness unconditionally
            // would falsely reject adding a second size.
            $colorHexRules[] = Rule::unique('attribute_values', 'color_hex')
                ->where('attribute_id', $attributeId)
                ->ignore($currentValueId);
        }

        return [
            'attribute_id' => [
                Rule::requiredIf(fn (): bool => $this->isMethod('post')),
                'sometimes', 'integer', 'exists:attributes,id',
            ],
            'value' => [
                'required', 'string', 'max:20',
                // Scoped per attribute, not globally — "M" must be unique
                // among sizes and "Red" among colors, but they don't
                // collide with each other.
                Rule::unique('attribute_values', 'value')
                    ->where('attribute_id', $attributeId)
                    ->ignore($currentValueId),
            ],
            'color_hex' => $colorHexRules,
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.unique' => 'This value already exists.',
            'color_hex.regex' => 'The color code must be a hex value like #C8102E.',
            'color_hex.unique' => 'This color code is already used by another color.',
        ];
    }
}
