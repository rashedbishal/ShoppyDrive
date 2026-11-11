<?php

declare(strict_types=1);

/*
 * Review photos: off by default, switched on and limited from Admin > Settings,
 * stored resized, and removed from disk whenever the review (or its product) is
 * deleted.
 */

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Support\ReviewImageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function allowReviewPhotos(int $max = 3): void
{
    Setting::updateOrCreate(['key' => ReviewImageSettings::ENABLED_KEY], ['value' => true, 'group' => 'reviews']);
    Setting::updateOrCreate(['key' => ReviewImageSettings::MAX_PER_REVIEW_KEY], ['value' => $max, 'group' => 'reviews']);
}

/** @return array{User, Product} a verified customer who has a delivered order of the product */
function reviewerWithDeliveredProduct(): array
{
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::create([
        'order_number' => 'ORD-RVPHOTO-'.uniqid(), 'user_id' => $user->id, 'status' => OrderStatus::DELIVERED,
        'payment_status' => 'paid', 'payment_method' => 'cod', 'subtotal' => 1000, 'shipping_cost' => 120,
        'discount' => 0, 'tax' => 0, 'total' => 1120, 'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [], 'delivered_at' => now()->subDay(),
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
        'price' => 1000, 'quantity' => 1, 'subtotal' => 1000, 'product_snapshot' => [],
    ]);

    return [$user, $product];
}

function submitReview(User $user, Product $product, array $images = [], array $overrides = [])
{
    return actingAs($user)->post(route('product.reviews.store', $product->slug), array_merge([
        'rating' => 5,
        'body' => 'Great product, exactly as described and arrived quickly.',
        'images' => $images,
    ], $overrides));
}

function photo(string $name = 'photo.jpg', int $width = 800, int $height = 600): UploadedFile
{
    return UploadedFile::fake()->image($name, $width, $height);
}

// ── Off by default ───────────────────────────────────────────────────────────

test('photos are off by default, a review without photos is unchanged, and photos are refused', function () {
    [$user, $product] = reviewerWithDeliveredProduct();

    submitReview($user, $product)->assertSessionHasNoErrors();
    expect(Review::query()->sole()->images)->toBeNull();

    [$other, $otherProduct] = reviewerWithDeliveredProduct();
    submitReview($other, $otherProduct, [photo()])->assertSessionHasErrors('images');

    expect(Review::query()->count())->toBe(1);
    expect(Storage::disk('public')->allFiles('reviews'))->toBe([]);
});

test('the settings default to off and 3, and a stored number is kept within 1 to 5', function () {
    expect(ReviewImageSettings::enabled())->toBeFalse()
        ->and(ReviewImageSettings::maxPerReview())->toBe(3);

    Setting::create(['key' => ReviewImageSettings::MAX_PER_REVIEW_KEY, 'value' => 99, 'group' => 'reviews']);
    expect(ReviewImageSettings::maxPerReview())->toBe(5);

    Setting::where('key', ReviewImageSettings::MAX_PER_REVIEW_KEY)->update(['value' => json_encode(0)]);
    expect(ReviewImageSettings::maxPerReview())->toBe(1);
});

// ── Uploading ────────────────────────────────────────────────────────────────

test('with photos on, a review keeps its photos, stored under reviews/', function () {
    allowReviewPhotos(3);
    [$user, $product] = reviewerWithDeliveredProduct();

    submitReview($user, $product, [photo('a.jpg'), photo('b.png')])->assertSessionHasNoErrors();

    $review = Review::query()->sole();
    expect($review->images)->toHaveCount(2)->and($review->is_approved)->toBeFalse();

    foreach ($review->images as $url) {
        expect($url)->toStartWith('/storage/reviews/');
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    }
});

test('a large photo is stored resized to at most 1600 px on its longest side', function () {
    allowReviewPhotos(3);
    [$user, $product] = reviewerWithDeliveredProduct();

    submitReview($user, $product, [photo('big.jpg', 4000, 3000)])->assertSessionHasNoErrors();

    $stored = Storage::disk('public')->path(substr(Review::query()->sole()->images[0], strlen('/storage/')));
    [$width, $height] = getimagesize($stored);

    expect(max($width, $height))->toBeLessThanOrEqual(1600)->and(min($width, $height))->toBeGreaterThan(1000);
});

