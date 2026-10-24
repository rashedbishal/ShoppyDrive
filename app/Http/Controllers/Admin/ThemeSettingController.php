<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ThemeResolver;
use App\Support\BangladeshDivisions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;
use Inertia\Inertia;
use Inertia\Response;

class ThemeSettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/settings/theme', [
            'themeConfig' => Setting::find('theme_config')?->value ?? [
                'mode' => 'global',
                'global_theme' => 'default',
            ],
            'availableThemes' => ThemeResolver::THEMES,
            'divisions' => BangladeshDivisions::ALL,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $themes = ThemeResolver::THEMES;

        $validator = Validator::make($request->all(), [
            'mode' => ['required', 'in:global,ab_test,region'],
            'global_theme' => ['required_if:mode,global', Rule::in($themes)],
            'ab_test.variants' => ['required_if:mode,ab_test', 'array', 'min:1'],
            'ab_test.variants.*.theme' => ['required', Rule::in($themes)],
            'ab_test.variants.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'region_map' => ['required_if:mode,region', 'array'],
            'region_map.*' => ['required', Rule::in($themes)],
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request): void {
            $mode = $request->input('mode');

            if ($mode === 'ab_test') {
                $variantThemes = array_column($request->input('ab_test.variants', []), 'theme');
                if (count($variantThemes) !== count(array_unique($variantThemes))) {
                    $validator->errors()->add('ab_test.variants', 'Each A/B variant must use a different theme.');
                }
            }

            if ($mode === 'region') {
                $regionMap = $request->input('region_map', []);
                $requiredKeys = [...BangladeshDivisions::ALL, 'default'];
                $missing = array_diff($requiredKeys, array_keys($regionMap));
                if ($missing !== []) {
                    $validator->errors()->add('region_map', 'Choose a theme for every division, plus a fallback for other regions.');
                }
            }
        });

        $validated = $validator->validate();

        Setting::updateOrCreate(
            ['key' => 'theme_config'],
            ['value' => $validated, 'group' => 'theme'],
        );

        return redirect()->route('admin.settings.theme')
            ->with('success', 'Theme settings saved.');
    }
}
