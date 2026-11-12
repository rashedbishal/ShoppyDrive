<?php

declare(strict_types=1);

use App\Jobs\SendNewsletterChunk;
use App\Mail\NewsletterProductMail;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->admin = createAdmin();
    $this->product = Product::factory()->create(['is_active' => true, 'slug' => 'promo-shoes']);

    NewsletterSubscriber::create([
        'email' => 'reader@example.com',
        'token' => 'reader-token',
        'is_active' => true,
        'subscribed_at' => now(),
    ]);
});

/** A real image file on the fake public disk, attached to the product. */
function promoImage(Product $product, string $name, bool $primary = false, int $width = 1600, int $height = 1200): ProductMedia
{
    $path = Storage::disk('public')->putFileAs('products', UploadedFile::fake()->image($name, $width, $height), $name);

    return ProductMedia::create([
        'product_id' => $product->id,
        'path' => '/storage/'.$path,
        'type' => 'image',
        'is_primary' => $primary,
        'sort_order' => 0,
    ]);
}

function promoVideo(Product $product, bool $primary = true): ProductMedia
{
    return ProductMedia::create([
        'product_id' => $product->id,
        'path' => '/storage/products/clip.mp4',
        'type' => 'video',
        'is_primary' => $primary,
        'sort_order' => 0,
    ]);
}

/** @return array<string, mixed> the newsletter payload of the first queued chunk */
function queuedPromotion(): array
{
    $payload = null;

    Bus::assertBatched(function (PendingBatch $batch) use (&$payload): bool {
        $payload = $batch->jobs->first(fn ($job) => $job instanceof SendNewsletterChunk)?->newsletter;

        return $payload !== null;
    });

    return $payload;
}

test('the View Product button links to the real product page', function () {
    $mail = new NewsletterProductMail(emailSubject: 'New arrival', product: $this->product);
    $productUrl = route('product.show', $this->product);

    expect($productUrl)->toEndWith('/product/promo-shoes');
    $mail->assertSeeInHtml('href="'.$productUrl.'"', false)
        ->assertDontSeeInHtml('/products/promo-shoes', false);

    $this->get($productUrl)->assertOk();
});

test('the product image uses a full address, not a site-relative path', function () {
    $mail = new NewsletterProductMail(emailSubject: 'New arrival', product: $this->product, selectedImage: '/storage/products/shoe.jpg');

    $mail->assertSeeInHtml('src="'.url('/storage/products/shoe.jpg').'"', false)
        ->assertDontSeeInHtml('src="/storage/', false);
});

test('without a chosen image the email uses the primary image, never a video', function () {
    promoVideo($this->product, primary: true);
    $image = promoImage($this->product, 'shoe.jpg');

    (new NewsletterProductMail(emailSubject: 'New arrival', product: $this->product->load('images')))
        ->assertSeeInHtml('src="'.url($image->path).'"', false)
        ->assertDontSeeInHtml('clip.mp4', false);
});

test('a product with no images is still sent, just without a picture', function () {
    promoVideo($this->product);

    (new NewsletterProductMail(emailSubject: 'New arrival', product: $this->product))
        ->assertDontSeeInHtml('class="product-image"', false)
        ->assertSeeInHtml('View Product');
});

test('the compose page offers only images to pick from', function () {
    promoVideo($this->product);
    $image = promoImage($this->product, 'shoe.jpg', primary: true);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.newsletter.compose'))
        ->assertInertia(fn (Assert $page) => $page->where(
            'products',
            fn ($products): bool => collect(collect($products)->firstWhere('id', $this->product->id)['media'])
                ->pluck('path')->all() === [$image->path],
        ));
});

test('sending with a chosen image queues an email-safe JPEG copy of it', function () {
    Bus::fake();
    $image = promoImage($this->product, 'shoe.webp', primary: true, width: 2400, height: 1800);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.newsletter.send'), [
            'type' => 'product_promotion',
            'subject' => 'New arrival',
            'product_id' => $this->product->id,
            'selected_image' => $image->path,
        ])
        ->assertSessionHasNoErrors();

    $copy = queuedPromotion()['selected_image'];
    expect($copy)->toStartWith('/storage/newsletter-images/')->toEndWith('.jpg');

    [$width, $height, $type] = getimagesize(Storage::disk('public')->path(substr($copy, strlen('/storage/'))));
    expect($type)->toBe(IMAGETYPE_JPEG)
        ->and(max($width, $height))->toBe(1200);
});

test('sending without a chosen image uses a copy of the primary image', function () {
    Bus::fake();
    promoVideo($this->product, primary: true);
    promoImage($this->product, 'shoe.png');

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.newsletter.send'), [
            'type' => 'product_promotion',
            'subject' => 'New arrival',
            'product_id' => $this->product->id,
        ])
        ->assertSessionHasNoErrors();

    expect(queuedPromotion()['selected_image'])->toStartWith('/storage/newsletter-images/');
});

test('a chosen image that is not one of the product\'s images is rejected', function (string $selected) {
    Bus::fake();
    promoVideo($this->product);
    $other = Product::factory()->create();
    promoImage($other, 'other.jpg');

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.newsletter.send'), [
            'type' => 'product_promotion',
            'subject' => 'New arrival',
            'product_id' => $this->product->id,
            'selected_image' => $selected,
        ])
        ->assertSessionHasErrors(['selected_image' => "Choose one of this product's images."]);

    Bus::assertNothingBatched();
})->with([
    'its video' => '/storage/products/clip.mp4',
    'another product\'s image' => '/storage/products/other.jpg',
    'an outside address' => 'https://evil.example/tracker.png',
]);
