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
     * Purifies only `sections.description.custom_content` in place — every
     * other key in this same JSON blob (hero, benefits, gallery,
     * testimonials, faq, footer, and the rest of `description` itself)
     * must survive untouched, since they're unrelated fields sharing one
     * column with this one rich-text value.
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
                'headline' => '',
                'subheadline' => '',
                'cta_text' => 'Order Now',
                'bg_color' => '#0f172a',
            ],
            'benefits' => [
                'enabled' => true,
                'title' => 'Why You\'ll Love This',
                'items' => ['Fast delivery', '100% genuine product', 'Easy returns'],
            ],
            'description' => [
                'enabled' => true,
                'title' => 'Product Details',
                'use_product_description' => true,
                'custom_content' => '',
            ],
            'gallery' => [
                'enabled' => true,
                'title' => 'Product Images',
            ],
            'testimonials' => [
                'enabled' => false,
                'title' => 'What Customers Say',
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

        return array_replace_recursive($defaults, $saved);
    }
}
