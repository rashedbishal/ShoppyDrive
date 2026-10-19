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
        return [
            'attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'value' => ['required', 'string', 'max:100'],
            'color_hex' => [
                Rule::requiredIf(function (): bool {
                    $attribute = Attribute::find($this->input('attribute_id'));

                    return $attribute?->type === AttributeType::COLOR;
                }),
                'nullable',
                'string',
                'max:9',
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
