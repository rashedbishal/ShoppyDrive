<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mews\Purifier\Facades\Purifier;

class LandingPage extends Model
{
    use Auditable;

    protected $fillable = ['title', 'slug', 'product_id', 'is_active', 'max_order_quantity', 'sections'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_order_quantity' => 'integer',
            'sections' => 'array',
        ];
    }

    /**
     * Purifies only the rich-text values in `sections` in place —
     * `description.custom_content` and each written testimonial's `text` —
     * every other key in this same JSON blob (hero, benefits, gallery, faq,
     * footer, and the rest of `description` itself) must survive untouched,
     * since they're unrelated fields sharing one column with them.
     */
    protected static function booted(): void
    {
        static::saving(function (LandingPage $landingPage): void {
            $sections = $landingPage->sections;
            $customContent = $sections['description']['custom_content'] ?? null;

            if (is_array($sections) && is_string($customContent)) {
                $sections['description']['custom_content'] = Purifier::clean($customContent);
                $landingPage->sections = $sections;
            }

            $sections = $landingPage->sections;

            if (is_array($sections) && is_array($sections['testimonials']['items'] ?? null)) {
                foreach ($sections['testimonials']['items'] as $index => $item) {
                    if (is_array($item) && is_string($item['text'] ?? null)) {
                        $sections['testimonials']['items'][$index]['text'] = Purifier::clean($item['text']);
                    }
                }

                $landingPage->sections = $sections;
            }
        });
    }

    /** `sections` is the full page-builder payload — too large to keep duplicating into every log row. */
    protected function auditExcept(): array
    {
        return ['sections', 'created_at', 'updated_at'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function defaultSections(): array
    {
        return [
            'hero' => [
                'enabled' => true,
                'badge' => '',
                'headline' => '',
                'subheadline' => '',
                'cta_text' => 'Order Now',
                'secondary_cta_text' => 'See Details',
                'bg_color' => '#0b1020',
                'features' => [
                    ['title' => 'Fast delivery', 'text' => 'Delivered to your door across Bangladesh'],
                    ['title' => '100% genuine', 'text' => 'Quality checked before it ships'],
                    ['title' => 'Easy returns', 'text' => 'Hassle-free if anything is wrong'],
                ],
                'carousel' => [
                    'use_product_images' => true,
                    'hidden_media_ids' => [],
                    'extra_slides' => [],
                    'animation' => 'fade',
                    'autoplay' => true,
                    'interval_seconds' => 4,
                    'transition_ms' => 800,
                    'show_dots' => true,
                    'show_progress' => true,
                ],
            ],
            'benefits' => [
                'enabled' => true,
                'title' => 'Why You\'ll Love This',
                'items' => ['Fast delivery', '100% genuine product', 'Easy returns'],
            ],
            'description' => [
                'enabled' => true,
                'badge' => '',
                'title' => 'Product Details',
                'use_product_description' => true,
                'custom_content' => '',
                'selection_title' => 'Choose your set',
                'selection_hint' => 'Pick one or more options, then continue to order.',
            ],
            'gallery' => [
                'enabled' => true,
                'title' => 'Product Images',
            ],
            'combos' => [],
            'delivery' => [
                'enabled' => true,
                'title' => 'Where we deliver',
                'text' => 'We deliver all over Bangladesh. Inside Dhaka usually takes 1-2 days; elsewhere 2-5 days.',
                'areas' => ['Dhaka', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna', 'All other districts'],
                'cta_text' => 'Order Now',
                'contact_label' => 'Questions? Call us',
            ],
            'selection' => [
                'title' => 'Select your order',
                'subtitle' => 'Choose what you want, then tell us where to send it.',
                'show_single_options' => true,
                'notes_label' => 'Order notes',
                'notes_placeholder' => 'Anything we should know? (optional)',
            ],
            'contact' => [
                'phone' => '',
                'email' => '',
            ],
            'testimonials' => [
                'enabled' => false,
                'title' => 'What Customers Say',
                // 'text' shows the written reviews below; 'image' shows `images` (phone screenshots, photos) as a carousel.
                'mode' => 'text',
                'images' => [],
                'items' => [
                    ['name' => '', 'rating' => 5, 'text' => ''],
                ],
            ],
            'faq' => [
                'enabled' => false,
                'title' => 'Frequently Asked Questions',
                'items' => [
                    ['question' => '', 'answer' => ''],
                ],
            ],
            'footer' => [
                'enabled' => true,
                'text' => '',
            ],
        ];
    }

    public function getSections(): array
    {
        $defaults = self::defaultSections();
        $saved = $this->sections ?? [];

        // Lists are replaced wholesale, never merged index-by-index with the
        // defaults (array_replace_recursive would otherwise resurrect default
        // rows the admin deleted, or splice into an admin's shorter list).
        $listKeys = [
            ['hero', 'features'],
            ['hero', 'carousel', 'hidden_media_ids'],
            ['hero', 'carousel', 'extra_slides'],
            ['delivery', 'areas'],
            ['benefits', 'items'],
            ['testimonials', 'items'],
            ['testimonials', 'images'],
            ['faq', 'items'],
        ];

        $merged = array_replace_recursive($defaults, $saved);

        foreach ($listKeys as $path) {
            $value = $saved;

            foreach ($path as $key) {
                $value = is_array($value) && array_key_exists($key, $value) ? $value[$key] : null;
            }

            if (is_array($value)) {
                $target = &$merged;

                foreach (array_slice($path, 0, -1) as $key) {
                    $target = &$target[$key];
                }

                $target[end($path)] = array_values($value);
                unset($target);
            }
        }

        $merged['combos'] = array_values($saved['combos'] ?? []);

        // Pages saved before the hero got its own three feature boxes: reuse
        // their old "Benefits" list so nothing the admin wrote disappears.
        if (! isset($saved['hero']['features']) && ! empty($saved['benefits']['items'])) {
            $merged['hero']['features'] = collect($saved['benefits']['items'])
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->take(3)
                ->map(fn (string $item): array => ['title' => $item, 'text' => ''])
                ->values()
                ->all();
        }

        return $merged;
    }
}
