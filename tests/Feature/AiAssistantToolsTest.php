<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FlashSaleItem;
use App\Models\HomepageSetting;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;
use App\Models\PromotionalBanner;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\AiAssistantTools;

function aiAssistantTestOrder(array $overrides = []): Order
{
    $address = ['name' => 'Test Customer', 'phone' => '01712345678', 'email' => 'buyer@example.com', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'];

    return Order::create(array_merge([
        'order_number' => 'ORD-AI-'.uniqid(), 'status' => 'processing', 'payment_status' => 'paid',
        'payment_method' => 'cod', 'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => $address, 'billing_address' => $address,
    ], $overrides));
}

test('a guest can look up their own order with the right phone number', function () {
    $order = aiAssistantTestOrder();

    $result = app(AiAssistantTools::class)->lookupOrder(null, $order->order_number, null, '01712345678');

    expect($result['found'])->toBeTrue()
        ->and($result['order_number'])->toBe($order->order_number);
});

test('a guest cannot look up an order with the wrong phone number', function () {
    $order = aiAssistantTestOrder();

    $result = app(AiAssistantTools::class)->lookupOrder(null, $order->order_number, null, '01999999999');

    expect($result['found'])->toBeFalse()
        ->and($result)->not->toHaveKey('order_number')
        ->and($result)->not->toHaveKey('total');
});

test('a guest cannot look up an order without any contact info at all', function () {
    $order = aiAssistantTestOrder();

    $result = app(AiAssistantTools::class)->lookupOrder(null, $order->order_number, null, null);

    expect($result['found'])->toBeFalse();
});

test('a logged-in customer can reach their own order without any phone or email', function () {
    $user = User::factory()->create();
    $order = aiAssistantTestOrder(['user_id' => $user->id]);

    $result = app(AiAssistantTools::class)->lookupOrder($user, $order->order_number, null, null);

    expect($result['found'])->toBeTrue()
        ->and($result['order_number'])->toBe($order->order_number);
});

test('a logged-in customer can never reach another customer\'s order by number', function () {
    $user = User::factory()->create();
    $otherUsersOrder = aiAssistantTestOrder(['user_id' => User::factory()->create()->id]);

    $result = app(AiAssistantTools::class)->lookupOrder($user, $otherUsersOrder->order_number, null, null);

    expect($result['found'])->toBeFalse()
        ->and($result)->not->toHaveKey('total');
});

test('a logged-in customer with no order number gets their latest order', function () {
    $user = User::factory()->create();
    $older = aiAssistantTestOrder(['user_id' => $user->id]);
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    $latest = aiAssistantTestOrder(['user_id' => $user->id]);

    $result = app(AiAssistantTools::class)->lookupOrder($user, null, null, null);

    expect($result['found'])->toBeTrue()
        ->and($result['order_number'])->toBe($latest->order_number);
});

test('searching products only returns publicly visible products', function () {
    $visible = Product::factory()->create(['name' => 'Blue Winter Jacket', 'is_active' => true, 'published_at' => now()->subDay()]);
    Product::factory()->create(['name' => 'Blue Winter Scarf', 'is_active' => false, 'published_at' => now()->subDay()]);

    $result = app(AiAssistantTools::class)->searchProducts('Blue Winter');

    $names = collect($result['products'])->pluck('name')->all();

    expect($names)->toContain('Blue Winter Jacket')
        ->and($names)->not->toContain('Blue Winter Scarf');
});

test('search results report real stock, including a variant product summed across its variants', function () {
    Product::factory()->create(['name' => 'Plain Mug', 'stock_quantity' => 0]);
    $variantProduct = Product::factory()->create(['name' => 'Variant Shirt', 'stock_quantity' => 0]);
    ProductVariant::factory()->create(['product_id' => $variantProduct->id, 'stock_quantity' => 5]);
    ProductVariant::factory()->create(['product_id' => $variantProduct->id, 'stock_quantity' => 3]);

    $result = app(AiAssistantTools::class)->searchProducts('Plain Mug');
    $mug = collect($result['products'])->firstWhere('name', 'Plain Mug');
    expect($mug['in_stock'])->toBeFalse()
        ->and($mug['stock_count'])->toBe(0);

    $result = app(AiAssistantTools::class)->searchProducts('Variant Shirt');
    $shirt = collect($result['products'])->firstWhere('name', 'Variant Shirt');
    expect($shirt['in_stock'])->toBeTrue()
        ->and($shirt['stock_count'])->toBe(8);
});

test('checking an inactive product reports it as not found, not as out of stock', function () {
    Product::factory()->create(['name' => 'Discontinued Mug', 'is_active' => false, 'published_at' => now()->subDay()]);

    $result = app(AiAssistantTools::class)->checkProductAvailability('Discontinued Mug');

    expect($result['found'])->toBeFalse();
});

test('searching finds a product by a glued-together spelling the storefront\'s own search would miss', function () {
    Product::factory()->create(['name' => 'Comfy T-Shirt']);

    $result = app(AiAssistantTools::class)->searchProducts('tshirt');

    expect($result['found'])->toBeTrue()
        ->and(collect($result['products'])->pluck('name')->all())->toContain('Comfy T-Shirt');
});

test('checking availability also finds a product by a glued-together spelling', function () {
    Product::factory()->create(['name' => 'Comfy T-Shirt']);

    $result = app(AiAssistantTools::class)->checkProductAvailability('tshirt');

    expect($result['found'])->toBeTrue()
        ->and($result['name'])->toBe('Comfy T-Shirt');
});

test('a simple product with no active flash sale reports its real price, not a fabricated discount', function () {
    Product::factory()->create(['name' => 'Plain Mug', 'price' => 500]);

    $result = app(AiAssistantTools::class)->checkProductAvailability('Plain Mug');

    expect($result['on_flash_sale'])->toBeFalse()
        ->and($result['flash_sale_price'])->toBeNull()
        ->and($result['price'])->toBe(500.0);
});

test('a simple product on an active flash sale reports the real discounted price', function () {
    $product = Product::factory()->create(['name' => 'Sale Mug', 'price' => 500]);
    FlashSaleItem::factory()->create(['product_id' => $product->id, 'sale_price' => 350]);

    $result = app(AiAssistantTools::class)->checkProductAvailability('Sale Mug');

    expect($result['on_flash_sale'])->toBeTrue()
        ->and($result['flash_sale_price'])->toBe(350.0);
});

test('each variant reports its own real flash-sale price, not a guessed one', function () {
    $product = Product::factory()->create(['name' => 'Variant Shirt']);
    $onSale = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'attributes' => ['color' => 'Red']]);
    $notOnSale = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1200, 'attributes' => ['color' => 'Blue']]);
    FlashSaleItem::factory()->create(['product_id' => $product->id, 'variant_id' => $onSale->id, 'sale_price' => 700]);

    $result = app(AiAssistantTools::class)->checkProductAvailability('Variant Shirt');
    $variants = collect($result['variants'])->keyBy(fn ($v) => $v['attributes']['color']);

    expect($variants['Red']['on_flash_sale'])->toBeTrue()
        ->and($variants['Red']['flash_sale_price'])->toBe(700.0)
        ->and($variants['Blue']['on_flash_sale'])->toBeFalse()
        ->and($variants['Blue']['flash_sale_price'])->toBeNull()
        ->and($variants['Blue']['price'])->toBe(1200.0);
});

