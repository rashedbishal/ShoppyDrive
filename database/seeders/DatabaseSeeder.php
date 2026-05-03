<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->seedSettings();
        $categories = $this->seedCategories();
        $brands = $this->seedBrands();
        $this->seedProducts($categories, $brands);
    }

    private function seedSettings(): void
    {
        Setting::upsert([
            ['key' => 'site_name', 'value' => json_encode('ShoppyDrive'), 'group' => 'general'],
            ['key' => 'currency_symbol', 'value' => json_encode('৳'), 'group' => 'general'],
            ['key' => 'contact_email', 'value' => json_encode('support@shoppydrive.com'), 'group' => 'general'],
            ['key' => 'contact_phone', 'value' => json_encode('+880 1700-000000'), 'group' => 'general'],
            ['key' => 'social_links', 'value' => json_encode([
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
                'image' => 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?auto=format&fit=crop&w=800&q=80',
                'description' => "Complete men's wardrobe from casual to formal.",
                'children' => [
                    ['name' => 'T-Shirts & Polos', 'slug' => 't-shirts-polos', 'image' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Shirts (Casual & Formal)', 'slug' => 'mens-shirts', 'image' => 'https://images.unsplash.com/photo-1596755389378-c31d21fd1273?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Jeans & Trousers', 'slug' => 'jeans-trousers', 'image' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Hoodies & Sweatshirts', 'slug' => 'mens-hoodies', 'image' => 'https://images.unsplash.com/photo-1556821840-3a63f15732ce?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Jackets & Coats', 'slug' => 'jackets-coats', 'image' => 'https://images.unsplash.com/photo-1520975954732-35dd22299614?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Innerwear & Sleepwear', 'slug' => 'mens-innerwear', 'image' => 'https://images.unsplash.com/photo-1574180566232-aaad1b5b8450?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Traditional Wear', 'slug' => 'mens-traditional', 'image' => 'https://images.unsplash.com/photo-1583391733956-6c78276477e2?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => "Women's Clothing",
                'slug' => 'womens-clothing',
                'image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=800&q=80',
                'description' => "Elegant women's fashion for every occasion.",
                'children' => [
                    ['name' => 'Dresses', 'slug' => 'dresses', 'image' => 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Tops, Tees & Blouses', 'slug' => 'tops-tees-blouses', 'image' => 'https://images.unsplash.com/photo-1551163943-3f7253a8d7e4?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Jeans, Pants & Skirts', 'slug' => 'womens-jeans-pants', 'image' => 'https://images.unsplash.com/photo-1594938298603-c8148c4b984b?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Hoodies & Sweaters', 'slug' => 'womens-hoodies', 'image' => 'https://images.unsplash.com/photo-1542295669297-4d352b042bca?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Traditional Wear', 'slug' => 'womens-traditional', 'image' => 'https://images.unsplash.com/photo-1614252369475-531eba1372f3?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Lingerie & Sleepwear', 'slug' => 'womens-lingerie', 'image' => 'https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Shoes',
                'slug' => 'shoes',
                'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80',
                'description' => 'Footwear for every style and occasion.',
                'children' => [
                    ['name' => "Men's Sneakers & Sports Shoes", 'slug' => 'mens-sneakers', 'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80'],
                    ['name' => "Men's Formal & Casual Shoes", 'slug' => 'mens-formal-shoes', 'image' => 'https://images.unsplash.com/photo-1533867617858-e7b97e060509?auto=format&fit=crop&w=800&q=80'],
                    ['name' => "Women's Sneakers", 'slug' => 'womens-sneakers', 'image' => 'https://images.unsplash.com/photo-1560769629-975ec94e6a86?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Heels & Flats', 'slug' => 'heels-flats', 'image' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Sandals & Slippers', 'slug' => 'sandals-slippers', 'image' => 'https://images.unsplash.com/photo-1603487742131-4160ec999306?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Boots', 'slug' => 'boots', 'image' => 'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Bags & Luggage',
                'slug' => 'bags-luggage',
                'image' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=800&q=80',
                'description' => 'Bags, wallets and travel essentials.',
                'children' => [
                    ['name' => 'Backpacks', 'slug' => 'backpacks', 'image' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=800&q=80'],
                    ['name' => "Women's Handbags & Crossbody Bags", 'slug' => 'handbags', 'image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Wallets & Purses', 'slug' => 'wallets-purses', 'image' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Travel Bags & Luggage', 'slug' => 'travel-bags', 'image' => 'https://images.unsplash.com/photo-1565026057447-bc90a3dceb87?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Laptop Bags & Briefcases', 'slug' => 'laptop-bags', 'image' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Jewelry & Accessories',
                'slug' => 'jewelry-accessories',
                'image' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=800&q=80',
                'description' => 'Jewelry, eyewear and fashion accessories.',
                'children' => [
                    ['name' => 'Necklaces & Pendants', 'slug' => 'necklaces', 'image' => 'https://images.unsplash.com/photo-1611085583191-a3b181a88401?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Earrings & Rings', 'slug' => 'earrings-rings', 'image' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Bracelets & Bangles', 'slug' => 'bracelets-bangles', 'image' => 'https://images.unsplash.com/photo-1573408301185-9519f94816b5?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Sunglasses & Eyewear', 'slug' => 'sunglasses', 'image' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Belts, Ties & Hats', 'slug' => 'belts-ties-hats', 'image' => 'https://images.unsplash.com/photo-1625625276248-8abd5bc7c73f?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Watches',
                'slug' => 'watches',
                'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80',
                'description' => 'Premium watches for every wrist.',
                'children' => [
                    ['name' => "Men's Watches", 'slug' => 'mens-watches', 'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80'],
                    ['name' => "Women's Watches", 'slug' => 'womens-watches', 'image' => 'https://images.unsplash.com/photo-1551816230-ef5deaed4a26?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Smart Watches', 'slug' => 'smart-watches', 'image' => 'https://images.unsplash.com/photo-1579586337278-3befd40fd17a?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Couple Watches', 'slug' => 'couple-watches', 'image' => 'https://images.unsplash.com/photo-1587836374828-4dbafa94cf0e?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Electronics & Gadgets',
                'slug' => 'electronics',
                'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80',
                'description' => 'Gadgets, accessories and smart tech.',
                'children' => [
                    ['name' => 'Earbuds, Headphones & Speakers', 'slug' => 'audio', 'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Mobile Phone Accessories', 'slug' => 'phone-accessories', 'image' => 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Power Banks & Chargers', 'slug' => 'power-banks', 'image' => 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Smart Home Devices', 'slug' => 'smart-home', 'image' => 'https://images.unsplash.com/photo-1558002038-1055907df827?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Computer Accessories', 'slug' => 'computer-accessories', 'image' => 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Beauty & Health',
                'slug' => 'beauty-health',
                'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=800&q=80',
                'description' => 'Skincare, makeup, grooming and wellness.',
                'children' => [
                    ['name' => 'Makeup & Cosmetics', 'slug' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Skincare Products', 'slug' => 'skincare', 'image' => 'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Haircare & Styling', 'slug' => 'haircare', 'image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Fragrances & Perfumes', 'slug' => 'fragrances', 'image' => 'https://images.unsplash.com/photo-1541643600914-78b084683702?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Grooming Tools', 'slug' => 'grooming', 'image' => 'https://images.unsplash.com/photo-1621607512214-68297480165e?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'image' => 'https://images.unsplash.com/photo-1484101403633-562f891dc89a?auto=format&fit=crop&w=800&q=80',
                'description' => 'Decor, kitchen, bedding and home improvement.',
                'children' => [
                    ['name' => 'Home Decor', 'slug' => 'home-decor', 'image' => 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Kitchen & Dining', 'slug' => 'kitchen-dining', 'image' => 'https://images.unsplash.com/photo-1556909172-5f7b3f2e6b1c?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Bedding & Bath', 'slug' => 'bedding-bath', 'image' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Storage & Organization', 'slug' => 'storage', 'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Tools & Home Improvement', 'slug' => 'tools', 'image' => 'https://images.unsplash.com/photo-1581622634843-f5e24ea8cde4?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Mother & Kids',
                'slug' => 'mother-kids',
                'image' => 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=800&q=80',
                'description' => 'Baby, kids clothing, toys and maternity care.',
                'children' => [
                    ['name' => "Boys' Clothing", 'slug' => 'boys-clothing', 'image' => 'https://images.unsplash.com/photo-1519238263530-99bdd11df2ea?auto=format&fit=crop&w=800&q=80'],
                    ['name' => "Girls' Clothing", 'slug' => 'girls-clothing', 'image' => 'https://images.unsplash.com/photo-1476234251651-f353703a034d?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Baby Clothes & Shoes', 'slug' => 'baby-clothes', 'image' => 'https://images.unsplash.com/photo-1522771930-78848d9293e8?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Toys & Hobbies', 'slug' => 'toys-hobbies', 'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Maternity Wear & Care', 'slug' => 'maternity', 'image' => 'https://images.unsplash.com/photo-1555252333-9f8e92e65df9?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
            [
                'name' => 'Sports & Outdoors',
                'slug' => 'sports-outdoors',
                'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=800&q=80',
                'description' => 'Activewear, sports equipment and outdoor gear.',
                'children' => [
                    ['name' => 'Activewear / Gym Wear', 'slug' => 'activewear', 'image' => 'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Sports Shoes', 'slug' => 'sports-shoes', 'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Fitness & Gym Equipment', 'slug' => 'gym-equipment', 'image' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=800&q=80'],
                    ['name' => 'Outdoor Gear', 'slug' => 'outdoor-gear', 'image' => 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=800&q=80'],
                ],
            ],
        ];

        $created = [];
        foreach ($tree as $sort => $data) {
            $parent = Category::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'parent_id' => null,
                'image' => $data['image'],
                'description' => $data['description'] ?? $data['name'].' products.',
                'meta_title' => $data['name'].' | ShoppyDrive',
                'meta_description' => $data['description'] ?? '',
                'is_active' => true,
                'sort_order' => $sort + 1,
            ]);

            $created[$data['slug']] = $parent;

            foreach ($data['children'] as $childSort => $child) {
                $childModel = Category::create([
                    'name' => $child['name'],
                    'slug' => $child['slug'],
                    'parent_id' => $parent->id,
                    'image' => $child['image'],
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
            ['name' => 'Samsung', 'slug' => 'samsung', 'description' => 'World-class electronics and smart devices'],
            ['name' => 'Anker', 'slug' => 'anker', 'description' => 'Reliable audio and charging accessories'],
            ['name' => 'Aarong', 'slug' => 'aarong', 'description' => "Bangladesh's leading fashion brand"],
            ['name' => 'Walton', 'slug' => 'walton', 'description' => 'Homegrown appliances and electronics'],
            ['name' => 'Bata', 'slug' => 'bata', 'description' => 'Trusted everyday footwear for all ages'],
            ['name' => 'CeraVe', 'slug' => 'cerave', 'description' => 'Dermatologist-recommended skincare'],
            ['name' => 'Nike', 'slug' => 'nike', 'description' => 'Performance sportswear and footwear'],
            ['name' => 'Adidas', 'slug' => 'adidas', 'description' => 'Sports and lifestyle apparel'],
            ['name' => 'Apple', 'slug' => 'apple', 'description' => 'Premium devices and accessories'],
            ['name' => 'Xiaomi', 'slug' => 'xiaomi', 'description' => 'Affordable smart gadgets and electronics'],
            ['name' => 'H&M', 'slug' => 'hm', 'description' => 'Fast fashion for men and women'],
            ['name' => 'Levi\'s', 'slug' => 'levis', 'description' => 'Iconic denim and casual wear'],
            ['name' => 'Fossil', 'slug' => 'fossil', 'description' => 'Classic watches and accessories'],
            ['name' => 'Puma', 'slug' => 'puma', 'description' => 'Athletic gear and urban style'],
            ['name' => 'Titan', 'slug' => 'titan', 'description' => 'Premium watch collection'],
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
            [
                'name' => 'Classic Slim Fit Polo T-Shirt',
                'slug' => 'classic-slim-fit-polo-t-shirt',
                'category' => 't-shirts-polos',
                'brand' => 'hm',
                'price' => 1290,
                'compare_price' => 1790,
                'stock' => 85,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1503341455253-b2e723bb3dbb?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Breathable cotton polo with a slim silhouette. Perfect for casual Fridays and weekend outings.',
            ],
            [
                'name' => 'Oxford Formal Shirt White',
                'slug' => 'oxford-formal-shirt-white',
                'category' => 'mens-shirts',
                'brand' => 'aarong',
                'price' => 1890,
                'compare_price' => 2490,
                'stock' => 60,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1596755389378-c31d21fd1273?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1603252109303-2751441dd157?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Crisp white Oxford shirt crafted from premium cotton. A wardrobe staple for every occasion.',
            ],
            [
                'name' => 'Slim Fit Stretch Jeans Dark Blue',
                'slug' => 'slim-fit-stretch-jeans-dark-blue',
                'category' => 'jeans-trousers',
                'brand' => 'levis',
                'price' => 3290,
                'compare_price' => 4200,
                'stock' => 72,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1598554747436-c9293d6a588f?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => "Slim fit jeans with 2% stretch elastane for all-day comfort. A Levi's classic redefined.",
            ],
            [
                'name' => 'Pullover Fleece Hoodie Navy',
                'slug' => 'pullover-fleece-hoodie-navy',
                'category' => 'mens-hoodies',
                'brand' => 'nike',
                'price' => 2990,
                'compare_price' => 3990,
                'stock' => 45,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1556821840-3a63f15732ce?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1578587018452-892bacefd3f2?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Soft fleece pullover hoodie with a spacious kangaroo pocket. Ideal for cool mornings and gym warm-ups.',
            ],
            [
                'name' => 'Premium Linen Panjabi Cream',
                'slug' => 'premium-linen-panjabi-cream',
                'category' => 'mens-traditional',
                'brand' => 'aarong',
                'price' => 3290,
                'compare_price' => 3890,
                'stock' => 58,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1583391733956-6c78276477e2?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1583846712148-b61ecca50c50?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Breathable linen panjabi with embroidered collar detail. Perfect for Eid, weddings and cultural events.',
            ],
            // Women's Clothing
            [
                'name' => 'Floral Midi Dress Printed',
                'slug' => 'floral-midi-dress-printed',
                'category' => 'dresses',
                'brand' => 'hm',
                'price' => 2490,
                'compare_price' => 3290,
                'stock' => 40,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Lightweight floral midi dress in a flattering A-line cut. Versatile for both day and evening wear.',
            ],
            [
                'name' => 'Heritage Banarasi Silk Saree',
                'slug' => 'heritage-banarasi-silk-saree',
                'category' => 'womens-traditional',
                'brand' => 'aarong',
                'price' => 7990,
                'compare_price' => 9990,
                'stock' => 24,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1614252369475-531eba1372f3?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1611042553365-9b101441c135?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Exquisite Banarasi silk saree with intricate zari work. A festive essential for every wardrobe.',
            ],
            [
                'name' => 'Casual Crop Top Ribbed',
                'slug' => 'casual-crop-top-ribbed',
                'category' => 'tops-tees-blouses',
                'brand' => 'hm',
                'price' => 990,
                'compare_price' => 1390,
                'stock' => 95,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1551163943-3f7253a8d7e4?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1564257631407-4deb1f99d992?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Stretchy ribbed crop top in a relaxed fit. Pairs beautifully with high-waist jeans or skirts.',
            ],
            // Shoes
            [
                'name' => 'Air Cushion Running Sneakers',
                'slug' => 'air-cushion-running-sneakers',
                'category' => 'mens-sneakers',
                'brand' => 'nike',
                'price' => 7490,
                'compare_price' => 9990,
                'stock' => 35,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1520256862855-398228c41684?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Lightweight running sneakers with air-cushioned midsole. Designed for comfort on long runs or daily commutes.',
            ],
            [
                'name' => "Men's Oxford Leather Shoes",
                'slug' => 'mens-oxford-leather-shoes',
                'category' => 'mens-formal-shoes',
                'brand' => 'bata',
                'price' => 4990,
                'compare_price' => 6200,
                'stock' => 28,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1533867617858-e7b97e060509?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1614252235316-8c857196f400?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Classic full-grain leather Oxford with rubber sole. Polished finish suitable for office and formal events.',
            ],
            [
                'name' => "Women's Strappy Heeled Sandals",
                'slug' => 'womens-strappy-heeled-sandals',
                'category' => 'heels-flats',
                'brand' => 'bata',
                'price' => 2490,
                'compare_price' => 3290,
                'stock' => 50,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1623177970094-1eec94a5ea5e?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Elegant strappy heeled sandals with a cushioned footbed. Goes perfectly with dresses and formal outfits.',
            ],
            // Bags
            [
                'name' => 'Urban Laptop Backpack 30L',
                'slug' => 'urban-laptop-backpack-30l',
                'category' => 'backpacks',
                'brand' => 'nike',
                'price' => 4290,
                'compare_price' => 5490,
                'stock' => 62,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1622560480654-d96214fdc887?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Durable 30L backpack with a padded 15" laptop sleeve and multiple organizer pockets.',
            ],
            [
                'name' => 'Leather Tote Handbag Tan',
                'slug' => 'leather-tote-handbag-tan',
                'category' => 'handbags',
                'brand' => 'hm',
                'price' => 3490,
                'compare_price' => 4500,
                'stock' => 38,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Structured leather tote with a roomy interior and magnetic snap closure. A timeless everyday carry.',
            ],
            // Jewelry & Accessories
            [
                'name' => 'Gold Plated Layer Necklace Set',
                'slug' => 'gold-plated-layer-necklace-set',
                'category' => 'necklaces',
                'brand' => 'aarong',
                'price' => 1490,
                'compare_price' => 1990,
                'stock' => 75,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1611085583191-a3b181a88401?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Delicate 3-layer gold plated necklace set. Lightweight and hypoallergenic for daily wear.',
            ],
            [
                'name' => 'Polarized UV400 Aviator Sunglasses',
                'slug' => 'polarized-uv400-aviator-sunglasses',
                'category' => 'sunglasses',
                'brand' => 'hm',
                'price' => 1290,
                'compare_price' => 1890,
                'stock' => 88,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1572635196237-14b3f281503f?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Classic aviator sunglasses with polarized lenses and UV400 protection. Lightweight metal frame.',
            ],
            // Watches
            [
                'name' => "Men's Chronograph Stainless Watch",
                'slug' => 'mens-chronograph-stainless-watch',
                'category' => 'mens-watches',
                'brand' => 'fossil',
                'price' => 12990,
                'compare_price' => 16500,
                'stock' => 20,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1594534475808-b18fc33b045e?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Classic stainless steel chronograph with date window and sapphire-coated mineral glass.',
            ],
            [
                'name' => 'Samsung Galaxy Watch 6 Smart Watch',
                'slug' => 'samsung-galaxy-watch-6',
                'category' => 'smart-watches',
                'brand' => 'samsung',
                'price' => 28990,
                'compare_price' => 34990,
                'stock' => 15,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1579586337278-3befd40fd17a?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1544117519-31a4b719223d?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Advanced health tracking with ECG, blood pressure monitoring and a stunning AMOLED display.',
            ],
            [
                'name' => "Women's Rose Gold Slim Watch",
                'slug' => 'womens-rose-gold-slim-watch',
                'category' => 'womens-watches',
                'brand' => 'titan',
                'price' => 6990,
                'compare_price' => 8990,
                'stock' => 30,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1551816230-ef5deaed4a26?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1557531365-e8b22d93e1ce?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Slim rose gold watch with a mesh strap and minimalist dial. Elegant enough for any occasion.',
            ],
            // Electronics
            [
                'name' => 'AirGo Pro ANC Wireless Earbuds',
                'slug' => 'airgo-pro-anc-wireless-earbuds',
                'category' => 'audio',
                'brand' => 'anker',
                'price' => 8990,
                'compare_price' => 11990,
                'stock' => 42,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Compact ANC earbuds with 30h battery life, USB-C charging and rich bass profile.',
            ],
            [
                'name' => 'Xiaomi 10000mAh Power Bank',
                'slug' => 'xiaomi-10000mah-power-bank',
                'category' => 'power-banks',
                'brand' => 'xiaomi',
                'price' => 1890,
                'compare_price' => 2490,
                'stock' => 110,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Slim 10000mAh power bank with dual USB output and 18W fast charge. TSA-approved travel size.',
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra 5G',
                'slug' => 'samsung-galaxy-s24-ultra-5g',
                'category' => 'phone-accessories',
                'brand' => 'samsung',
                'price' => 134999,
                'compare_price' => 149999,
                'stock' => 16,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Flagship smartphone with 200MP camera, S Pen, 5000mAh battery and titanium frame.',
            ],
            [
                'name' => 'Mechanical RGB Gaming Keyboard',
                'slug' => 'mechanical-rgb-gaming-keyboard',
                'category' => 'computer-accessories',
                'brand' => 'xiaomi',
                'price' => 3490,
                'compare_price' => 4500,
                'stock' => 55,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1595044426077-d36d9236d54a?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Compact TKL mechanical keyboard with per-key RGB, clicky blue switches and braided USB cable.',
            ],
            // Beauty & Health
            [
                'name' => 'CeraVe Hydrating Skincare Duo',
                'slug' => 'cerave-hydrating-skincare-duo',
                'category' => 'skincare',
                'brand' => 'cerave',
                'price' => 2890,
                'compare_price' => 3590,
                'stock' => 70,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Gentle hydrating cleanser and moisturizer duo with ceramides and hyaluronic acid.',
            ],
            [
                'name' => 'Complete Makeup Starter Kit',
                'slug' => 'complete-makeup-starter-kit',
                'category' => 'makeup',
                'brand' => 'hm',
                'price' => 2290,
                'compare_price' => 3100,
                'stock' => 48,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'All-in-one makeup kit with foundation, blush, eyeshadow palette, mascara and lipstick.',
            ],
            [
                'name' => 'Argan Oil Hair Serum 100ml',
                'slug' => 'argan-oil-hair-serum-100ml',
                'category' => 'haircare',
                'brand' => 'cerave',
                'price' => 990,
                'compare_price' => 1390,
                'stock' => 120,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1519751138087-5bf79df62d5b?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Lightweight argan oil serum that tames frizz and adds brilliant shine without greasiness.',
            ],
            [
                'name' => 'Wireless Electric Beard Trimmer',
                'slug' => 'wireless-electric-beard-trimmer',
                'category' => 'grooming',
                'brand' => 'xiaomi',
                'price' => 1990,
                'compare_price' => 2790,
                'stock' => 65,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1621607512214-68297480165e?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Cordless beard trimmer with 40 length settings, self-sharpening blades and washable head.',
            ],
            // Home & Living
            [
                'name' => 'Compact Air Fryer 4L',
                'slug' => 'compact-air-fryer-4l',
                'category' => 'kitchen-dining',
                'brand' => 'walton',
                'price' => 7990,
                'compare_price' => 9990,
                'stock' => 19,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1556909172-5f7b3f2e6b1c?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1644093549994-3dc2a7a44f5b?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Countertop air fryer with 7 preset programs, rapid air circulation and non-stick basket.',
            ],
            [
                'name' => 'Smart Rice Cooker 1.8L',
                'slug' => 'smart-rice-cooker-1-8l',
                'category' => 'kitchen-dining',
                'brand' => 'walton',
                'price' => 5290,
                'compare_price' => 6790,
                'stock' => 33,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1491203659242-5355db3a50e4?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Multi-function rice cooker with warm-hold, steam basket and non-stick inner pot.',
            ],
            [
                'name' => 'Boho Macrame Wall Hanging',
                'slug' => 'boho-macrame-wall-hanging',
                'category' => 'home-decor',
                'brand' => 'aarong',
                'price' => 1490,
                'compare_price' => 1990,
                'stock' => 40,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1615529162924-f8605388461d?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Handcrafted boho macrame wall hanging in natural cotton rope. Adds texture and warmth to any wall.',
            ],
            [
                'name' => 'Egyptian Cotton Bedsheet Set',
                'slug' => 'egyptian-cotton-bedsheet-set',
                'category' => 'bedding-bath',
                'brand' => 'hm',
                'price' => 3990,
                'compare_price' => 5200,
                'stock' => 25,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => '400 thread count Egyptian cotton king bedsheet set with 2 pillowcases and fitted sheet.',
            ],
            // Mother & Kids
            [
                'name' => "Boys' Graphic Tee & Shorts Set",
                'slug' => 'boys-graphic-tee-shorts-set',
                'category' => 'boys-clothing',
                'brand' => 'nike',
                'price' => 1490,
                'compare_price' => 1990,
                'stock' => 90,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1519238263530-99bdd11df2ea?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1503944583220-79d8926ad5e2?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Fun graphic print t-shirt and matching shorts set for boys ages 4-12. Soft cotton with elastic waist.',
            ],
            [
                'name' => "Girls' Unicorn Hoodie Dress",
                'slug' => 'girls-unicorn-hoodie-dress',
                'category' => 'girls-clothing',
                'brand' => 'hm',
                'price' => 1690,
                'compare_price' => 2190,
                'stock' => 65,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1476234251651-f353703a034d?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1518831959646-742c3a14ebf7?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Adorable unicorn hoodie dress with rainbow pockets and soft plush lining. Ages 3-10.',
            ],
            [
                'name' => 'Building Blocks Set 100 Pieces',
                'slug' => 'building-blocks-set-100-pieces',
                'category' => 'toys-hobbies',
                'brand' => 'xiaomi',
                'price' => 1990,
                'compare_price' => 2690,
                'stock' => 80,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1545558014-8692077e9b5c?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Colorful 100-piece building block set in a sturdy storage box. Stimulates creativity for ages 2+.',
            ],
            // Sports & Outdoors
            [
                'name' => 'Dri-Fit Training Tank Top',
                'slug' => 'dri-fit-training-tank-top',
                'category' => 'activewear',
                'brand' => 'nike',
                'price' => 1490,
                'compare_price' => 1990,
                'stock' => 100,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Moisture-wicking Dri-Fit tank top with mesh ventilation panels. Ideal for gym and outdoor training.',
            ],
            [
                'name' => 'Adjustable Dumbbell Set 5–25kg',
                'slug' => 'adjustable-dumbbell-set-5-25kg',
                'category' => 'gym-equipment',
                'brand' => 'puma',
                'price' => 8990,
                'compare_price' => 12000,
                'stock' => 22,
                'featured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1574680096145-d05b474e2155?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Space-saving adjustable dumbbell set replacing 9 pairs. Quick-adjust dial from 5 to 25kg.',
            ],
            [
                'name' => 'Trail Running Shoes Waterproof',
                'slug' => 'trail-running-shoes-waterproof',
                'category' => 'sports-shoes',
                'brand' => 'adidas',
                'price' => 6490,
                'compare_price' => 8990,
                'stock' => 40,
                'featured' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=800&q=80',
                ],
                'description' => 'Waterproof trail running shoes with aggressive rubber outsole and cushioned midsole for rough terrain.',
            ],
        ];

        foreach ($products as $data) {
            $category = $categories[$data['category']] ?? null;
            $brand = $brands[$data['brand']] ?? null;

            if (! $category) {
                continue;
            }

            $product = Product::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sku' => strtoupper(Str::random(3)).'-'.random_int(1000, 9999),
                'description' => $data['description'],
                'short_description' => $data['description'],
                'category_id' => $category->id,
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
            ]);

            foreach ($data['images'] as $index => $imageUrl) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $imageUrl,
                    'alt' => $product->name,
                    'sort_order' => $index + 1,
                    'is_primary' => $index === 0,
                ]);
            }
        }
    }
}
