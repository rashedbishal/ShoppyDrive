<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * The site's brand colour (owner decision 2026-11-14): one colour, picked by
 * the mother admin in Admin › Settings › Brand Colour, from which every shade
 * the site uses is derived — hover, light tint, the dark-mode variant, the
 * text colour that stays readable on it, and an RGB triplet for translucent
 * glows. It drives the CMS, the hardcoded brand spots on the storefront and
 * the Classic storefront theme; the visitor-selectable Neomorphism,
 * Glassmorphism and Aurora themes keep their own palettes. Error, warning
 * and delete reds are a different colour on purpose and are not affected.
 *
 * With nothing saved (or the default saved) css() returns null, so the page
 * renders exactly from app.css, with no inline override at all.
 */
final class BrandColor
{
    public const KEY = 'brand_color';

    public const DEFAULT = '#c8102e';

    /** The light, readable text colour on a dark brand colour, and the dark one on a light colour. */
    private const LIGHT_TEXT = '#ffffff';

    private const DARK_TEXT = '#1a1a2e';

    /** The exact shades app.css ships with for the default red, kept so it stays pixel-identical. */
    private const DEFAULT_PALETTE = [
        'brand' => '#c8102e',
        'hover' => '#a50d25',
        'light' => '#fff0f2',
        'foreground' => '#ffffff',
        'rgb' => '200 16 46',
        'dark' => '#e8112a',
        'dark_rgb' => '232 17 42',
        'bright' => '#ff3a55',
        'bright_rgb' => '255 58 85',
    ];

    /** The saved colour, or the default when none (or an invalid one) is saved. */
    public static function hex(): string
    {
        $saved = LayoutCache::settings()[self::KEY] ?? null;

        return is_string($saved) && self::isValid($saved) ? strtolower($saved) : self::DEFAULT;
    }

    public static function isValid(string $hex): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /**
     * Every shade derived from one colour.
     *
     * @return array{brand: string, hover: string, light: string, foreground: string, rgb: string, dark: string, dark_rgb: string, bright: string, bright_rgb: string}
     */
    public static function palette(?string $hex = null): array
    {
        $hex = strtolower($hex ?? self::hex());

        if (! self::isValid($hex) || $hex === self::DEFAULT) {
            return self::DEFAULT_PALETTE;
        }

        $rgb = self::toRgb($hex);
        // The same steps the default red's hand-picked shades follow: hover
        // ~17% darker, the tint ~6% of the colour on white, the dark-mode
        // colour ~12% lighter so it holds up on a dark background.
        $hover = self::mix($rgb, [0, 0, 0], 0.175);
        $light = self::mix($rgb, [255, 255, 255], 0.94);
        $dark = self::mix($rgb, [255, 255, 255], 0.12);
        // The top of the landing pages' glowing order buttons.
        $bright = self::mix($rgb, [255, 255, 255], 0.22);

        return [
            'brand' => $hex,
            'hover' => self::toHex($hover),
            'light' => self::toHex($light),
            'foreground' => self::readableTextOn($rgb),
            'rgb' => implode(' ', $rgb),
            'dark' => self::toHex($dark),
            'dark_rgb' => implode(' ', $dark),
            'bright' => self::toHex($bright),
            'bright_rgb' => implode(' ', $bright),
        ];
    }

    /**
     * The inline CSS that overrides app.css's default brand variables, or
     * null for the default colour. `html:root` outranks app.css's `:root`
     * whichever stylesheet loads last. Built only from validated hex values.
     */
    public static function css(): ?string
    {
        $hex = self::hex();

        if ($hex === self::DEFAULT) {
            return null;
        }

        $p = self::palette($hex);

        return 'html:root{'
            ."--brand:{$p['brand']};"
            ."--brand-hover:{$p['hover']};"
            ."--brand-light:{$p['light']};"
            ."--brand-foreground:{$p['foreground']};"
            ."--brand-rgb:{$p['rgb']};"
            ."--brand-dark:{$p['dark']};"
            ."--brand-dark-rgb:{$p['dark_rgb']};"
            ."--brand-bright:{$p['bright']};"
            ."--brand-bright-rgb:{$p['bright_rgb']};"
            .'}';
    }

    public static function save(string $hex): void
    {
        Setting::updateOrCreate(['key' => self::KEY], ['value' => strtolower($hex), 'group' => 'general']);
    }

    public static function reset(): void
    {
        Setting::where('key', self::KEY)->first()?->delete();
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function toRgb(string $hex): array
    {
        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }

    /**
     * Moves $from towards $to by $amount (0–1).
     *
     * @param  array{0: int, 1: int, 2: int}  $from
     * @param  array{0: int, 1: int, 2: int}  $to
     * @return array{0: int, 1: int, 2: int}
     */
    private static function mix(array $from, array $to, float $amount): array
    {
        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $amount),
            (int) round($from[1] + ($to[1] - $from[1]) * $amount),
            (int) round($from[2] + ($to[2] - $from[2]) * $amount),
        ];
    }

    /**
     * White or dark text, whichever has the higher WCAG contrast on the colour.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function readableTextOn(array $rgb): string
    {
        $background = self::luminance($rgb);
        $onWhite = (1.05) / ($background + 0.05);
        $onDark = ($background + 0.05) / (self::luminance(self::toRgb(self::DARK_TEXT)) + 0.05);

        return $onWhite >= $onDark ? self::LIGHT_TEXT : self::DARK_TEXT;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function luminance(array $rgb): float
    {
        $channel = static function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
    }
}
