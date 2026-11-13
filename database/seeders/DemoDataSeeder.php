<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\BlockedIp;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\HeroSlide;
use App\Models\IncompleteOrder;
use App\Models\LandingPage;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Page;
use App\Models\Payment;
use App\Models\PopularKeyword;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\PromotionalBanner;
use App\Models\ReceivedSms;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SmsPattern;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Additive demo data for exercising every admin list tab (pagination,
 * filters, long forms). Unlike DatabaseSeeder it never truncates anything:
 * every row is keyed on a "demo" slug/code/email/prefix and created with
 * firstOrCreate()/updateOrCreate(), so it is safe to re-run and leaves
 * existing data untouched.
 *
 * Run with: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const PLACEHOLDER = '/placeholder.svg';

    /** Rows per tab (at least 40 each; a few tabs use their own count below). */
    private const COUNT = 45;

    private const COLORS = ['Red' => '#DC2626', 'Blue' => '#2563EB', 'Black' => '#111827', 'White' => '#F9FAFB', 'Green' => '#16A34A'];

    private const SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Refusing to seed demo data in production.');
        }

        fake()->seed(20261113);

        // Orders fire placed-order mails and Meta events from their created
        // hook, and every Auditable model would flood the audit trail, so
        // both are switched off for the whole run.
        activity()->withoutLogs(fn () => Model::unguarded(function (): void {
            $this->seedAttributes();
            $this->seedCategories();
            $this->seedBrands();
            $products = $this->seedProducts();
            $customers = $this->seedCustomers();
            $this->seedCoupons();
            $this->seedFlashSales($products);
            $this->seedBundles($products);
            $orders = Order::withoutEvents(fn () => $this->seedOrders($customers, $products));
            $this->seedIncompleteOrders($products);
            $this->seedReturns();
            $this->seedReviews($customers, $products);
            $this->seedContactMessages();
            $this->seedNewsletterSubscribers();
            $this->seedBlockedIps();
            $this->seedSmsPatterns();
            $this->seedReceivedSms($orders);
            $this->seedHeroSlides();
            $this->seedPromotionalBanners();
            $this->seedLandingPages($products);
            $this->seedBlogs();
            $this->seedPages();
            $this->seedPopularKeywords();
        }));
    }

    private function key(int $number): string
    {
        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }

    private function seedAttributes(): void
    {
        $color = Attribute::firstOrCreate(['name' => 'Color'], ['type' => 'color', 'has_images' => true]);
        $size = Attribute::firstOrCreate(['name' => 'Size'], ['type' => 'select', 'has_images' => false]);

        $sort = 1;
        foreach (self::COLORS as $name => $hex) {
            AttributeValue::firstOrCreate(
                ['attribute_id' => $color->id, 'value' => $name],
                ['color_hex' => $hex, 'sort_order' => $sort++],
            );
        }

        foreach (self::SIZES as $index => $name) {
            AttributeValue::firstOrCreate(
                ['attribute_id' => $size->id, 'value' => $name],
                ['sort_order' => $index + 1],
            );
        }

        for ($i = 1; $i <= self::COUNT; $i++) {
            $attribute = Attribute::firstOrCreate(
                ['name' => 'Demo Attribute '.$this->key($i)],
                ['type' => 'select', 'has_images' => false],
            );

            foreach (['Option A', 'Option B'] as $index => $value) {
                AttributeValue::firstOrCreate(
                    ['attribute_id' => $attribute->id, 'value' => $value],
                    ['sort_order' => $index + 1],
                );
            }
        }
    }

    private function seedCategories(): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $name = 'Demo Category '.$this->key($i);

            Category::firstOrCreate(['slug' => 'demo-category-'.$this->key($i)], [
                'name' => $name,
                'image' => self::PLACEHOLDER,
                'description' => $name.' — demo data.',
                'meta_title' => $name,
                'meta_description' => $name.' products.',
                'is_active' => $i % 7 !== 0,
                'sort_order' => 100 + $i,
            ]);
        }
    }

    private function seedBrands(): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            Brand::firstOrCreate(['slug' => 'demo-brand-'.$this->key($i)], [
                'name' => 'Demo Brand '.$this->key($i),
                'description' => 'Demo brand '.$i.' for admin list testing.',
                'is_active' => $i % 8 !== 0,
            ]);
        }
    }

    /**
     * @return Collection<int, Product>
     */
    private function seedProducts(): Collection
    {
        $subCategories = SubCategory::orderBy('id')->get();
        $brands = Brand::orderBy('id')->get();
        $color = Attribute::where('name', 'Color')->firstOrFail();
        $size = Attribute::where('name', 'Size')->firstOrFail();
        $colorValues = AttributeValue::where('attribute_id', $color->id)->orderBy('sort_order')->get();
        $sizeValues = AttributeValue::where('attribute_id', $size->id)->orderBy('sort_order')->get();

        if ($subCategories->isEmpty()) {
            throw new RuntimeException('No sub-categories found: run the base catalogue seeder first.');
        }

        $products = new Collection;

        for ($i = 1; $i <= self::COUNT; $i++) {
            $key = $this->key($i);
            $subCategory = $subCategories[($i * 3) % $subCategories->count()];
            $price = fake()->numberBetween(30, 1500) * 10;
            $hasVariants = $i % 4 === 0;

            $product = Product::withTrashed()->where('slug', 'demo-product-'.$key)->first() ?? Product::create([
                'name' => 'Demo Product '.$key.' '.ucfirst(fake()->word()),
                'slug' => 'demo-product-'.$key,
                'sku' => 'DEMO-P-'.$key,
                'description' => fake()->paragraph(4),
                'short_description' => fake()->sentence(),
                'category_id' => $subCategory->category_id,
                'sub_category_id' => $subCategory->id,
                'brand_id' => $brands[$i % $brands->count()]->id,
                'price' => $price,
                'compare_price' => $i % 3 === 0 ? null : $price + fake()->numberBetween(10, 40) * 10,
                'cost_price' => (int) round($price * 0.6),
                'stock_quantity' => match (true) {
                    $i % 11 === 0 => 0,
                    $i % 5 === 0 => fake()->numberBetween(1, 4),
                    default => fake()->numberBetween(20, 200),
                },
                'low_stock_threshold' => 5,
                'is_active' => $i % 9 !== 0,
                'is_featured' => $i % 6 === 0,
                'is_digital' => false,
                'has_color_variant' => $hasVariants,
                'has_size_variant' => $hasVariants,
                'variant_attribute_ids' => $hasVariants ? [$color->id, $size->id] : null,
                'weight' => fake()->randomFloat(2, 0.2, 3),
                'meta_title' => 'Demo Product '.$key,
                'published_at' => now()->subDays(fake()->numberBetween(1, 90)),
            ]);

            if ($product->wasRecentlyCreated) {
                ProductMedia::create([
                    'product_id' => $product->id,
                    'path' => self::PLACEHOLDER,
                    'type' => 'image',
                    'alt' => $product->name,
                    'sort_order' => 1,
                    'is_primary' => true,
                ]);

                if ($hasVariants) {
                    $this->seedVariants($product, $colorValues->take(2), $sizeValues->take(3));
                }
            }

            $products->push($product);
        }

        return $products;
    }

    /**
     * @param  Collection<int, AttributeValue>  $colors
     * @param  Collection<int, AttributeValue>  $sizes
     */
    private function seedVariants(Product $product, Collection $colors, Collection $sizes): void
    {
        $isFirst = true;

        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $product->sku.'-'.strtoupper(substr($color->value, 0, 2)).'-'.$size->value,
                    'price' => $product->price + ($sizes->search($size) * 50),
                    'cost_price' => $product->cost_price,
                    'stock_quantity' => fake()->numberBetween(0, 60),
                    'low_stock_threshold' => 5,
                    'attributes' => ['Color' => $color->value, 'Size' => $size->value],
                    'is_default' => $isFirst,
                    'color_value_id' => $color->id,
                    'size_value_id' => $size->id,
                    'attribute_value_ids' => [$color->id, $size->id],
                ]);

                $isFirst = false;
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function seedCustomers(): Collection
    {
        $password = Hash::make('password');
        $customers = new Collection;

        for ($i = 1; $i <= self::COUNT; $i++) {
            $customers->push(User::firstOrCreate(
                ['email' => 'demo.customer'.$this->key($i).'@example.test'],
                [
                    'name' => fake()->name(),
                    'phone' => '01'.fake()->numberBetween(3, 9).fake()->numerify('########'),
                    'password' => $password,
                    'email_verified_at' => $i % 10 === 0 ? null : now()->subDays(fake()->numberBetween(1, 120)),
                    'role' => 'customer',
                    'created_at' => now()->subDays(fake()->numberBetween(1, 120)),
                ],
            ));
        }

        return $customers;
    }

    private function seedCoupons(): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $isPercent = $i % 2 === 1;
            [$startsAt, $expiresAt] = match ($i % 3) {
                0 => [now()->subDays(60), now()->subDays(5)],
                1 => [now()->subDays(5), now()->addDays(30)],
                default => [now()->addDays(3), now()->addDays(40)],
            };

            Coupon::withTrashed()->firstOrCreate(['code' => 'DEMO'.$this->key($i)], [
                'name' => 'Demo Coupon '.$this->key($i),
                'type' => $isPercent ? 'percent' : 'fixed',
                'value' => $isPercent ? fake()->numberBetween(5, 30) : fake()->numberBetween(5, 50) * 10,
                'applies_to' => 'all',
                'min_order_amount' => $i % 4 === 0 ? 0 : fake()->numberBetween(5, 20) * 100,
                'max_uses' => $i % 5 === 0 ? null : 100,
                'used_count' => 0,
                'per_user_limit' => 1,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'is_active' => $i % 10 !== 0,
            ]);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedFlashSales(Collection $products): void
    {
        $count = $products->count();

        for ($i = 1; $i <= self::COUNT; $i++) {
            [$startsAt, $endsAt] = match ($i % 3) {
                0 => [now()->subDays(10), now()->subDays(3)],
                1 => [now()->subHours(6), now()->addDays(2)],
                default => [now()->addDays(2), now()->addDays(5)],
            };

            $sale = FlashSale::firstOrCreate(['name' => 'Demo Flash Sale '.$this->key($i)], [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_active' => $i % 10 !== 0,
            ]);

            if (! $sale->wasRecentlyCreated) {
                continue;
            }

            foreach (range(0, 1 + $i % 3) as $offset) {
                $this->createFlashSaleItem($sale, $products[($i + $offset * 7) % $count]);
            }
        }

        // The "infinite adding" fixture: one long campaign (start in the
        // future so it never touches live storefront pricing) holding 40
        // products, so the edit form's product list can be tested at length.
        $mega = FlashSale::firstOrCreate(['name' => 'Demo Mega Flash Sale (40 products)'], [
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(37),
            'is_active' => true,
        ]);

        if ($mega->wasRecentlyCreated) {
            foreach ($products->take(40) as $product) {
                $this->createFlashSaleItem($mega, $product);
            }
        }
    }

    private function createFlashSaleItem(FlashSale $sale, Product $product): void
    {
        FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'sale_price' => round($product->price * fake()->randomFloat(2, 0.6, 0.9), 2),
            'stock_limit' => fake()->boolean(50) ? fake()->numberBetween(10, 60) : null,
            'sold_count' => 0,
        ]);
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedBundles(Collection $products): void
    {
        // Only products without variants: a bundle line for a variant
        // product needs a specific variant chosen.
        $simple = $products->filter(fn (Product $product): bool => ! $product->has_color_variant)->values();

        for ($i = 1; $i <= 40; $i++) {
            $key = $this->key($i);
            $bundle = ProductBundle::firstOrCreate(['slug' => 'demo-bundle-'.$key], [
                'name' => 'Demo Bundle '.$key,
                'description' => 'Demo bundle '.$i.'.',
                'image' => self::PLACEHOLDER,
                'discount_type' => $i % 5 === 0 ? 'free_delivery' : 'fixed_price',
                'bundle_price' => null,
                'is_active' => $i % 9 !== 0,
                'sort_order' => $i,
            ]);

            if (! $bundle->wasRecentlyCreated) {
                continue;
            }

            $total = 0;
            foreach (range(0, 1 + $i % 2) as $offset) {
                $product = $simple[($i + $offset * 5) % $simple->count()];
                $quantity = 1 + ($offset % 2);
                $total += $product->price * $quantity;

                ProductBundleItem::create([
                    'product_bundle_id' => $bundle->id,
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => $quantity,
                ]);
            }

            if ($bundle->discount_type === 'fixed_price') {
                $bundle->update(['bundle_price' => round($total * 0.85, 2)]);
            }
        }
    }

    /**
     * 120 live orders over every status, plus 40 trashed ones.
     *
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Order>
     */
    private function seedOrders(Collection $customers, Collection $products): Collection
    {
        $lifecycle = [
            OrderStatus::PENDING, OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED,
            OrderStatus::DELIVERED, OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::REFUNDED,
        ];
        $methods = [PaymentMethod::COD, PaymentMethod::COD, PaymentMethod::MobileBanking, PaymentMethod::ZINIPAY];
        $sellable = $products->filter(fn (Product $product): bool => $product->is_active && ! $product->has_color_variant)->values();
        $orders = new Collection;

        for ($i = 1; $i <= 160; $i++) {
            $isTrashed = $i > 120;
            $suffix = 'D'.($isTrashed ? 'T' : 'M').str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $number = 'ORD-'.now()->subDays($i % 60)->format('Ymd').'-'.$suffix;

            $existing = Order::withTrashed()->where('order_number', 'like', 'ORD-%-'.$suffix)->first();
            if ($existing) {
                $orders->push($existing);

                continue;
            }

            $status = $lifecycle[$i % count($lifecycle)];
            $method = $methods[$i % count($methods)];
            $customer = $customers[$i % $customers->count()];
            $placedAt = now()->subDays($i % 60)->subMinutes($i * 7);

            $paymentStatus = match ($status) {
                OrderStatus::DELIVERED => PaymentStatus::PAID,
                OrderStatus::REFUNDED => PaymentStatus::REFUNDED,
                OrderStatus::CANCELLED => PaymentStatus::UNPAID,
                default => $method === PaymentMethod::COD ? PaymentStatus::UNPAID : PaymentStatus::PAID,
            };

            $lines = [];
            foreach (range(0, $i % 3) as $offset) {
                $product = $sellable[($i * 2 + $offset * 3) % $sellable->count()];
                $quantity = 1 + (($i + $offset) % 3);
                $lines[] = [$product, $quantity];
            }

            $subtotal = collect($lines)->sum(fn (array $line): float => $line[0]->price * $line[1]);
            $shipping = $i % 2 === 0 ? 60 : 120;
            $address = [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address_line1' => fake()->streetAddress(),
                'address_line2' => null,
                'city' => fake()->randomElement(['Dhaka', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna']),
                'state' => 'Dhaka',
                'postal_code' => (string) fake()->numberBetween(1000, 9999),
                'country' => 'Bangladesh',
            ];

            $order = Order::create([
                'order_number' => $number,
                'user_id' => $customer->id,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_method' => $method,
                'subtotal' => $subtotal,
                'shipping_cost' => $shipping,
                'discount' => 0,
                'tax' => 0,
                'total' => $subtotal + $shipping,
                'shipping_address' => $address,
                'billing_address' => $address,
                'cancellation_reason' => $status === OrderStatus::CANCELLED ? 'Customer changed their mind.' : null,
                'shipped_at' => in_array($status, [OrderStatus::SHIPPED, OrderStatus::DELIVERED], true) ? $placedAt->copy()->addDay() : null,
                'delivered_at' => $status === OrderStatus::DELIVERED ? $placedAt->copy()->addDays(3) : null,
                'is_seen' => $i % 4 !== 0,
                'ip_address' => '203.0.113.'.(($i % 250) + 1),
                'user_agent' => 'DemoDataSeeder',
                'traffic_source' => 'direct',
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
                'deleted_at' => $isTrashed ? $placedAt->copy()->addHours(2) : null,
            ]);

            foreach ($lines as [$product, $quantity]) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'quantity' => $quantity,
                    'subtotal' => $product->price * $quantity,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => $product->price,
                        'image' => self::PLACEHOLDER,
                        'variant_attributes' => null,
                    ],
                ]);
            }

            OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'pending', 'notes' => 'Order placed successfully.', 'created_at' => $placedAt, 'updated_at' => $placedAt]);

            if ($status !== OrderStatus::PENDING) {
                OrderStatusHistory::create(['order_id' => $order->id, 'status' => $status->value, 'notes' => 'Demo status: '.$status->value, 'created_at' => $placedAt->copy()->addDay(), 'updated_at' => $placedAt->copy()->addDay()]);
            }

            if ($method !== PaymentMethod::COD && $paymentStatus !== PaymentStatus::UNPAID) {
                Payment::create([
                    'order_id' => $order->id,
                    'method' => $method->value,
                    'amount' => $order->total,
                    'status' => $paymentStatus === PaymentStatus::REFUNDED ? 'refunded' : 'paid',
                    'transaction_id' => 'DEMO'.strtoupper(Str::random(8)),
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt,
                ]);
            }

            $orders->push($order);
        }

        return $orders;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedIncompleteOrders(Collection $products): void
    {
        $sellable = $products->filter(fn (Product $product): bool => $product->is_active && ! $product->has_color_variant)->values();

        for ($i = 1; $i <= 40; $i++) {
            $product = $sellable[$i % $sellable->count()];
            $quantity = 1 + $i % 3;
            $token = 'demo-attempt-'.$this->key($i);
            $at = now()->subDays($i % 14)->subMinutes($i * 13);

            IncompleteOrder::firstOrCreate(['attempt_token' => $token], [
                'name' => fake()->name(),
                'phone' => '01'.fake()->numberBetween(3, 9).fake()->numerify('########'),
                'items' => [[
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'price' => (float) $product->price,
                    'quantity' => $quantity,
                    'subtotal' => (float) $product->price * $quantity,
                    'image' => self::PLACEHOLDER,
                    'variant_attributes' => null,
                ]],
                'subtotal' => (float) $product->price * $quantity,
                'reminder_sent_at' => $i % 5 === 0 ? $at->copy()->addHours(3) : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    private function seedReturns(): void
    {
        $statuses = [ReturnStatus::PENDING, ReturnStatus::APPROVED, ReturnStatus::REJECTED, ReturnStatus::REFUNDED];
        $reasons = ['Wrong size', 'Item damaged on arrival', 'Not as described', 'Changed my mind', 'Received the wrong item'];

        $delivered = Order::where('order_number', 'like', 'ORD-%-DM%')
            ->where('status', OrderStatus::DELIVERED->value)
            ->with('items')
            ->orderBy('id')
            ->take(40)
            ->get();

        foreach ($delivered as $index => $order) {
            if (ReturnRequest::where('order_id', $order->id)->exists()) {
                continue;
            }

            $status = $statuses[$index % count($statuses)];
            $item = $order->items->first();
            $isResolved = in_array($status, [ReturnStatus::REJECTED, ReturnStatus::REFUNDED], true);

            ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'reason' => $reasons[$index % count($reasons)],
                'status' => $status,
                'items' => [[
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => 1,
                    'reason' => null,
                ]],
                'admin_note' => $isResolved ? 'Demo resolution note.' : null,
                'refund_amount' => $status === ReturnStatus::REFUNDED ? $item->price : null,
                'refund_method' => $status === ReturnStatus::REFUNDED ? 'cash' : null,
                'resolved_at' => $isResolved ? now()->subDays($index % 10) : null,
                'created_at' => now()->subDays(($index % 20) + 1),
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, Product>  $products
     */
    private function seedReviews(Collection $customers, Collection $products): void
    {
        $titles = ['Great quality', 'Worth the price', 'Not bad', 'Could be better', 'Loved it', 'Fast delivery', 'Disappointed'];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $customer = $customers[$i - 1];
            $product = $products[$i - 1];

            Review::withTrashed()->firstOrCreate(['product_id' => $product->id, 'user_id' => $customer->id], [
                'rating' => 1 + ($i * 3) % 5,
                'title' => $titles[$i % count($titles)],
                'body' => fake()->paragraph(2),
                'is_approved' => $i % 3 !== 0,
                'created_at' => now()->subDays($i % 45),
            ]);
        }
    }

    private function seedContactMessages(): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $isRead = $i % 3 === 0;
            $at = now()->subDays($i % 30)->subMinutes($i * 11);

            ContactMessage::firstOrCreate(['email' => 'demo.contact'.$this->key($i).'@example.test'], [
                'name' => fake()->name(),
                'subject' => 'Demo enquiry '.$this->key($i).': '.fake()->sentence(3),
                'message' => fake()->paragraph(3),
                'is_read' => $isRead,
                'read_at' => $isRead ? $at->copy()->addHour() : null,
                'created_at' => $at,
            ]);
        }
    }

    private function seedNewsletterSubscribers(): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $isActive = $i % 6 !== 0;
            $subscribedAt = now()->subDays($i % 90);

            NewsletterSubscriber::firstOrCreate(['email' => 'demo.subscriber'.$this->key($i).'@example.test'], [
                'name' => fake()->name(),
                'token' => Str::random(40),
                'is_active' => $isActive,
                'subscribed_at' => $subscribedAt,
                'unsubscribed_at' => $isActive ? null : $subscribedAt->copy()->addDays(5),
                'created_at' => $subscribedAt,
            ]);
        }
    }

    private function seedBlockedIps(): void
    {
        $adminId = Admin::query()->orderBy('id')->value('id');
        $reasons = ['Repeated failed logins', 'Scraping the catalogue', 'Fake orders', 'Spam contact form', null];

        // RFC 5737 documentation ranges: never a real visitor's address.
        for ($i = 1; $i <= 40; $i++) {
            BlockedIp::firstOrCreate(['ip_address' => ($i <= 20 ? '203.0.113.' : '198.51.100.').($i <= 20 ? $i : $i - 20)], [
                'reason' => $reasons[$i % count($reasons)],
                'blocked_by' => $adminId,
            ]);
        }
    }

    private function seedSmsPatterns(): void
    {
        $this->call(SmsPatternSeeder::class);

        $providers = ['bkash', 'nagad'];

        for ($i = 1; $i <= 40; $i++) {
            SmsPattern::firstOrCreate(['provider' => $providers[$i % count($providers)], 'name' => 'Demo Pattern '.$this->key($i)], [
                'identifier' => 'DEMO-IDENT-'.$this->key($i),
                'txn_id_regex' => 'TxnID:\s*([A-Z0-9]+)',
                'amount_regex' => 'Amount:\s*Tk\s*([\d,]+(?:\.\d+)?)',
                'is_active' => $i % 8 !== 0,
                'sort_order' => 10 + $i,
            ]);
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function seedReceivedSms(Collection $orders): void
    {
        $matchable = $orders->filter(fn (Order $order): bool => $order->payment_method === PaymentMethod::MobileBanking && $order->deleted_at === null)->values();

        for ($i = 1; $i <= self::COUNT; $i++) {
            $isBkash = $i % 2 === 1;
            $transactionId = 'DEMO'.strtoupper(Str::random(7));
            $amount = fake()->numberBetween(5, 80) * 50;
            $isMatched = $i % 4 === 0 && $matchable->isNotEmpty();
            $receivedAt = now()->subDays($i % 20)->subMinutes($i * 9);

            ReceivedSms::firstOrCreate(['sender' => 'DEMO'.($isBkash ? 'bKash' : 'Nagad').$this->key($i)], [
                'provider' => $isBkash ? 'bkash' : 'nagad',
                'content' => $isBkash
                    ? "You have received Tk {$amount}.00 from 01700000000. Fee Tk 0.00. TrxID {$transactionId} at ".$receivedAt->format('d/m/Y H:i')
                    : "Money Received.\nAmount: Tk {$amount}.00\nSender: 01800000000\nTxnID: {$transactionId}",
                'received_at' => $receivedAt,
                'parsed_transaction_id' => $transactionId,
                'parsed_amount' => $amount,
                'is_matched' => $isMatched,
                'matched_order_id' => $isMatched ? $matchable[$i % $matchable->count()]->id : null,
            ]);
        }
    }

    /** Demo slides stay inactive (except the first) so the storefront carousel isn't flooded. */
    private function seedHeroSlides(): void
    {
        for ($i = 1; $i <= 40; $i++) {
            HeroSlide::firstOrCreate(['title' => 'Demo Hero Slide '.$this->key($i)], [
                'eyebrow' => 'Demo collection '.$i,
                'description' => fake()->sentence(12),
                'image' => self::PLACEHOLDER,
                'primary_cta_label' => 'Shop now',
                'primary_cta_href' => '/products',
                'stats' => [['label' => 'Delivery', 'value' => '24–48h'], ['label' => 'Returns', 'value' => 'Easy']],
                'is_active' => false,
                'sort_order' => 100 + $i,
            ]);
        }
    }

    /** Demo banners stay inactive so the storefront homepage isn't flooded. */
    private function seedPromotionalBanners(): void
    {
        for ($i = 1; $i <= 40; $i++) {
            PromotionalBanner::firstOrCreate(['title' => 'Demo Banner '.$this->key($i)], [
                'description' => fake()->sentence(10),
                'image' => self::PLACEHOLDER,
                'href' => '/products',
                'badge' => 'Demo '.$i,
                'accent' => 'from-secondary/90 via-secondary/70 to-secondary/20',
                'is_active' => false,
                'sort_order' => 100 + $i,
            ]);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedLandingPages(Collection $products): void
    {
        $sellable = $products->filter(fn (Product $product): bool => $product->is_active)->values();

        for ($i = 1; $i <= 40; $i++) {
            $key = $this->key($i);
            $product = $sellable[$i % $sellable->count()];
            $sections = LandingPage::defaultSections();
            $sections['hero']['headline'] = 'Demo landing page '.$key;
            $sections['hero']['subheadline'] = fake()->sentence(10);

            LandingPage::firstOrCreate(['slug' => 'demo-landing-'.$key], [
                'title' => 'Demo Landing Page '.$key,
                'product_id' => $product->id,
                'is_active' => $i % 8 !== 0,
                'max_order_quantity' => 5,
                'sections' => $sections,
            ]);
        }
    }

    private function seedBlogs(): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $isPublished = $i % 5 !== 0;

            Blog::firstOrCreate(['slug' => 'demo-blog-post-'.$this->key($i)], [
                'title' => 'Demo Blog Post '.$this->key($i).': '.ucfirst(fake()->words(4, true)),
                'excerpt' => fake()->sentence(18),
                'content' => '<p>'.implode('</p><p>', fake()->paragraphs(4)).'</p>',
                'author_name' => fake()->name(),
                'meta_title' => 'Demo Blog Post '.$this->key($i),
                'is_published' => $isPublished,
                'published_at' => $isPublished ? now()->subDays($i) : null,
            ]);
        }
    }

    private function seedPages(): void
    {
        for ($i = 1; $i <= 40; $i++) {
            Page::firstOrCreate(['slug' => 'demo-page-'.$this->key($i)], [
                'title' => 'Demo Page '.$this->key($i),
                'content' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
                'meta_title' => 'Demo Page '.$this->key($i),
                'is_published' => $i % 4 !== 0,
            ]);
        }
    }

    private function seedPopularKeywords(): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            PopularKeyword::firstOrCreate(['keyword' => 'demo keyword '.$this->key($i)], [
                'sort_order' => 100 + $i,
                'is_active' => $i % 7 !== 0,
            ]);
        }
    }
}
