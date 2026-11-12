<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Attribute;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating or editing an attribute (Color, Size, Set, …) that products can
 * build their variants from. Names are unique ignoring case: variant labels
 * ({"Set": "12pcs"}) are keyed by name, so two "Set"s would collide.
 *
 * The display type isn't editable: new attributes are always text options
 * (AttributeController::store()) and an existing one keeps its type, so the
 * built-in Color keeps its swatches. A `type` sent anyway is ignored.
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