test('no offers are reported when nothing is currently active', function () {
    // A migration seeds a permanent "free delivery over X" announcement_bar
    // row for every store, so a truly "nothing active" state means that's
    // explicitly turned off too, not just the absence of flash sales/banners.
    HomepageSetting::where('section_key', 'announcement_bar')->update(['is_visible' => false]);

    $result = app(AiAssistantTools::class)->getCurrentOffers();

    expect($result['found'])->toBeFalse();
});

test('an active flash sale on a visible product is reported as an offer', function () {
    $product = Product::factory()->create(['name' => 'Sale Jacket', 'price' => 1000]);
    FlashSaleItem::factory()->create(['product_id' => $product->id, 'sale_price' => 750]);

    $result = app(AiAssistantTools::class)->getCurrentOffers();

    expect($result['found'])->toBeTrue()
        ->and($result['flash_sale_items'][0]['product_name'])->toBe('Sale Jacket')
        ->and($result['flash_sale_items'][0]['sale_price'])->toBe(750.0);
});

test('a flash sale on a hidden product is never reported as an offer', function () {
    $hidden = Product::factory()->create(['name' => 'Hidden Sale Item', 'is_active' => false]);
    FlashSaleItem::factory()->create(['product_id' => $hidden->id]);

    $result = app(AiAssistantTools::class)->getCurrentOffers();

    expect($result['flash_sale_items'])->toBe([]);
});

test('an active promotional banner and the visible announcement bar are both reported as offers', function () {
    PromotionalBanner::create(['title' => 'Festival Sale', 'description' => 'Up to 50% off', 'image' => 'banners/festival.jpg', 'is_active' => true, 'sort_order' => 1]);
    HomepageSetting::where('section_key', 'announcement_bar')->update(['title' => 'Free delivery over ৳999', 'is_visible' => true]);

    $result = app(AiAssistantTools::class)->getCurrentOffers();

    expect($result['found'])->toBeTrue()
        ->and($result['announcement'])->toBe('Free delivery over ৳999')
        ->and($result['promotions'][0]['title'])->toBe('Festival Sale');
});

