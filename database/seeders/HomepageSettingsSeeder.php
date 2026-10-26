<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\PromotionalBanner;
use Illuminate\Database\Seeder;

class HomepageSettingsSeeder extends Seeder
{
    private const PLACEHOLDER = '/placeholder.svg';

    public function run(): void
    {
        $sections = [
            [
                'section_key' => 'announcement_bar',
                'section_name' => 'Announcement Bar',
                'title' => 'Free delivery on orders over ৳999',
                'is_visible' => true,
                'sort_order' => 0,
            ],
            [
                'section_key' => 'hero_slider',
                'section_name' => 'Hero Slider',
                'is_visible' => true,
                'sort_order' => 1,
            ],
            [
                'section_key' => 'flash_sale',
                'section_name' => 'Weekend Flash Sale',
                'eyebrow' => 'Flash sale',
                'title' => 'Weekend flash sale',
                'action_label' => 'Shop all',
                'action_href' => '/flash-sales',
                'is_visible' => true,
                'sort_order' => 2,
            ],
            [
                'section_key' => 'categories',
                'section_name' => 'Shop by Category',
                'eyebrow' => 'Categories',
                'title' => 'Shop by category',
                'description' => 'Curated entry points to the Bangladesh shopping experience, organized around the categories shoppers reach for most.',
                'action_label' => 'View all categories',
                'action_href' => '/products',
                'is_visible' => true,
                'sort_order' => 3,
            ],
            [
                'section_key' => 'featured_products',
                'section_name' => 'Featured Products',
                'eyebrow' => 'Featured',
                'title' => 'Featured products',
                'description' => 'Balanced merchandising that keeps the page premium, readable, and focused on conversion.',
                'action_label' => 'View all products',
                'action_href' => '/products',
                'is_visible' => true,
                'sort_order' => 4,
            ],
            [
                'section_key' => 'promotional_banners',
                'section_name' => 'Promotional Banners',
                'is_visible' => true,
                'sort_order' => 5,
            ],
            [
                'section_key' => 'new_arrivals',
                'section_name' => 'New Arrivals',
                'eyebrow' => 'Arrivals',
                'title' => 'New arrivals',
                'description' => 'Fresh inventory, updated merchandising, and products that feel new the moment they land.',
                'action_label' => 'See newest',
                'action_href' => '/products?sort=newest',
                'is_visible' => true,
                'sort_order' => 6,
            ],
            [
                'section_key' => 'brands',
                'section_name' => 'Our Brands',
                'eyebrow' => 'Brands',
                'title' => 'Our brands',
                'description' => 'A quieter section with grayscale brand treatment, letting shoppers scan premium labels without visual noise.',
                'action_label' => 'Explore brands',
                'action_href' => '/brands',
                'is_visible' => true,
                'sort_order' => 7,
            ],
            [
                'section_key' => 'best_sellers',
                'section_name' => 'Best Selling Items',
                'eyebrow' => 'Best sellers',
                'title' => 'Best selling items',
                'description' => 'Products with proven demand, strong ratings, and enough detail to justify the purchase quickly.',
                'action_label' => 'Shop best selling items',
                'action_href' => '/products?sort=popular',
                'is_visible' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($sections as $section) {
            HomepageSetting::updateOrCreate(
                ['section_key' => $section['section_key']],
                $section,
            );
        }

        HeroSlide::create([
            'eyebrow' => 'ShoppyDrive — Bangladesh-ready shopping',
            'title' => 'Style for every occasion — from casual to festive',
            'description' => "Explore men's and women's fashion with premium fabrics, clear pricing, and fast nationwide delivery.",
            'image' => self::PLACEHOLDER,
            'primary_cta_label' => "Shop Men's Clothing",
            'primary_cta_href' => '/category/mens-clothing',
            'stats' => [
                ['label' => 'Style drops', 'value' => 'Weekly'],
                ['label' => 'Returns',     'value' => 'Easy exchange'],
                ['label' => 'Delivery',    'value' => '24–48h'],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        HeroSlide::create([
            'eyebrow' => 'Gadgets & Electronics',
            'title' => 'Smart tech and audio gear at honest prices',
            'description' => 'Premium earbuds, power banks, and accessories curated for buyers who want confidence before checkout.',
            'image' => self::PLACEHOLDER,
            'primary_cta_label' => 'Shop Electronics',
            'primary_cta_href' => '/category/electronics',
            'stats' => [
                ['label' => 'Payment',  'value' => 'Cash on Delivery'],
                ['label' => 'Support',  'value' => 'Bangla + English'],
                ['label' => 'Coverage', 'value' => 'Nationwide'],
            ],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        PromotionalBanner::create([
            'title' => 'Refresh your daily essentials',
            'description' => 'Smart restocks, curated home items, and low-friction buying for repeat shoppers.',
            'image' => self::PLACEHOLDER,
            'href' => '/products?sort=newest',
            'badge' => 'New arrivals',
            'accent' => 'from-secondary/90 via-secondary/70 to-secondary/20',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PromotionalBanner::create([
            'title' => 'Upgrade your festival cart',
            'description' => 'Premium gifts, standout fashion, and bundle-friendly picks for the season.',
            'image' => self::PLACEHOLDER,
            'href' => '/products?sort=popular',
            'badge' => 'Seasonal edit',
            'accent' => 'from-primary/90 via-primary/70 to-primary/20',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