test('the number of photos is limited to what the admin chose', function () {
    allowReviewPhotos(2);
    [$user, $product] = reviewerWithDeliveredProduct();

    submitReview($user, $product, [photo('1.jpg'), photo('2.jpg'), photo('3.jpg')])->assertSessionHasErrors('images');

    expect(Review::query()->count())->toBe(0)->and(Storage::disk('public')->allFiles('reviews'))->toBe([]);

    submitReview($user, $product, [photo('1.jpg'), photo('2.jpg')])->assertSessionHasNoErrors();
    expect(Review::query()->sole()->images)->toHaveCount(2);
});

test('files that are not acceptable photos are refused', function (UploadedFile $file) {
    allowReviewPhotos(3);
    [$user, $product] = reviewerWithDeliveredProduct();

    submitReview($user, $product, [$file])->assertSessionHasErrors('images.0');

    expect(Review::query()->count())->toBe(0)->and(Storage::disk('public')->allFiles('reviews'))->toBe([]);
})->with([
    'a photo over 5 MB' => fn () => UploadedFile::fake()->image('huge.jpg', 800, 600)->size(6000),
    'a GIF' => fn () => UploadedFile::fake()->create('anim.gif', 100, 'image/gif'),
    'an SVG' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'a PDF' => fn () => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    'a script renamed to .jpg' => fn () => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo 1;'),
    'a tiny image' => fn () => UploadedFile::fake()->image('tiny.jpg', 100, 100),
]);

test('only a customer who ordered the product can attach photos (nothing is stored otherwise)', function () {
    allowReviewPhotos(3);
    $stranger = User::factory()->create();
    $product = Product::factory()->create();

    submitReview($stranger, $product, [photo()])->assertSessionHasErrors('review');

    expect(Review::query()->count())->toBe(0)->and(Storage::disk('public')->allFiles('reviews'))->toBe([]);
});

test('a second review of the same product stores nothing', function () {
    allowReviewPhotos(3);
    [$user, $product] = reviewerWithDeliveredProduct();

    submitReview($user, $product, [photo()])->assertSessionHasNoErrors();
    $filesAfterFirst = Storage::disk('public')->allFiles('reviews');

    submitReview($user, $product, [photo(), photo('x.jpg')])->assertSessionHasErrors('review');

    expect(Review::query()->count())->toBe(1)->and(Storage::disk('public')->allFiles('reviews'))->toBe($filesAfterFirst);
});

// ── Showing them ─────────────────────────────────────────────────────────────

test('an approved review shows its photos on the product page and a pending one does not', function () {
    allowReviewPhotos(3);
    [$user, $product] = reviewerWithDeliveredProduct();
    submitReview($user, $product, [photo()]);
    $review = Review::query()->sole();

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn ($page) => $page->has('reviews', 0));

    $review->update(['is_approved' => true]);

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn ($page) => $page->has('reviews', 1)->where('reviews.0.images.0', $review->images[0]));
});

test('the storefront is told whether photos are allowed and how many', function () {
    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('settings.review_images_enabled', false)->where('settings.review_images_max', 3));

    allowReviewPhotos(4);

    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('settings.review_images_enabled', true)->where('settings.review_images_max', 4));
});

// ── Deleting ─────────────────────────────────────────────────────────────────