test('an active bundle with at least 2 visible components is reported as a deal', function () {
    $bundle = ProductBundle::factory()->create(['name' => 'Starter Pack', 'bundle_price' => 500]);
    $a = Product::factory()->create(['name' => 'Bundle Item A']);
    $b = Product::factory()->create(['name' => 'Bundle Item B']);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id]);

    $result = app(AiAssistantTools::class)->getCurrentOffers();

    expect($result['found'])->toBeTrue()
        ->and($result['bundle_deals'][0]['name'])->toBe('Starter Pack')
        ->and($result['bundle_deals'][0]['bundle_price'])->toBe(500.0)
        ->and($result['bundle_deals'][0]['products'])->toContain('Bundle Item A', 'Bundle Item B');
});

test('a bundle left with fewer than 2 visible components is not reported as a deal', function () {
    $bundle = ProductBundle::factory()->create();
    $a = Product::factory()->create();
    $hidden = Product::factory()->create(['is_active' => false]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $hidden->id]);

    $result = app(AiAssistantTools::class)->getCurrentOffers();

    expect($result['bundle_deals'] ?? [])->toBe([]);
});

test('an unpublished policy topic is reported as not available rather than invented', function () {
    // BIZ-032/034 seeds this page already published by default — this test
    // is specifically about what happens when a store hasn't published it.
    Page::where('slug', 'privacy-policy')->update(['is_published' => false]);

    $result = app(AiAssistantTools::class)->getStorePolicy('privacy');

    expect($result['found'])->toBeFalse();
});

test('an unknown policy topic lists the valid ones instead of guessing', function () {
    $result = app(AiAssistantTools::class)->getStorePolicy('cookies');

    expect($result['found'])->toBeFalse()
        ->and($result['message'])->toContain('privacy-policy');
});

test('a published policy page is returned with its plain-text content and url', function () {
    // BIZ-032/034 already seeds this slug — update its content rather than
    // creating a second row, which would violate the unique slug constraint.
    Page::where('slug', 'privacy-policy')->update(['title' => 'Privacy Policy', 'content' => '<p>We respect your <b>privacy</b>.</p>', 'is_published' => true]);

    $result = app(AiAssistantTools::class)->getStorePolicy('privacy');

    expect($result['found'])->toBeTrue()
        ->and($result['title'])->toBe('Privacy Policy')
        ->and($result['content'])->toBe('We respect your privacy.')
        ->and($result['url'])->toBe(route('privacy-policy'));
});

test('a very long policy page is truncated rather than dumped in full', function () {
    Page::where('slug', 'terms')->update(['title' => 'Terms', 'content' => str_repeat('a', 4000), 'is_published' => true]);

    $result = app(AiAssistantTools::class)->getStorePolicy('terms');

    expect(mb_strlen($result['content']))->toBeLessThan(3100)
        ->and($result['content'])->toEndWith('…');
});

test('adding a simple product to the cart creates a real server-side line', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Comfy T-Shirt']);

    $result = app(AiAssistantTools::class)->addToCart($user, 'tshirt', 2, null);

    expect($result['success'])->toBeTrue()
        ->and($result['cart_summary']['items'][0]['product_name'])->toBe('Comfy T-Shirt')
        ->and($result['cart_summary']['items'][0]['quantity'])->toBe(2)
        ->and($result['cart'])->not->toBeNull();

    $cart = Cart::where('user_id', $user->id)->first();
    expect($cart->items()->where('product_id', $product->id)->first()?->quantity)->toBe(2);
});

test('a quantity is capped rather than trusted blindly from the model', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Comfy T-Shirt']);

    $result = app(AiAssistantTools::class)->addToCart($user, 'tshirt', 9999, null);

    expect($result['cart_summary']['items'][0]['quantity'])->toBe(20);
});

test('adding a product with variants but no selection asks instead of guessing', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Running Shoe']);
    ProductVariant::factory()->create(['product_id' => $product->id, 'attributes' => ['color' => 'Red', 'size' => '42']]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'attributes' => ['color' => 'Blue', 'size' => '43']]);

    $result = app(AiAssistantTools::class)->addToCart($user, 'Running Shoe', 1, null);

    expect($result['success'])->toBeFalse()
        ->and($result['available_options'])->toHaveCount(2);

    expect(Cart::where('user_id', $user->id)->first()?->items)->toBeEmpty();
});

test('adding a product with a matched variant description adds that exact variant', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Running Shoe']);
    $red = ProductVariant::factory()->create(['product_id' => $product->id, 'attributes' => ['color' => 'Red', 'size' => '42']]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'attributes' => ['color' => 'Blue', 'size' => '43']]);

    $result = app(AiAssistantTools::class)->addToCart($user, 'Running Shoe', 1, 'red');

    expect($result['success'])->toBeTrue();

    $cart = Cart::where('user_id', $user->id)->first();
    expect($cart->items()->where('variant_id', $red->id)->exists())->toBeTrue();
});

