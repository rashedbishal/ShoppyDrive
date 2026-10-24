<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Request;

class ThemeResolver
{
    /**
     * @var array<int, string>
     */
    public const THEMES = ['default', 'neomorphism', 'glassmorphism', 'aurora'];

    public function __construct(private readonly GeoLocationService $geoLocation) {}

    /**
     * Resolve which theme a request should see, and whether the A/B cookie needs (re)setting.
     *
     * @return array{theme: string, setCookie: bool}
     */
    public function resolve(Request $request): array
    {
        if ($previewTheme = $this->previewOverride($request)) {
            return ['theme' => $previewTheme, 'setCookie' => false];
        }

        $config = Setting::find('theme_config')?->value ?? [
            'mode' => 'global',
            'global_theme' => 'default',
        ];

        return match ($config['mode'] ?? 'global') {
            'ab_test' => $this->resolveAbTest($request, $config),
            'region' => ['theme' => $this->resolveRegion($request, $config), 'setCookie' => false],
            default => ['theme' => $this->validTheme($config['global_theme'] ?? null), 'setCookie' => false],
        };
    }

    private function previewOverride(Request $request): ?string
    {
        if (! $request->user('admin')) {
            return null;
        }

        $requested = $request->query('preview_theme');

        return is_string($requested) && in_array($requested, self::THEMES, true) ? $requested : null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{theme: string, setCookie: bool}
     */
    private function resolveAbTest(Request $request, array $config): array
    {
        $variants = $config['ab_test']['variants'] ?? [];
        $validThemes = array_column($variants, 'theme');

        $cookieTheme = $request->cookie('theme_variant');
        if (is_string($cookieTheme) && in_array($cookieTheme, $validThemes, true)) {
            return ['theme' => $cookieTheme, 'setCookie' => false];
        }

        if ($validThemes === []) {
            return ['theme' => 'default', 'setCookie' => false];
        }

        return ['theme' => $this->weightedPick($variants), 'setCookie' => true];
    }

    /**
     * @param  array<int, array{theme: string, weight: int}>  $variants
     */
    private function weightedPick(array $variants): string
    {
        $totalWeight = array_sum(array_column($variants, 'weight'));
        if ($totalWeight <= 0) {
            return $variants[0]['theme'];
        }

        $roll = random_int(1, $totalWeight);
        $cumulative = 0;

        foreach ($variants as $variant) {
            $cumulative += $variant['weight'];
            if ($roll <= $cumulative) {
                return $variant['theme'];
            }
        }

        return $variants[array_key_last($variants)]['theme'];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveRegion(Request $request, array $config): string
    {
        $regionMap = $config['region_map'] ?? [];
        $division = $this->geoLocation->divisionForIp($request->ip() ?? '');

        if ($division !== null && isset($regionMap[$division])) {
            return $this->validTheme($regionMap[$division]);
        }

        return $this->validTheme($regionMap['default'] ?? null);
    }

    private function validTheme(?string $theme): string
    {
        return $theme !== null && in_array($theme, self::THEMES, true) ? $theme : 'default';
    }
}
