<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private const PLACEHOLDER = '/placeholder.svg';

    public function run(): void
    {
        // Truncate all content tables (preserves users, settings, orders)
        Schema::disableForeignKeyConstraints();
        foreach (['flash_sale_items', 'flash_sales', 'product_media', 'products', 'sub_categories', 'categories', 'brands', 'hero_slides', 'promotional_banners'] as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password')],
        );

        $this->call(HomepageSettingsSeeder::class);
        $this->seedSettings();
        $categories = $this->seedCategories();
        $brands = $this->seedBrands();
        $this->seedProducts($categories, $brands);
    }

    private function seedSettings(): void
    {
        DB::table('settings')->upsert([
            ['key' => 'site_name',        'value' => json_encode('ShoppyDrive'),           'group' => 'general'],
            ['key' => 'currency_symbol',  'value' => json_encode('৳'),                     'group' => 'general'],
            ['key' => 'contact_email',    'value' => json_encode('support@shoppydrive.com'), 'group' => 'general'],
            ['key' => 'contact_phone',    'value' => json_encode('+880 1700-000000'),        'group' => 'general'],
            ['key' => 'social_links',     'value' => json_encode([
                'facebook' => 'https://facebook.com/shoppydrive',
                'instagram' => 'https://instagram.com/shoppydrive',
            ]), 'group' => 'general'],
        ], ['key'], ['value', 'group']);
    }

    private function seedCategories(): array
    {
        $tree = [
            [
                'name' => "Men's Clothing",
                'slug' => 'mens-clothing',
                'description' => "Complete men's wardrobe from casual to formal.",
                'children' => [
                    ['name' => 'T-Shirts & Polos',            'slug' => 't-shirts-polos'],
                    ['name' => 'Shirts (Casual & Formal)',     'slug' => 'mens-shirts'],
                    ['name' => 'Jeans & Trousers',             'slug' => 'jeans-trousers'],
                    ['name' => 'Hoodies & Sweatshirts',        'slug' => 'mens-hoodies'],
                    ['name' => 'Jackets & Coats',              'slug' => 'jackets-coats'],
                    ['name' => 'Innerwear & Sleepwear',        'slug' => 'mens-innerwear'],
                    ['name' => 'Traditional Wear',             'slug' => 'mens-traditional'],
                ],
            ],
            [
                'name' => "Women's Clothing",
                'slug' => 'womens-clothing',
                'description' => "Elegant women's fashion for every occasion.",
                'children' => [
                    ['name' => 'Dresses',                 'slug' => 'dresses'],
                    ['name' => 'Tops, Tees & Blouses',    'slug' => 'tops-tees-blouses'],
                    ['name' => 'Jeans, Pants & Skirts',   'slug' => 'womens-jeans-pants'],
                    ['name' => 'Hoodies & Sweaters',       'slug' => 'womens-hoodies'],
                    ['name' => 'Traditional Wear',         'slug' => 'womens-traditional'],
                    ['name' => 'Lingerie & Sleepwear',     'slug' => 'womens-lingerie'],
                ],
            ],
            [
                'name' => 'Shoes',
                'slug' => 'shoes',
                'description' => 'Footwear for every style and occasion.',
                'children' => [
                    ['name' => "Men's Sneakers & Sports Shoes", 'slug' => 'mens-sneakers'],
                    ['name' => "Men's Formal & Casual Shoes",   'slug' => 'mens-formal-shoes'],
                    ['name' => "Women's Sneakers",              'slug' => 'womens-sneakers'],
                    ['name' => 'Heels & Flats',                 'slug' => 'heels-flats'],
                    ['name' => 'Sandals & Slippers',            'slug' => 'sandals-slippers'],
                    ['name' => 'Boots',                         'slug' => 'boots'],
                ],
            ],
            [
                'name' => 'Bags & Luggage',
                'slug' => 'bags-luggage',
                'description' => 'Bags, wallets and travel essentials.',
                'children' => [
                    ['name' => 'Backpacks',                           'slug' => 'backpacks'],
                    ['name' => "Women's Handbags & Crossbody Bags",   'slug' => 'handbags'],
                    ['name' => 'Wallets & Purses',                    'slug' => 'wallets-purses'],
                    ['name' => 'Travel Bags & Luggage',               'slug' => 'travel-bags'],
                    ['name' => 'Laptop Bags & Briefcases',            'slug' => 'laptop-bags'],
                ],
            ],
            [
                'name' => 'Jewelry & Accessories',
                'slug' => 'jewelry-accessories',
                'description' => 'Jewelry, eyewear and fashion accessories.',
                'children' => [
                    ['name' => 'Necklaces & Pendants',  'slug' => 'necklaces'],
                    ['name' => 'Earrings & Rings',      'slug' => 'earrings-rings'],
                    ['name' => 'Bracelets & Bangles',   'slug' => 'bracelets-bangles'],
                    ['name' => 'Sunglasses & Eyewear',  'slug' => 'sunglasses'],
                    ['name' => 'Belts, Ties & Hats',    'slug' => 'belts-ties-hats'],
                ],
            ],
            [
                'name' => 'Watches',
                'slug' => 'watches',
                'description' => 'Premium watches for every wrist.',
                'children' => [
                    ['name' => "Men's Watches",    'slug' => 'mens-watches'],
                    ['name' => "Women's Watches",  'slug' => 'womens-watches'],
                    ['name' => 'Smart Watches',    'slug' => 'smart-watches'],
                    ['name' => 'Couple Watches',   'slug' => 'couple-watches'],
                ],
            ],
            [
                'name' => 'Electronics & Gadgets',
                'slug' => 'electronics',
                'description' => 'Gadgets, accessories and smart tech.',
                'children' => [
                    ['name' => 'Earbuds, Headphones & Speakers', 'slug' => 'audio'],
                    ['name' => 'Mobile Phone Accessories',        'slug' => 'phone-accessories'],
                    ['name' => 'Power Banks & Chargers',          'slug' => 'power-banks'],
                    ['name' => 'Smart Home Devices',              'slug' => 'smart-home'],
                    ['name' => 'Computer Accessories',            'slug' => 'computer-accessories'],
                ],
            ],
            [
                'name' => 'Beauty & Health',
                'slug' => 'beauty-health',
                'description' => 'Skincare, makeup, grooming and wellness.',
                'children' => [
                    ['name' => 'Makeup & Cosmetics',       'slug' => 'makeup'],
                    ['name' => 'Skincare Products',        'slug' => 'skincare'],
                    ['name' => 'Haircare & Styling',       'slug' => 'haircare'],
                    ['name' => 'Fragrances & Perfumes',    'slug' => 'fragrances'],
                    ['name' => 'Grooming Tools',           'slug' => 'grooming'],
                ],
            ],
            [
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'description' => 'Decor, kitchen, bedding and home improvement.',
                'children' => [
                    ['name' => 'Home Decor',                  'slug' => 'home-decor'],
                    ['name' => 'Kitchen & Dining',             'slug' => 'kitchen-dining'],
                    ['name' => 'Bedding & Bath',               'slug' => 'bedding-bath'],
                    ['name' => 'Storage & Organization',       'slug' => 'storage'],
                    ['name' => 'Tools & Home Improvement',     'slug' => 'tools'],
                ],
            ],
            [
                'name' => 'Mother & Kids',
                'slug' => 'mother-kids',
                'description' => 'Baby, kids clothing, toys and maternity care.',
                'children' => [
                    ['name' => "Boys' Clothing",           'slug' => 'boys-clothing'],
                    ['name' => "Girls' Clothing",          'slug' => 'girls-clothing'],
                    ['name' => 'Baby Clothes & Shoes',     'slug' => 'baby-clothes'],
                    ['name' => 'Toys & Hobbies',           'slug' => 'toys-hobbies'],
                    ['name' => 'Maternity Wear & Care',    'slug' => 'maternity'],
                ],
            ],
            [
                'name' => 'Sports & Outdoors',
                'slug' => 'sports-outdoors',
                'description' => 'Activewear, sports equipment and outdoor gear.',
                'children' => [
                    ['name' => 'Activewear / Gym Wear',         'slug' => 'activewear'],
                    ['name' => 'Sports Shoes',                  'slug' => 'sports-shoes'],
                    ['name' => 'Fitness & Gym Equipment',       'slug' => 'gym-equipment'],
                    ['name' => 'Outdoor Gear',                  'slug' => 'outdoor-gear'],
                ],
            ],
        ];

        $created = [];
        foreach ($tree as $sort => $data) {
            $parent = Category::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'image' => self::PLACEHOLDER,
                'description' => $data['description'] ?? $data['name'].' products.',
                'meta_title' => $data['name'].' | ShoppyDrive',
                'meta_description' => $data['description'] ?? '',
                'is_active' => true,
                'sort_order' => $sort + 1,
            ]);

            $created[$data['slug']] = $parent;

            foreach ($data['children'] as $childSort => $child) {
                $childModel = SubCategory::create([
                    'name' => $child['name'],
                    'slug' => $child['slug'],
                    'category_id' => $parent->id,
                    'image' => self::PLACEHOLDER,
                    'description' => $child['name'].' — curated selection on ShoppyDrive.',
                    'meta_title' => $child['name'].' | ShoppyDrive',
                    'meta_description' => $child['name'].' products.',
                    'is_active' => true,
                    'sort_order' => $childSort + 1,
                ]);
                $created[$child['slug']] = $childModel;
            }
        }

        return $created;
    }

    private function seedBrands(): array
    {
        $brands = [
            ['name' => 'Samsung',  'slug' => 'samsung',  'description' => 'World-class electronics and smart devices'],
            ['name' => 'Anker',    'slug' => 'anker',    'description' => 'Reliable audio and charging accessories'],
            ['name' => 'Aarong',   'slug' => 'aarong',   'description' => "Bangladesh's leading fashion brand"],
            ['name' => 'Walton',   'slug' => 'walton',   'description' => 'Homegrown appliances and electronics'],
            ['name' => 'Bata',     'slug' => 'bata',     'description' => 'Trusted everyday footwear for all ages'],
            ['name' => 'CeraVe',   'slug' => 'cerave',   'description' => 'Dermatologist-recommended skincare'],
            ['name' => 'Nike',     'slug' => 'nike',     'description' => 'Performance sportswear and footwear'],
            ['name' => 'Adidas',   'slug' => 'adidas',   'description' => 'Sports and lifestyle apparel'],
            ['name' => 'Apple',    'slug' => 'apple',    'description' => 'Premium devices and accessories'],
            ['name' => 'Xiaomi',   'slug' => 'xiaomi',   'description' => 'Affordable smart gadgets and electronics'],
            ['name' => 'H&M',      'slug' => 'hm',       'description' => 'Fast fashion for men and women'],
            ['name' => "Levi's",   'slug' => 'levis',    'description' => 'Iconic denim and casual wear'],
            ['name' => 'Fossil',   'slug' => 'fossil',   'description' => 'Classic watches and accessories'],
            ['name' => 'Puma',     'slug' => 'puma',     'description' => 'Athletic gear and urban style'],
            ['name' => 'Titan',    'slug' => 'titan',    'description' => 'Premium watch collection'],
        ];

        $created = [];
        foreach ($brands as $brand) {
            $model = Brand::create([
                'name' => $brand['name'],
                'slug' => $brand['slug'],
                'description' => $brand['description'],
                'is_active' => true,
            ]);
            $created[$brand['slug']] = $model;
        }

        return $created;
    }

    private function seedProducts(array $categories, array $brands): void
    {
        $products = [
            // Men's Clothing
            ['name' => 'Classic Slim Fit Polo T-Shirt',   'slug' => 'classic-slim-fit-polo-t-shirt',   'category' => 't-shirts-polos',    'brand' => 'hm',    'price' => 1290,  'compare_price' => 1790,  'stock' => 85,  'featured' => true,  'description' => 'Breathable cotton polo with a slim silhouette. Perfect for casual Fridays and weekend outings.'],
            ['name' => 'Oxford Formal Shirt White',        'slug' => 'oxford-formal-shirt-white',        'category' => 'mens-shirts',       'brand' => 'aarong', 'price' => 1890,  'compare_price' => 2490,  'stock' => 60,  'featured' => true,  'description' => 'Crisp white Oxford shirt crafted from premium cotton. A wardrobe staple for every occasion.'],
            ['name' => 'Slim Fit Stretch Jeans Dark Blue', 'slug' => 'slim-fit-stretch-jeans-dark-blue', 'category' => 'jeans-trousers',    'brand' => 'levis', 'price' => 3290,  'compare_price' => 4200,  'stock' => 72,  'featured' => false, 'description' => 'Slim fit jeans with 2% stretch elastane for all-day comfort.'],
            ['name' => 'Pullover Fleece Hoodie Navy',      'slug' => 'pullover-fleece-hoodie-navy',      'category' => 'mens-hoodies',      'brand' => 'nike',  'price' => 2990,  'compare_price' => 3990,  'stock' => 45,  'featured' => true,  'description' => 'Soft fleece pullover hoodie with a spacious kangaroo pocket.'],
            ['name' => 'Premium Linen Panjabi Cream',      'slug' => 'premium-linen-panjabi-cream',      'category' => 'mens-traditional',  'brand' => 'aarong', 'price' => 3290,  'compare_price' => 3890,  'stock' => 58,  'featured' => true,  'description' => 'Breathable linen panjabi with embroidered collar detail. Perfect for Eid and cultural events.'],
            // Women's Clothing
            ['name' => 'Floral Midi Dress Printed',        'slug' => 'floral-midi-dress-printed',        'category' => 'dresses',           'brand' => 'hm',    'price' => 2490,  'compare_price' => 3290,  'stock' => 40,  'featured' => true,  'description' => 'Lightweight floral midi dress in a flattering A-line cut.'],
            ['name' => 'Heritage Banarasi Silk Saree',     'slug' => 'heritage-banarasi-silk-saree',     'category' => 'womens-traditional', 'brand' => 'aarong', 'price' => 7990,  'compare_price' => 9990,  'stock' => 24,  'featured' => true,  'description' => 'Exquisite Banarasi silk saree with intricate zari work. A festive essential.'],
            ['name' => 'Casual Crop Top Ribbed',           'slug' => 'casual-crop-top-ribbed',           'category' => 'tops-tees-blouses', 'brand' => 'hm',    'price' => 990,   'compare_price' => 1390,  'stock' => 95,  'featured' => false, 'description' => 'Stretchy ribbed crop top in a relaxed fit.'],
            // Shoes
            ['name' => 'Air Cushion Running Sneakers',     'slug' => 'air-cushion-running-sneakers',     'category' => 'mens-sneakers',     'brand' => 'nike',  'price' => 7490,  'compare_price' => 9990,  'stock' => 35,  'featured' => true,  'description' => 'Lightweight running sneakers with air-cushioned midsole.'],
            ['name' => "Men's Oxford Leather Shoes",       'slug' => 'mens-oxford-leather-shoes',        'category' => 'mens-formal-shoes', 'brand' => 'bata',  'price' => 4990,  'compare_price' => 6200,  'stock' => 28,  'featured' => false, 'description' => 'Classic full-grain leather Oxford with rubber sole.'],
            ['name' => "Women's Strappy Heeled Sandals",   'slug' => 'womens-strappy-heeled-sandals',    'category' => 'heels-flats',       'brand' => 'bata',  'price' => 2490,  'compare_price' => 3290,  'stock' => 50,  'featured' => false, 'description' => 'Elegant strappy heeled sandals with a cushioned footbed.'],
            // Bags
            ['name' => 'Urban Laptop Backpack 30L',        'slug' => 'urban-laptop-backpack-30l',        'category' => 'backpacks',         'brand' => 'nike',  'price' => 4290,  'compare_price' => 5490,  'stock' => 62,  'featured' => true,  'description' => 'Durable 30L backpack with a padded 15" laptop sleeve and multiple organizer pockets.'],
            ['name' => 'Leather Tote Handbag Tan',         'slug' => 'leather-tote-handbag-tan',         'category' => 'handbags',          'brand' => 'hm',    'price' => 3490,  'compare_price' => 4500,  'stock' => 38,  'featured' => false, 'description' => 'Structured leather tote with a roomy interior and magnetic snap closure.'],
            // Jewelry
            ['name' => 'Gold Plated Layer Necklace Set',   'slug' => 'gold-plated-layer-necklace-set',   'category' => 'necklaces',         'brand' => 'aarong', 'price' => 1490,  'compare_price' => 1990,  'stock' => 75,  'featured' => false, 'description' => 'Delicate 3-layer gold plated necklace set. Lightweight and hypoallergenic for daily wear.'],
            ['name' => 'Polarized UV400 Aviator Sunglasses', 'slug' => 'polarized-uv400-aviator-sunglasses', 'category' => 'sunglasses',        'brand' => 'hm',    'price' => 1290,  'compare_price' => 1890,  'stock' => 88,  'featured' => true,  'description' => 'Classic aviator sunglasses with polarized lenses and UV400 protection.'],
            // Watches
            ['name' => "Men's Chronograph Stainless Watch", 'slug' => 'mens-chronograph-stainless-watch',  'category' => 'mens-watches',      'brand' => 'fossil', 'price' => 12990, 'compare_price' => 16500, 'stock' => 20,  'featured' => true,  'description' => 'Classic stainless steel chronograph with date window and mineral glass.'],
            ['name' => 'Samsung Galaxy Watch 6',           'slug' => 'samsung-galaxy-watch-6',           'category' => 'smart-watches',     'brand' => 'samsung', 'price' => 28990, 'compare_price' => 34990, 'stock' => 15,  'featured' => true,  'description' => 'Advanced health tracking with ECG, blood pressure monitoring and AMOLED display.'],
            ['name' => "Women's Rose Gold Slim Watch",     'slug' => 'womens-rose-gold-slim-watch',      'category' => 'womens-watches',    'brand' => 'titan', 'price' => 6990,  'compare_price' => 8990,  'stock' => 30,  'featured' => false, 'description' => 'Slim rose gold watch with a mesh strap and minimalist dial.'],
            // Electronics
            ['name' => 'AirGo Pro ANC Wireless Earbuds',  'slug' => 'airgo-pro-anc-wireless-earbuds',   'category' => 'audio',             'brand' => 'anker', 'price' => 8990,  'compare_price' => 11990, 'stock' => 42,  'featured' => true,  'description' => 'Compact ANC earbuds with 30h battery life and USB-C charging.'],
            ['name' => 'Xiaomi 10000mAh Power Bank',       'slug' => 'xiaomi-10000mah-power-bank',       'category' => 'power-banks',       'brand' => 'xiaomi', 'price' => 1890,  'compare_price' => 2490,  'stock' => 110, 'featured' => false, 'description' => 'Slim 10000mAh power bank with dual USB output and 18W fast charge.'],
            ['name' => 'Samsung Galaxy S24 Ultra 5G',      'slug' => 'samsung-galaxy-s24-ultra-5g',      'category' => 'phone-accessories', 'brand' => 'samsung', 'price' => 134999, 'compare_price' => 149999, 'stock' => 16,  'featured' => true,  'description' => 'Flagship smartphone with 200MP camera, S Pen and titanium frame.'],
            ['name' => 'Mechanical RGB Gaming Keyboard',   'slug' => 'mechanical-rgb-gaming-keyboard',   'category' => 'computer-accessories', 'brand' => 'xiaomi', 'price' => 3490, 'compare_price' => 4500,  'stock' => 55,  'featured' => false, 'description' => 'Compact TKL mechanical keyboard with per-key RGB and clicky blue switches.'],
            // Beauty
            ['name' => 'CeraVe Hydrating Skincare Duo',   'slug' => 'cerave-hydrating-skincare-duo',    'category' => 'skincare',          'brand' => 'cerave', 'price' => 2890,  'compare_price' => 3590,  'stock' => 70,  'featured' => true,  'description' => 'Gentle hydrating cleanser and moisturizer duo with ceramides.'],
            ['name' => 'Complete Makeup Starter Kit',      'slug' => 'complete-makeup-starter-kit',      'category' => 'makeup',            'brand' => 'hm',    'price' => 2290,  'compare_price' => 3100,  'stock' => 48,  'featured' => false, 'description' => 'All-in-one makeup kit with foundation, blush, eyeshadow, mascara and lipstick.'],
            ['name' => 'Argan Oil Hair Serum 100ml',       'slug' => 'argan-oil-hair-serum-100ml',       'category' => 'haircare',          'brand' => 'cerave', 'price' => 990,   'compare_price' => 1390,  'stock' => 120, 'featured' => false, 'description' => 'Lightweight argan oil serum that tames frizz and adds brilliant shine.'],
            ['name' => 'Wireless Electric Beard Trimmer',  'slug' => 'wireless-electric-beard-trimmer',  'category' => 'grooming',          'brand' => 'xiaomi', 'price' => 1990,  'compare_price' => 2790,  'stock' => 65,  'featured' => false, 'description' => 'Cordless beard trimmer with 40 length settings and self-sharpening blades.'],
            // Home & Living
            ['name' => 'Compact Air Fryer 4L',             'slug' => 'compact-air-fryer-4l',             'category' => 'kitchen-dining',    'brand' => 'walton', 'price' => 7990,  'compare_price' => 9990,  'stock' => 19,  'featured' => true,  'description' => 'Countertop air fryer with 7 preset programs and rapid air circulation.'],
            ['name' => 'Smart Rice Cooker 1.8L',           'slug' => 'smart-rice-cooker-1-8l',           'category' => 'kitchen-dining',    'brand' => 'walton', 'price' => 5290,  'compare_price' => 6790,  'stock' => 33,  'featured' => false, 'description' => 'Multi-function rice cooker with warm-hold and steam basket.'],
            ['name' => 'Boho Macrame Wall Hanging',         'slug' => 'boho-macrame-wall-hanging',        'category' => 'home-decor',        'brand' => 'aarong', 'price' => 1490,  'compare_price' => 1990,  'stock' => 40,  'featured' => false, 'description' => 'Handcrafted boho macrame wall hanging in natural cotton rope.'],
            ['name' => 'Egyptian Cotton Bedsheet Set',      'slug' => 'egyptian-cotton-bedsheet-set',     'category' => 'bedding-bath',      'brand' => 'hm',    'price' => 3990,  'compare_price' => 5200,  'stock' => 25,  'featured' => true,  'description' => '400 thread count Egyptian cotton king bedsheet set with pillowcases.'],
            // Mother & Kids
            ['name' => "Boys' Graphic Tee & Shorts Set",   'slug' => 'boys-graphic-tee-shorts-set',      'category' => 'boys-clothing',     'brand' => 'nike',  'price' => 1490,  'compare_price' => 1990,  'stock' => 90,  'featured' => false, 'description' => 'Fun graphic print t-shirt and matching shorts set for boys ages 4-12.'],
            ['name' => "Girls' Unicorn Hoodie Dress",       'slug' => 'girls-unicorn-hoodie-dress',       'category' => 'girls-clothing',    'brand' => 'hm',    'price' => 1690,  'compare_price' => 2190,  'stock' => 65,  'featured' => false, 'description' => 'Adorable unicorn hoodie dress with rainbow pockets and plush lining. Ages 3-10.'],
            ['name' => 'Building Blocks Set 100 Pieces',   'slug' => 'building-blocks-set-100-pieces',   'category' => 'toys-hobbies',      'brand' => 'xiaomi', 'price' => 1990,  'compare_price' => 2690,  'stock' => 80,  'featured' => false, 'description' => 'Colorful 100-piece building block set in a sturdy storage box.'],
            // Sports
            ['name' => 'Dri-Fit Training Tank Top',        'slug' => 'dri-fit-training-tank-top',        'category' => 'activewear',        'brand' => 'nike',  'price' => 1490,  'compare_price' => 1990,  'stock' => 100, 'featured' => false, 'description' => 'Moisture-wicking Dri-Fit tank top with mesh ventilation panels.'],
            ['name' => 'Adjustable Dumbbell Set 5–25kg',   'slug' => 'adjustable-dumbbell-set-5-25kg',   'category' => 'gym-equipment',     'brand' => 'puma',  'price' => 8990,  'compare_price' => 12000, 'stock' => 22,  'featured' => true,  'description' => 'Space-saving adjustable dumbbell set replacing 9 pairs.'],
            ['name' => 'Trail Running Shoes Waterproof',   'slug' => 'trail-running-shoes-waterproof',   'category' => 'sports-shoes',      'brand' => 'adidas', 'price' => 6490,  'compare_price' => 8990,  'stock' => 40,  'featured' => false, 'description' => 'Waterproof trail running shoes with aggressive rubber outsole.'],
        ];

        foreach ($products as $data) {
            $category = $categories[$data['category']] ?? null;
            $brand = $brands[$data['brand']] ?? null;

            if (! $category) {
                continue;
            }

            $productData = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sku' => strtoupper(Str::random(3)).'-'.random_int(1000, 9999),
                'description' => $data['description'],
                'short_description' => $data['description'],
                'brand_id' => $brand?->id,
                'price' => $data['price'],
                'compare_price' => $data['compare_price'],
                'stock_quantity' => $data['stock'],
                'low_stock_threshold' => (int) max(3, round($data['stock'] * 0.1)),
                'is_active' => true,
                'is_featured' => $data['featured'],
                'is_digital' => false,
                'weight' => round(0.3 + lcg_value() * 2.2, 2),
                'meta_title' => $data['name'].' | ShoppyDrive',
                'meta_description' => $data['description'],
                'published_at' => now()->subDays(random_int(1, 90)),
            ];

            if ($category instanceof SubCategory) {
                $productData['sub_category_id'] = $category->id;
                $productData['category_id'] = $category->category_id;
            } else {
                $productData['category_id'] = $category->id;
            }

            $product = Product::create($productData);

            // One placeholder image per product
            ProductMedia::create([
                'product_id' => $product->id,
                'path' => self::PLACEHOLDER,
                'type' => 'image',
                'alt' => $product->name,
                'sort_order' => 1,
                'is_primary' => true,
            ]);
        }
    }
}