test('updating one cart item leaves every other line untouched', function () {
    $user = User::factory()->create();
    $tshirt = Product::factory()->create(['name' => 'Comfy T-Shirt']);
    $mug = Product::factory()->create(['name' => 'Coffee Mug']);
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $tshirt->id, 'quantity' => 1, 'price' => 500]);
    $cart->items()->create(['product_id' => $mug->id, 'quantity' => 3, 'price' => 200]);

    $result = app(AiAssistantTools::class)->setCartItemQuantity($user, 'tshirt', 5);

    expect($result['success'])->toBeTrue();
    expect($cart->items()->where('product_id', $tshirt->id)->first()->quantity)->toBe(5);
    expect($cart->items()->where('product_id', $mug->id)->first()->quantity)->toBe(3);
});

test('setting a cart item to quantity zero removes just that line', function () {
    $user = User::factory()->create();
    $tshirt = Product::factory()->create(['name' => 'Comfy T-Shirt']);
    $mug = Product::factory()->create(['name' => 'Coffee Mug']);
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $tshirt->id, 'quantity' => 1, 'price' => 500]);
    $cart->items()->create(['product_id' => $mug->id, 'quantity' => 3, 'price' => 200]);

    $result = app(AiAssistantTools::class)->setCartItemQuantity($user, 'tshirt', 0);

    expect($result['success'])->toBeTrue()
        ->and($cart->items()->where('product_id', $tshirt->id)->exists())->toBeFalse()
        ->and($cart->items()->where('product_id', $mug->id)->exists())->toBeTrue();
});

test('updating a product that is not in the cart is reported rather than silently ignored', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Comfy T-Shirt']);

    $result = app(AiAssistantTools::class)->setCartItemQuantity($user, 'tshirt', 3);

    expect($result['success'])->toBeFalse();
});

test('a bundle cart line is refused rather than edited by the assistant', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Bundled Item']);
    $bundle = ProductBundle::factory()->create();
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'bundle_id' => $bundle->id, 'bundle_unit_quantity' => 1, 'quantity' => 1, 'price' => 100]);

    $result = app(AiAssistantTools::class)->setCartItemQuantity($user, 'Bundled Item', 2);

    expect($result['success'])->toBeFalse();
});

test('viewing the cart reports its current contents', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Comfy T-Shirt']);
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 500]);

    $result = app(AiAssistantTools::class)->viewCart($user);

    expect($result['cart_summary']['item_count'])->toBe(2)
        ->and($result['cart_summary']['subtotal'])->toBe(1000.0);
});

test('an eligible coupon the customer already named is applied to their real cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 1000]);
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 1000]);
    $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'min_order_amount' => null]);

    $result = app(AiAssistantTools::class)->applyCouponToCart($user, 'save10');

    expect($result['success'])->toBeTrue()
        ->and($result['cart_summary']['coupon_code'])->toBe('SAVE10');

    expect($cart->fresh()->coupon_id)->toBe($coupon->id);
});

test('an unknown coupon code is reported as invalid, not silently ignored', function () {
    $user = User::factory()->create();
    Cart::create(['user_id' => $user->id]);

    $result = app(AiAssistantTools::class)->applyCouponToCart($user, 'DOES-NOT-EXIST');

    expect($result['success'])->toBeFalse();
});

test('a coupon below its minimum order amount is refused with the real eligibility reason', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100]);
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
    Coupon::factory()->create(['code' => 'BIGSPEND', 'min_order_amount' => 5000]);

    $result = app(AiAssistantTools::class)->applyCouponToCart($user, 'BIGSPEND');

    expect($result['success'])->toBeFalse()
        ->and($cart->fresh()->coupon_id)->toBeNull();
});

test('applying a coupon to an empty cart is refused rather than stored for later', function () {
    $user = User::factory()->create();
    Cart::create(['user_id' => $user->id]);
    Coupon::factory()->create(['code' => 'SAVE10']);

    $result = app(AiAssistantTools::class)->applyCouponToCart($user, 'SAVE10');

    expect($result['success'])->toBeFalse();
});

test('removing a coupon clears it from the real cart', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create();
    Cart::create(['user_id' => $user->id, 'coupon_id' => $coupon->id]);

    $result = app(AiAssistantTools::class)->removeCouponFromCart($user);

    expect($result['success'])->toBeTrue();
    expect(Cart::where('user_id', $user->id)->first()->coupon_id)->toBeNull();
});

test('removing a coupon when none is applied is reported rather than silently succeeding', function () {
    $user = User::factory()->create();
    Cart::create(['user_id' => $user->id]);

    $result = app(AiAssistantTools::class)->removeCouponFromCart($user);

    expect($result['success'])->toBeFalse();
});

