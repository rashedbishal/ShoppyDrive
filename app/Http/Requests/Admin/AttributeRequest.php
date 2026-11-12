<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or editing an attribute (Color, Size, Set, …) that products can
 * build their variants from. Names are unique ignoring case: variant labels
 * ({"Set": "12pcs"}) are keyed by name, so two "Set"s would collide.
 */
class AttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $attribute = $this->route('attribute');

        return [
            'name' => [
                'required', 'string', 'max:40',
                function (string $field, mixed $value, Closure $fail) use ($attribute): void {
                    $taken = Attribute::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                        ->when($attribute, fn ($query) => $query->whereKeyNot($attribute->id))
                        ->exists();

                    if ($taken) {
                        $fail('An attribute with this name already exists.');
                    }
                },
            ],
            // Colour swatches need every value to carry a hex code, so the
            // type is fixed once the attribute has values.
            'type' => [
                'required', Rule::in([AttributeType::SELECT->value, AttributeType::COLOR->value]),
                function (string $field, mixed $value, Closure $fail) use ($attribute): void {
                    if ($attribute !== null && $attribute->type->value !== $value && $attribute->values()->exists()) {
                        $fail("The type can't change once the attribute has values.");
                    }
                },
            ],
            'has_images' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
