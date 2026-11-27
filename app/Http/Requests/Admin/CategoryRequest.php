<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\SubCategory;
use App\Rules\RasterImageOrUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CategoryRequest extends FormRequest
{
    /**
     * The admin picker (resources/js/lib/category-icons.tsx) offers every lucide-react icon
     * name (1700+), so icons are validated by their kebab-case shape rather than an enumerated
     * list that would need to be kept in sync with the npm package.
     */
    public const ICON_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public const HEX_COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

    /**
     * All 148 CSS Level-4 named colors offered in the admin picker, mirrored in
     * resources/js/lib/category-colors.ts.
     *
     * @var array<int, string>
     */
    public const COLOR_NAMES = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black',
        'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse',
        'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan',
        'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta',
        'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen',
        'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink',
        'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen',
        'fuchsia', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow',
        'grey', 'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender',
        'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan',
        'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon',
        'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue',
        'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine',
        'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue',
        'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream',
        'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange',
        'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred',
        'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple',
        'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell',
        'sienna', 'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen',
        'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white',
        'whitesmoke', 'yellow', 'yellowgreen',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'unique:categories,slug,'.($categoryId ?? 'NULL').',id,deleted_at,NULL',
                $this->notASubCategorySlug(...),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'image' => ['nullable', new RasterImageOrUrl],
            'icon' => ['nullable', 'string', 'max:50', 'regex:'.self::ICON_PATTERN],
            'color' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if (preg_match(self::HEX_COLOR_PATTERN, $value) === 1) {
                    return;
                }

                if (in_array(strtolower($value), self::COLOR_NAMES, true)) {
                    return;
                }

                $fail('The '.$attribute.' must be a named color or a #rrggbb hex code.');
            }],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * DISC-89: /category/{slug} looks categories up before sub-categories, so
     * a category taking a sub-category's slug would hide that sub-category's
     * page. Compared with the slug as saved (CategoryController applies
     * Str::slug). A category keeping its current slug is never blocked, so an
     * older clash can't stop unrelated edits.
     */
    private function notASubCategorySlug(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = Str::slug((string) $value);

        if ($slug === $this->route('category')?->slug) {
            return;
        }

        if (SubCategory::where('slug', $slug)->exists()) {
            $fail('A sub-category already uses this slug. Category and sub-category pages share the same /category/ address, so choose a different slug.');
        }
    }
}