test('a logged-in customer can cancel their own genuinely pending order', function () {
    $user = User::factory()->create();
    $order = aiAssistantTestOrder(['user_id' => $user->id, 'status' => 'pending']);

    $result = app(AiAssistantTools::class)->cancelOrder($user, $order->order_number);

    expect($result['success'])->toBeTrue();
    expect($order->refresh()->status->value)->toBe('cancelled');
});

test('cancelling never reaches another customer\'s order even by exact order number', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $order = aiAssistantTestOrder(['user_id' => $owner->id, 'status' => 'pending']);

    $result = app(AiAssistantTools::class)->cancelOrder($attacker, $order->order_number);

    expect($result['success'])->toBeFalse();
    expect($order->refresh()->status->value)->toBe('pending');
});

test('cancelling a non-pending order is refused with the real reason, same as the account page', function () {
    $user = User::factory()->create();
    $order = aiAssistantTestOrder(['user_id' => $user->id, 'status' => 'processing']);

    $result = app(AiAssistantTools::class)->cancelOrder($user, $order->order_number);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Only pending orders can be cancelled.');
    expect($order->refresh()->status->value)->toBe('processing');
});

test('cancelling a pending order already handed to a courier is refused', function () {
    $user = User::factory()->create();
    $order = aiAssistantTestOrder(['user_id' => $user->id, 'status' => 'pending', 'pathao_consignment_id' => 'PATHAO-TEST']);

    $result = app(AiAssistantTools::class)->cancelOrder($user, $order->order_number);

    expect($result['success'])->toBeFalse();
    expect($order->refresh()->status->value)->toBe('pending');
});

test('cancelling an order number that does not exist on the account is reported, not silently ignored', function () {
    $user = User::factory()->create();

    $result = app(AiAssistantTools::class)->cancelOrder($user, 'ORD-DOES-NOT-EXIST');

    expect($result['success'])->toBeFalse();
});

test('listing categories excludes inactive and soft-deleted ones', function () {
    Category::factory()->create(['name' => 'Visible Category']);
    Category::factory()->create(['name' => 'Disabled Category', 'is_active' => false]);
    Category::factory()->create(['name' => 'Deleted Category'])->delete();

    $result = app(AiAssistantTools::class)->listCategories();

    $names = collect($result['categories'])->pluck('name')->all();

    expect($names)->toBe(['Visible Category']);
});

test('no categories reports found false rather than an empty success', function () {
    $result = app(AiAssistantTools::class)->listCategories();

    expect($result['found'])->toBeFalse();
});

test('a category name is still matched when the customer drops its apostrophe', function () {
    $category = Category::factory()->create(['name' => "Men's Fashion"]);
    SubCategory::factory()->create(['name' => 'Shirts', 'category_id' => $category->id]);

    $result = app(AiAssistantTools::class)->listSubCategories('Mens fashion');

    expect($result['found'])->toBeTrue()
        ->and($result['category'])->toBe("Men's Fashion");
});

test('a brand name is still matched when the customer drops its apostrophe', function () {
    $brand = Brand::factory()->create(['name' => "Levi's"]);
    Product::factory()->create(['name' => 'Classic Jeans', 'brand_id' => $brand->id]);

    $result = app(AiAssistantTools::class)->listProductsByBrand('Levis');

    expect($result['found'])->toBeTrue()
        ->and($result['brand'])->toBe("Levi's");
});

test('sub-categories exclude an inactive or deleted parent category even if the sub-category itself is active', function () {
    $activeParent = Category::factory()->create(['name' => 'Electronics']);
    SubCategory::factory()->create(['name' => 'Phones', 'category_id' => $activeParent->id]);
    SubCategory::factory()->create(['name' => 'Disabled Sub', 'category_id' => $activeParent->id, 'is_active' => false]);

    $disabledParent = Category::factory()->create(['name' => 'Clearance', 'is_active' => false]);
    SubCategory::factory()->create(['name' => 'Should Not Appear', 'category_id' => $disabledParent->id]);

    $result = app(AiAssistantTools::class)->listSubCategories('Electronics');

    expect(collect($result['sub_categories'])->pluck('name')->all())->toBe(['Phones']);

    $hiddenParentResult = app(AiAssistantTools::class)->listSubCategories('Clearance');
    expect($hiddenParentResult['found'])->toBeFalse();
});

test('products listed by category never include an inactive product or one from another category', function () {
    $category = Category::factory()->create(['name' => 'Fashion']);
    $other = Category::factory()->create(['name' => 'Home']);
    Product::factory()->create(['name' => 'Visible Shirt', 'category_id' => $category->id]);
    Product::factory()->create(['name' => 'Hidden Shirt', 'category_id' => $category->id, 'is_active' => false]);
    Product::factory()->create(['name' => 'Other Category Item', 'category_id' => $other->id]);

    $result = app(AiAssistantTools::class)->listProductsInCategory('Fashion', null);

    expect(collect($result['products'])->pluck('name')->all())->toBe(['Visible Shirt']);
});

