<?php

declare(strict_types=1);

use App\Exceptions\InsufficientStockException;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\LandingPageLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

/*
 * DISC-65: optional Bangla for a landing page — the admin's switch and
 * default language, the saved Bangla text, what the storefront receives,
 * and the customer messages in Bangla mode. Ordering itself is unchanged.
 */
beforeEach(function () {
    Mail::fake();
    createMotherAdmin();

    $this->product = Product::factory()->create([
        'name' => 'Tea Set', 'is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 10,
    ]);
});

/** @param  array<string, mixed>  $attributes */
function banglaLandingPage(Product $product, array $attributes = []): LandingPage
{
    return LandingPage::create(array_merge([
        'title' => 'Promo', 'slug' => 'promo-'.Str::lower(Str::random(8)), 'product_id' => $product->id, 'is_active' => true,
        'sections' => LandingPage::defaultSections(),
        'bangla_enabled' => true, 'default_language' => 'bn',
        'title_bn' => 'অফার', 'sections_bn' => ['hero' => ['headline' => 'সেরা চা সেট'], 'product' => ['name' => 'চা সেট']],
    ], $attributes));
}

/** @param  array<string, mixed>  $overrides */
function banglaOrderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Customer', 'phone' => '01712345678', 'address_line1' => '123 Main St',
        'city' => 'Dhaka', 'state' => 'Dhaka', 'quantity' => 1,
        'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
    ], $overrides);
}

/** @param  array<string, mixed>  $overrides */
function banglaUpdatePayload(LandingPage $page, array $overrides = []): array
{
    return array_merge([
        'title' => $page->title, 'slug' => $page->slug, 'product_id' => $page->product_id, 'is_active' => true,
        'sections' => $page->getSections(),
    ], $overrides);
}

test('a new landing page stores the Bangla switch and the default language', function () {
    actingAs(createAdmin(), 'admin');

    post(route('admin.landing-pages.store'), [
        'title' => 'Eid Offer', 'slug' => 'eid-offer', 'product_id' => $this->product->id, 'is_active' => true,
        'bangla_enabled' => true, 'default_language' => 'bn',
    ])->assertRedirect(route('admin.landing-pages.index'));

    $page = LandingPage::query()->where('slug', 'eid-offer')->sole();
    expect($page->bangla_enabled)->toBeTrue()
        ->and($page->default_language)->toBe('bn');
});

test('a new landing page without the Bangla fields is created English-only, as before', function () {
    actingAs(createAdmin(), 'admin');

    post(route('admin.landing-pages.store'), [
        'title' => 'Plain', 'slug' => 'plain-page', 'product_id' => $this->product->id, 'is_active' => true,
    ])->assertRedirect(route('admin.landing-pages.index'));

    $page = LandingPage::query()->where('slug', 'plain-page')->sole();
    expect($page->bangla_enabled)->toBeFalse()
        ->and($page->default_language)->toBe('en')
        ->and($page->sections_bn)->toBeNull();
});

test('an unknown default language is rejected', function () {
    actingAs(createAdmin(), 'admin');

    post(route('admin.landing-pages.store'), [
        'title' => 'Eid Offer', 'slug' => 'eid-offer', 'product_id' => $this->product->id,
        'bangla_enabled' => true, 'default_language' => 'fr',
    ])->assertSessionHasErrors('default_language');

    expect(LandingPage::query()->count())->toBe(0);
});

test('saving the page stores the Bangla title and text, cleaning Bangla rich text like the English', function () {
    $page = banglaLandingPage($this->product, ['sections_bn' => null, 'title_bn' => null]);
    actingAs(createAdmin(), 'admin');

    put(route('admin.landing-pages.update', $page), banglaUpdatePayload($page, [
        'bangla_enabled' => true, 'default_language' => 'en', 'title_bn' => 'ঈদ অফার',
        'sections_bn' => [
            'hero' => ['headline' => 'সেরা অফার'],
            'faq' => ['items' => [['question' => 'প্রশ্ন', 'answer' => 'উত্তর']]],
            'testimonials' => ['items' => [['name' => 'রহিম', 'text' => '<p>ভালো</p><script>alert(1)</script>']]],
        ],
    ]))->assertSessionHasNoErrors();

    $page->refresh();
    expect($page->title_bn)->toBe('ঈদ অফার')
        ->and($page->default_language)->toBe('en')
        ->and($page->sections_bn['hero']['headline'])->toBe('সেরা অফার')
        ->and($page->sections_bn['faq']['items'][0]['answer'])->toBe('উত্তর')
        ->and($page->sections_bn['testimonials']['items'][0]['text'])->toBe('<p>ভালো</p>');
});

