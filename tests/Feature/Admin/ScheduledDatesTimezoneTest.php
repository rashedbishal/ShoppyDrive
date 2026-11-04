<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Coupon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAs(createAdmin(), 'admin');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function couponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'EID20',
        'name' => 'Eid',
        'type' => 'percent',
        'value' => 20,
        'is_active' => true,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function blogPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Eid Tips',
        'slug' => 'eid-tips',
        'content' => '<p>Body.</p>',
        'is_published' => true,
    ], $overrides);
}

test('a coupon runs between the Bangladesh times the admin typed', function () {
    post(route('admin.coupons.store'), couponPayload([
        'starts_at' => '2027-01-10T00:00',
        'expires_at' => '2027-01-10T23:59',
    ]))->assertSessionHasNoErrors();

    $coupon = Coupon::where('code', 'EID20')->firstOrFail();

    expect($coupon->starts_at->toDateTimeString())->toBe('2027-01-09 18:00:00')
        ->and($coupon->expires_at->toDateTimeString())->toBe('2027-01-10 17:59:00');

    $errorAt = function (string $moment) use ($coupon): ?string {
        $this->travelTo($moment);

        return $coupon->eligibilityError(1000, null);
    };

    expect($errorAt('2027-01-09 23:59:00 Asia/Dhaka'))->toBe('This coupon is not yet active.')
        ->and($errorAt('2027-01-10 00:01:00 Asia/Dhaka'))->toBeNull()
        ->and($errorAt('2027-01-10 23:58:00 Asia/Dhaka'))->toBeNull()
        ->and($errorAt('2027-01-11 00:01:00 Asia/Dhaka'))->toBe('This coupon has expired.');
});

test('a coupon edit shows the times as typed, and resaving does not move them', function () {
    post(route('admin.coupons.store'), couponPayload([
        'starts_at' => '2027-01-10T10:00',
        'expires_at' => '2027-01-12T22:30',
    ]))->assertSessionHasNoErrors();

    $coupon = Coupon::where('code', 'EID20')->firstOrFail();

    $this->get(route('admin.coupons.edit', $coupon))->assertInertia(fn (Assert $page) => $page
        ->where('coupon.starts_at_input', '2027-01-10T10:00')
        ->where('coupon.expires_at_input', '2027-01-12T22:30'));

    put(route('admin.coupons.update', $coupon), couponPayload([
        'starts_at' => '2027-01-10T10:00',
        'expires_at' => '2027-01-12T22:30',
    ]))->assertSessionHasNoErrors();

    expect($coupon->fresh()->starts_at->toDateTimeString())->toBe('2027-01-10 04:00:00')
        ->and($coupon->fresh()->expires_at->toDateTimeString())->toBe('2027-01-12 16:30:00');
});

test('a coupon without dates keeps them empty, and they can be cleared', function () {
    post(route('admin.coupons.store'), couponPayload())->assertSessionHasNoErrors();

    $coupon = Coupon::where('code', 'EID20')->firstOrFail();

    expect($coupon->starts_at)->toBeNull()->and($coupon->expires_at)->toBeNull();

    $this->get(route('admin.coupons.edit', $coupon))->assertInertia(fn (Assert $page) => $page
        ->where('coupon.starts_at_input', '')
        ->where('coupon.expires_at_input', ''));

    put(route('admin.coupons.update', $coupon), couponPayload(['expires_at' => '2027-01-12T22:30']))->assertSessionHasNoErrors();
    put(route('admin.coupons.update', $coupon), couponPayload(['expires_at' => '']))->assertSessionHasNoErrors();

    expect($coupon->fresh()->expires_at)->toBeNull();
});

test('a coupon cannot expire before it starts', function () {
    post(route('admin.coupons.store'), couponPayload([
        'starts_at' => '2027-01-10T10:00',
        'expires_at' => '2027-01-10T09:00',
    ]))->assertSessionHasErrors('expires_at');
});

test('a scheduled blog post goes live at the Bangladesh time the admin typed', function () {
    post(route('admin.blogs.store'), blogPayload(['published_at' => '2027-01-10T09:00']))->assertSessionHasNoErrors();

    $blog = Blog::where('slug', 'eid-tips')->firstOrFail();

    expect($blog->published_at->toDateTimeString())->toBe('2027-01-10 03:00:00');

    $this->travelTo('2027-01-10 08:59:00 Asia/Dhaka');
    expect(Blog::published()->whereKey($blog->id)->exists())->toBeFalse();

    $this->travelTo('2027-01-10 09:01:00 Asia/Dhaka');
    expect(Blog::published()->whereKey($blog->id)->exists())->toBeTrue();
});

test('a blog edit shows the time as typed, and resaving does not move it', function () {
    post(route('admin.blogs.store'), blogPayload(['published_at' => '2027-01-10T09:00']))->assertSessionHasNoErrors();

    $blog = Blog::where('slug', 'eid-tips')->firstOrFail();

    $this->get(route('admin.blogs.edit', $blog))->assertInertia(fn (Assert $page) => $page
        ->where('blog.published_at_input', '2027-01-10T09:00'));

    put(route('admin.blogs.update', $blog), blogPayload(['published_at' => '2027-01-10T09:00']))->assertSessionHasNoErrors();

    expect($blog->fresh()->published_at->toDateTimeString())->toBe('2027-01-10 03:00:00');
});

test('a blog post published with no date goes live now', function () {
    $this->freezeTime();

    post(route('admin.blogs.store'), blogPayload())->assertSessionHasNoErrors();

    $blog = Blog::where('slug', 'eid-tips')->firstOrFail();

    expect($blog->published_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and(Blog::published()->whereKey($blog->id)->exists())->toBeTrue();
});