test('deleting a review as an admin removes its photos from disk', function () {
    allowReviewPhotos(3);
    [$user, $product] = reviewerWithDeliveredProduct();
    submitReview($user, $product, [photo('a.jpg'), photo('b.jpg')]);
    $review = Review::query()->sole();
    $paths = array_map(fn (string $url) => substr($url, strlen('/storage/')), $review->images);
    foreach ($paths as $path) {
        Storage::disk('public')->assertExists($path);
    }

    auth('web')->logout(); // the customer is done; the admin area turns logged-in customers away
    actingAs(createAdmin(['email' => 'reviews-admin@example.com']), 'admin')
        ->delete(route('admin.reviews.destroy', $review))
        ->assertSessionHasNoErrors();

    foreach ($paths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
    expect(Review::query()->count())->toBe(0)
        ->and(Review::withTrashed()->sole()->images)->toBeNull();
});

test('deleting a review with no photos still works and touches no other files', function () {
    $other = UploadedFile::fake()->image('keep.jpg')->store('reviews', 'public');
    [$user, $product] = reviewerWithDeliveredProduct();
    submitReview($user, $product);
    auth('web')->logout();

    actingAs(createAdmin(['email' => 'reviews-admin2@example.com']), 'admin')
        ->delete(route('admin.reviews.destroy', Review::query()->sole()))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists($other);
});

test('only files inside reviews/ are ever deleted, whatever a review row holds', function () {
    $outside = UploadedFile::fake()->image('avatar.jpg')->store('avatars', 'public');
    [$user, $product] = reviewerWithDeliveredProduct();
    $review = Review::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5, 'body' => 'Ten characters at least.', 'is_approved' => false,
        'images' => ['/storage/'.$outside, '/storage/reviews/../avatars/x.jpg', 'https://example.test/a.jpg', 123]]);

    $review->delete();

    Storage::disk('public')->assertExists($outside);
});

test('permanently deleting a product removes the photos of its reviews', function () {
    allowReviewPhotos(3);
    [$user, $product] = reviewerWithDeliveredProduct();
    submitReview($user, $product, [photo()]);
    $path = substr(Review::query()->sole()->images[0], strlen('/storage/'));
    Storage::disk('public')->assertExists($path);

    // The order history that normally blocks a permanent delete is removed first.
    OrderItem::where('product_id', $product->id)->delete();
    $product->forceDelete();

    Storage::disk('public')->assertMissing($path);
});

// ── Admin settings ───────────────────────────────────────────────────────────

test('an admin can turn photos on and choose how many, and the choice is saved', function () {
    actingAs(createAdmin(['email' => 'settings-admin@example.com']), 'admin')
        ->put(route('admin.settings.update'), ['review_images_enabled' => '1', 'review_images_max' => '4'])
        ->assertSessionHasNoErrors();

    expect(ReviewImageSettings::enabled())->toBeTrue()->and(ReviewImageSettings::maxPerReview())->toBe(4);

    actingAs(createAdmin(['email' => 'settings-admin2@example.com']), 'admin')
        ->put(route('admin.settings.update'), ['review_images_enabled' => '0'])
        ->assertSessionHasNoErrors();

    expect(ReviewImageSettings::enabled())->toBeFalse()->and(ReviewImageSettings::maxPerReview())->toBe(4);
});

test('the number of photos must be between 1 and 5', function (string $value) {
    actingAs(createAdmin(['email' => 'settings-admin3@example.com']), 'admin')
        ->put(route('admin.settings.update'), ['review_images_enabled' => '1', 'review_images_max' => $value])
        ->assertSessionHasErrors('review_images_max');

    expect(ReviewImageSettings::enabled())->toBeFalse();
})->with(['zero' => '0', 'six' => '6', 'text' => 'many']);

test('an admin without the settings permission cannot change it', function () {
    $staff = Admin::create(['name' => 'Limited', 'email' => 'limited-rev@example.com', 'password' => 'password', 'permissions' => ['reviews' => ['view']]]);

    actingAs($staff, 'admin')->put(route('admin.settings.update'), ['review_images_enabled' => '1'])->assertForbidden();

    expect(ReviewImageSettings::enabled())->toBeFalse();
});

test('the admin settings page is given the current choice', function () {
    allowReviewPhotos(2);

    actingAs(createAdmin(['email' => 'settings-admin4@example.com']), 'admin')->get(route('admin.settings.edit'))
        ->assertInertia(fn ($page) => $page->where('settings.review_images_enabled', true)->where('settings.review_images_max', 2));
});