test('a Bangla text longer than its English field allows is rejected', function () {
    $page = banglaLandingPage($this->product);
    actingAs(createAdmin(), 'admin');

    put(route('admin.landing-pages.update', $page), banglaUpdatePayload($page, [
        'bangla_enabled' => true, 'sections_bn' => ['hero' => ['headline' => str_repeat('ক', 121)]],
    ]))->assertSessionHasErrors('sections_bn.hero.headline');

    expect($page->refresh()->sections_bn['hero']['headline'])->toBe('সেরা চা সেট');
});

test('turning Bangla off hides it but keeps the Bangla text', function () {
    $page = banglaLandingPage($this->product);
    actingAs(createAdmin(), 'admin');

    put(route('admin.landing-pages.update', $page), banglaUpdatePayload($page, [
        'bangla_enabled' => false, 'title_bn' => $page->title_bn, 'sections_bn' => $page->sections_bn,
    ]))->assertSessionHasNoErrors();

    $page->refresh();
    expect($page->bangla_enabled)->toBeFalse()
        ->and($page->sections_bn['hero']['headline'])->toBe('সেরা চা সেট');
    get(route('lp.show', $page->slug))->assertOk()->assertInertia(fn ($p) => $p
        ->where('page.bangla_enabled', false)
        ->where('page.default_language', 'en')
        ->where('page.title_bn', null)
        ->where('page.sections_bn', null));
});

test('a page with Bangla on sends its default language and Bangla text to the storefront', function () {
    $page = banglaLandingPage($this->product);

    get(route('lp.show', $page->slug))->assertOk()->assertInertia(fn ($p) => $p
        ->where('page.bangla_enabled', true)
        ->where('page.default_language', 'bn')
        ->where('page.title_bn', 'অফার')
        ->where('page.sections_bn.hero.headline', 'সেরা চা সেট')
        ->where('page.sections.hero.cta_text', 'Order Now'));
});

test('a Bangla visitor gets the order form errors in Bangla', function () {
    $page = banglaLandingPage($this->product);

    post(route('lp.order', $page->slug), banglaOrderPayload(['phone' => '12345', 'lang' => 'bn']))
        ->assertSessionHasErrors(['phone' => 'সঠিক ১১ সংখ্যার বাংলাদেশি মোবাইল নম্বর লিখুন (যেমন 01712345678)।']);

    expect(Order::query()->count())->toBe(0);
});

test('asking for Bangla on a page without Bangla still answers in English', function () {
    $page = banglaLandingPage($this->product, ['bangla_enabled' => false]);

    post(route('lp.order', $page->slug), banglaOrderPayload(['phone' => '12345', 'lang' => 'bn']))
        ->assertSessionHasErrors(['phone' => 'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).']);
});

test('a Bangla visitor ordering more than is in stock is told so in Bangla, with the Bangla product name', function () {
    $this->product->update(['stock_quantity' => 3]);
    $page = banglaLandingPage($this->product);

    post(route('lp.order', $page->slug), banglaOrderPayload(['quantity' => 5, 'lang' => 'bn']))
        ->assertSessionHasErrors(['quantity' => '"চা সেট" স্টকে মাত্র ৩ টি আছে।']);

    expect(Order::query()->count())->toBe(0)
        ->and($this->product->refresh()->stock_quantity)->toBe(3);
});