test('products can be narrowed to a specific sub-category under a category', function () {
    $category = Category::factory()->create(['name' => 'Fashion']);
    $sub = SubCategory::factory()->create(['name' => 'Shirts', 'category_id' => $category->id]);
    $otherSub = SubCategory::factory()->create(['name' => 'Shoes', 'category_id' => $category->id]);
    Product::factory()->create(['name' => 'A Shirt', 'category_id' => $category->id, 'sub_category_id' => $sub->id]);
    Product::factory()->create(['name' => 'A Shoe', 'category_id' => $category->id, 'sub_category_id' => $otherSub->id]);

    $result = app(AiAssistantTools::class)->listProductsInCategory('Fashion', 'Shirts');

    expect(collect($result['products'])->pluck('name')->all())->toBe(['A Shirt']);
});

test('listing brands excludes an inactive brand', function () {
    Brand::factory()->create(['name' => 'Visible Brand']);
    Brand::factory()->create(['name' => 'Disabled Brand', 'is_active' => false]);

    $result = app(AiAssistantTools::class)->listBrands();

    expect(collect($result['brands'])->pluck('name')->all())->toBe(['Visible Brand']);
});

test('products listed by brand never include a product whose own brand was deactivated', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);
    Product::factory()->create(['name' => 'Acme Gadget', 'brand_id' => $brand->id]);
    Product::factory()->create(['name' => 'Inactive Gadget', 'brand_id' => $brand->id, 'is_active' => false]);

    $result = app(AiAssistantTools::class)->listProductsByBrand('Acme');

    expect(collect($result['products'])->pluck('name')->all())->toBe(['Acme Gadget']);
});

test('a product is excluded from brand listing once its brand itself is deactivated, even though the product row is untouched', function () {
    $brand = Brand::factory()->create(['name' => 'Acme', 'is_active' => false]);
    Product::factory()->create(['name' => 'Acme Gadget', 'brand_id' => $brand->id]);

    $result = app(AiAssistantTools::class)->listProductsByBrand('Acme');

    expect($result['found'])->toBeFalse();
});