test('an order placed in Bangla mode is created exactly like an English one', function () {
    $page = banglaLandingPage($this->product);

    post(route('lp.order', $page->slug), banglaOrderPayload(['quantity' => 2, 'lang' => 'bn']))->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect((float) $order->subtotal)->toBe(1000.0)
        ->and($order->items()->sole()->product_name)->toBe('Tea Set')
        ->and($this->product->refresh()->stock_quantity)->toBe(8);
});

test('a Bangla visitor with an unknown transaction id is told so in Bangla', function () {
    $page = banglaLandingPage($this->product);
    post(route('lp.order', $page->slug), banglaOrderPayload(['payment_method' => 'mobile_banking', 'provider' => 'bkash']));
    $pending = PendingCheckout::query()->where('landing_page_id', $page->id)->sole();

    post(route('lp.payment.submit', [$page->slug, $pending->token]), ['transaction_id' => 'NOPE0001', 'lang' => 'bn'])
        ->assertSessionHasErrors(['transaction_id' => 'আমাদের বিকাশ রেকর্ডে এই ট্রানজেকশন আইডি পাওয়া যায়নি। এসএমএস থেকে আইডিটি আবার মিলিয়ে দেখুন।']);

    expect(Order::query()->count())->toBe(0);
});

test('the confirmation page gets Bangla labels for the product, its options and the language settings', function () {
    $variant = ProductVariant::factory()->create(['product_id' => $this->product->id, 'price' => 600, 'stock_quantity' => 5, 'attributes' => ['Color' => 'Red']]);
    $page = banglaLandingPage($this->product, ['sections_bn' => ['product' => [
        'name' => 'চা সেট',
        'attribute_names' => [['en' => 'Color', 'bn' => 'রং']],
        'value_names' => [['attribute' => 'Color', 'en' => 'Red', 'bn' => 'লাল']],
    ]]]);
    post(route('lp.order', $page->slug), banglaOrderPayload(['items' => [['variant_id' => $variant->id, 'quantity' => 1]]]));
    $order = Order::query()->sole();

    get(route('lp.confirmation', $order->guest_token))->assertOk()->assertInertia(fn ($p) => $p
        ->where('language.slug', $page->slug)
        ->where('language.default_language', 'bn')
        ->where('order.items.0.product_name', 'Tea Set')
        ->where('order.items.0.variant_label', 'Color: Red')
        ->where('order.items.0.bn.product_name', 'চা সেট')
        ->where('order.items.0.bn.variant_label', 'রং: লাল'));
});

test('stock messages are worded in Bangla for each kind of stock problem', function (InsufficientStockException $exception, string $expected) {
    expect(LandingPageLanguage::stockMessage($exception))->toBe($expected);
})->with([
    'a few left' => [fn () => InsufficientStockException::forProduct('Tea Set', 3), '"Tea Set" স্টকে মাত্র ৩ টি আছে।'],
    'out of stock' => [fn () => InsufficientStockException::forProduct('Tea Set', 0), '"Tea Set" স্টকে নেই।'],
    'unnamed item' => [fn () => InsufficientStockException::forProduct('this item', 0), 'এই পণ্যটি স্টকে নেই।'],
    'flash-sale limit' => [fn () => InsufficientStockException::forFlashSaleLimit('Tea Set'), '"Tea Set"-এর ফ্ল্যাশ সেলের স্টক শেষ হয়ে গেছে।'],
    'mismatched variant' => [fn () => InsufficientStockException::forMismatchedVariant(), 'বেছে নেওয়া একটি অপশন এখন পাওয়া যাচ্ছে না।'],
    'COD limit' => [fn () => InsufficientStockException::forPendingCodLimit('Tea Set', 10, 4), 'আগের অর্ডারগুলো পাঠানো না হওয়া পর্যন্ত একটি ফোন নম্বর থেকে ক্যাশ অন ডেলিভারিতে "Tea Set" সর্বোচ্চ ১০ টি অর্ডার করা যায়। আপনার ৪ টি ইতিমধ্যে পাঠানোর অপেক্ষায় আছে। অনুগ্রহ করে পরিমাণ কমান, বিকাশ/নগদে পেমেন্ট করুন, অথবা আমাদের সাথে যোগাযোগ করুন।'],
]);