test('only approved reviews count toward a product\'s rating and review list', function () {
    $product = Product::factory()->create(['name' => 'Rated Product']);
    $reviewer = User::factory()->create();
    Review::create(['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 5, 'title' => 'Great', 'body' => 'Loved it.', 'is_approved' => true]);
    Review::create(['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 1, 'title' => 'Spam', 'body' => 'Not real.', 'is_approved' => false]);

    $result = app(AiAssistantTools::class)->getProductReviews('Rated Product');

    expect($result['reviews_count'])->toBe(1)
        ->and($result['average_rating'])->toBe(5.0)
        ->and(collect($result['reviews'])->pluck('title')->all())->toBe(['Great']);
});

test('a product with no approved reviews reports zero rather than an error', function () {
    Product::factory()->create(['name' => 'Unreviewed Product']);

    $result = app(AiAssistantTools::class)->getProductReviews('Unreviewed Product');

    expect($result['found'])->toBeTrue()
        ->and($result['reviews_count'])->toBe(0)
        ->and($result['average_rating'])->toBeNull();
});

test('featured products only ever includes visible products flagged as featured', function () {
    Product::factory()->create(['name' => 'Featured Item', 'is_featured' => true]);
    Product::factory()->create(['name' => 'Hidden Featured Item', 'is_featured' => true, 'is_active' => false]);
    Product::factory()->create(['name' => 'Not Featured', 'is_featured' => false]);

    $result = app(AiAssistantTools::class)->getFeaturedProducts('featured');

    expect(collect($result['products'])->pluck('name')->all())->toBe(['Featured Item']);
});

test('new arrivals only lists the admin-curated, still-visible product ids', function () {
    $visible = Product::factory()->create(['name' => 'New Visible']);
    $hidden = Product::factory()->create(['name' => 'New But Hidden', 'is_active' => false]);
    HomepageSetting::updateOrCreate(['section_key' => 'new_arrivals'], ['section_name' => 'New Arrivals', 'metadata' => ['product_ids' => [$visible->id, $hidden->id]]]);

    $result = app(AiAssistantTools::class)->getFeaturedProducts('new_arrivals');

    expect(collect($result['products'])->pluck('name')->all())->toBe(['New Visible']);
});

test('an unknown homepage section is reported rather than guessed', function () {
    $result = app(AiAssistantTools::class)->getFeaturedProducts('clearance');

    expect($result['found'])->toBeFalse();
});

test('shipping cost inside Dhaka city corporation is lower than everywhere else', function () {
    $inside = app(AiAssistantTools::class)->estimateShippingCost('Dhaka', true);
    $outsideDhaka = app(AiAssistantTools::class)->estimateShippingCost('Dhaka', false);
    $otherDistrict = app(AiAssistantTools::class)->estimateShippingCost('Chattogram', false);

    expect($inside['shipping_cost'])->toBeLessThan($outsideDhaka['shipping_cost'])
        ->and($outsideDhaka['shipping_cost'])->toBe($otherDistrict['shipping_cost']);
});

test('an invented district is reported as unrecognized rather than silently priced', function () {
    $result = app(AiAssistantTools::class)->estimateShippingCost('Atlantis', false);

    expect($result['found'])->toBeFalse();
});

test('account info reports the real points balance and referral code', function () {
    $user = User::factory()->create(['points_balance' => 120]);

    $result = app(AiAssistantTools::class)->getAccountInfo($user);

    expect($result['points_balance'])->toBe(120)
        ->and($result['referral_code'])->toBe($user->referral_code);
});

test('a published blog post matching the search is found, an unpublished one is not', function () {
    Blog::factory()->create(['title' => 'How to style a winter jacket']);
    Blog::factory()->draft()->create(['title' => 'Winter styling secrets']);

    $result = app(AiAssistantTools::class)->searchBlogPosts('winter');

    expect(collect($result['posts'])->pluck('title')->all())->toBe(['How to style a winter jacket']);
});

test('saved addresses only ever belong to the account asking', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Address::create(['user_id' => $user->id, 'label' => 'Home', 'name' => 'Test', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'postal_code' => '1200', 'country' => 'Bangladesh', 'is_default' => true]);
    Address::create(['user_id' => $other->id, 'label' => 'Work', 'name' => 'Other', 'phone' => '01700000001', 'address_line1' => 'House 2', 'city' => 'Dhaka', 'state' => 'Dhaka', 'postal_code' => '1200', 'country' => 'Bangladesh', 'is_default' => true]);

    $result = app(AiAssistantTools::class)->listSavedAddresses($user);

    expect($result['addresses'])->toHaveCount(1)
        ->and($result['addresses'][0]['label'])->toBe('Home');
});

test('adding and removing a wishlist item works and never duplicates an existing entry', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Wishlist Item']);

    $add = app(AiAssistantTools::class)->addToWishlist($user, 'Wishlist Item');
    expect($add['success'])->toBeTrue();

    app(AiAssistantTools::class)->addToWishlist($user, 'Wishlist Item');
    expect(Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->count())->toBe(1);

    $view = app(AiAssistantTools::class)->viewWishlist($user);
    expect(collect($view['items'])->pluck('product_name')->all())->toBe(['Wishlist Item']);

    $remove = app(AiAssistantTools::class)->removeFromWishlist($user, 'Wishlist Item');
    expect($remove['success'])->toBeTrue();
    expect(Wishlist::where('user_id', $user->id)->exists())->toBeFalse();
});

test('a wishlist item that is no longer visible is excluded when viewing the wishlist', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Discontinued', 'is_active' => false]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    $result = app(AiAssistantTools::class)->viewWishlist($user);

    expect($result['found'])->toBeFalse();
});

test('removing a product never on the wishlist is reported rather than silently succeeding', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Never Wishlisted']);

    $result = app(AiAssistantTools::class)->removeFromWishlist($user, 'Never Wishlisted');

    expect($result['success'])->toBeFalse();
});

test('a return request can be submitted for a delivered order within the window', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = Order::create([
        'order_number' => 'ORD-AIRET-'.uniqid(), 'user_id' => $user->id, 'status' => 'delivered', 'payment_status' => 'paid',
        'payment_method' => 'cod', 'subtotal' => 500, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 500,
        'shipping_address' => [], 'billing_address' => [], 'delivered_at' => now()->subDay(),
    ]);
    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 500, 'quantity' => 1, 'subtotal' => 500, 'product_snapshot' => []]);

    $result = app(AiAssistantTools::class)->submitReturnRequest($user, $order->order_number, 'Wrong size', null);

    expect($result['success'])->toBeTrue();
    expect(ReturnRequest::where('order_id', $order->id)->exists())->toBeTrue();
});

test('a return request is refused outside the eligibility window, with the real reason', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = Order::create([
        'order_number' => 'ORD-AIRET2-'.uniqid(), 'user_id' => $user->id, 'status' => 'delivered', 'payment_status' => 'paid',
        'payment_method' => 'cod', 'subtotal' => 500, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 500,
        'shipping_address' => [], 'billing_address' => [], 'delivered_at' => now()->subDays(10),
    ]);
    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 500, 'quantity' => 1, 'subtotal' => 500, 'product_snapshot' => []]);

    $result = app(AiAssistantTools::class)->submitReturnRequest($user, $order->order_number, 'Too late', null);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('window');
    expect(ReturnRequest::where('order_id', $order->id)->exists())->toBeFalse();
});

test('a return request never reaches another customer\'s order', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = Order::create([
        'order_number' => 'ORD-AIRET3-'.uniqid(), 'user_id' => $owner->id, 'status' => 'delivered', 'payment_status' => 'paid',
        'payment_method' => 'cod', 'subtotal' => 500, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 500,
        'shipping_address' => [], 'billing_address' => [], 'delivered_at' => now()->subDay(),
    ]);
    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 500, 'quantity' => 1, 'subtotal' => 500, 'product_snapshot' => []]);

    $result = app(AiAssistantTools::class)->submitReturnRequest($attacker, $order->order_number, 'Not mine', null);

    expect($result['success'])->toBeFalse();
    expect(ReturnRequest::where('order_id', $order->id)->exists())->toBeFalse();
});

test('a return request for a specific product only returns that product\'s remaining quantity', function () {
    $user = User::factory()->create();
    $productA = Product::factory()->create(['name' => 'Item A', 'price' => 500]);
    $productB = Product::factory()->create(['name' => 'Item B', 'price' => 300]);
    $order = Order::create([
        'order_number' => 'ORD-AIRET4-'.uniqid(), 'user_id' => $user->id, 'status' => 'delivered', 'payment_status' => 'paid',
        'payment_method' => 'cod', 'subtotal' => 800, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 800,
        'shipping_address' => [], 'billing_address' => [], 'delivered_at' => now()->subDay(),
    ]);
    $order->items()->create(['product_id' => $productA->id, 'product_name' => 'Item A', 'price' => 500, 'quantity' => 1, 'subtotal' => 500, 'product_snapshot' => []]);
    $order->items()->create(['product_id' => $productB->id, 'product_name' => 'Item B', 'price' => 300, 'quantity' => 1, 'subtotal' => 300, 'product_snapshot' => []]);

    $result = app(AiAssistantTools::class)->submitReturnRequest($user, $order->order_number, 'Item A was defective', 'Item A');

    expect($result['success'])->toBeTrue();
    $returnRequest = ReturnRequest::where('order_id', $order->id)->first();
    expect($returnRequest->items)->toHaveCount(1)
        ->and($returnRequest->items[0]['product_id'])->toBe($productA->id);
});

test('related products only come from the same category and never include the product itself', function () {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $product = Product::factory()->create(['name' => 'Main Product', 'category_id' => $category->id]);
    $sameCategory = Product::factory()->create(['name' => 'Same Category Item', 'category_id' => $category->id]);
    $hiddenSameCategory = Product::factory()->create(['name' => 'Hidden Sibling', 'category_id' => $category->id, 'is_active' => false]);
    Product::factory()->create(['name' => 'Different Category Item', 'category_id' => $otherCategory->id]);

    $result = app(AiAssistantTools::class)->getRelatedProducts('Main Product');

    $names = collect($result['related_products'])->pluck('name')->all();
    expect($names)->toBe(['Same Category Item'])
        ->and($names)->not->toContain('Main Product', 'Hidden Sibling', 'Different Category Item');
});

test('a product with nothing else in its category reports none found', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'Lonely Product', 'category_id' => $category->id]);

    $result = app(AiAssistantTools::class)->getRelatedProducts('Lonely Product');

    expect($result['found'])->toBeFalse();
});

test('listing my orders returns recent orders across statuses, newest first', function () {
    $user = User::factory()->create();
    $older = aiAssistantTestOrder(['user_id' => $user->id, 'status' => 'delivered']);
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    $newer = aiAssistantTestOrder(['user_id' => $user->id, 'status' => 'pending']);

    $result = app(AiAssistantTools::class)->listMyOrders($user);

    expect($result['found'])->toBeTrue();
    $numbers = collect($result['orders'])->pluck('order_number')->all();
    expect($numbers[0])->toBe($newer->order_number)
        ->and($numbers)->toContain($older->order_number);
});

test('a brand new account with no orders is reported rather than an empty success', function () {
    $user = User::factory()->create();

    $result = app(AiAssistantTools::class)->listMyOrders($user);

    expect($result['found'])->toBeFalse();
});

test('claiming the daily login bonus for the first time today awards points', function () {
    $user = User::factory()->create(['login_streak_count' => 0, 'login_streak_date' => null]);

    $result = app(AiAssistantTools::class)->claimDailyLoginBonus($user);

    expect($result['success'])->toBeTrue();
});

test('claiming the daily login bonus twice in the same day only awards it once', function () {
    $user = User::factory()->create();

    app(AiAssistantTools::class)->claimDailyLoginBonus($user);
    $second = app(AiAssistantTools::class)->claimDailyLoginBonus($user);

    expect($second['success'])->toBeFalse()
        ->and($second['message'])->toContain('Already claimed today');
});
