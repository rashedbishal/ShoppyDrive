<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Rules\StorableDateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(createAdmin(), 'admin');
});

/** @return array<string, mixed> */
function rangeFlashSalePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Eid Sale',
        'starts_at' => '2027-01-10T09:00',
        'ends_at' => '2027-01-12T21:00',
        'is_active' => true,
    ], $overrides);
}

/** @return array<string, mixed> */
function rangeCouponPayload(array $overrides = []): array
{
    return array_merge(['code' => 'RANGE10', 'name' => 'Range', 'type' => 'percent', 'value' => 10, 'is_active' => true], $overrides);
}

/** @return array<string, mixed> */
function rangeBlogPayload(array $overrides = []): array
{
    return array_merge(['title' => 'Range Post', 'slug' => 'range-post', 'content' => '<p>Body.</p>', 'is_published' => true], $overrides);
}

test('a flash sale date the database cannot store is refused with a clear message', function (string $field, string $value) {
    $this->post(route('admin.flash-sales.store'), rangeFlashSalePayload([$field => $value]))
        ->assertSessionHasErrors([$field => StorableDateTime::MESSAGE]);

    expect(FlashSale::count())->toBe(0);
})->with([
    'start in year 9999' => ['starts_at', '9999-01-10T09:00'],
    'end in year 9999' => ['ends_at', '9999-01-12T21:00'],
    'end in 2038' => ['ends_at', '2038-01-20T00:00'],
    'start before 2000' => ['starts_at', '1999-12-31T23:59'],
]);

test('editing a flash sale to year 9999 is refused and leaves it unchanged', function () {
    $this->post(route('admin.flash-sales.store'), rangeFlashSalePayload())->assertSessionHasNoErrors();
    $sale = FlashSale::firstOrFail();
    $originalEnd = $sale->ends_at->toDateTimeString();

    $this->put(route('admin.flash-sales.update', $sale), rangeFlashSalePayload(['ends_at' => '9999-12-31T23:59']))
        ->assertSessionHasErrors(['ends_at' => StorableDateTime::MESSAGE]);

    expect($sale->refresh()->ends_at->toDateTimeString())->toBe($originalEnd);
});

test('dates inside the range, up to the last day of 2037, are accepted', function () {
    $this->post(route('admin.flash-sales.store'), rangeFlashSalePayload([
        'starts_at' => '2000-01-01T00:00',
        'ends_at' => '2037-12-31T23:59',
    ]))->assertSessionHasNoErrors();

    expect(FlashSale::count())->toBe(1);
});

test('coupon dates the database cannot store are refused', function (string $field) {
    $this->post(route('admin.coupons.store'), rangeCouponPayload([$field => '9999-06-01T10:00']))
        ->assertSessionHasErrors([$field => StorableDateTime::MESSAGE]);

    expect(Coupon::count())->toBe(0);
})->with(['starts_at', 'expires_at']);

test('a blog publish date the database cannot store is refused', function () {
    $this->post(route('admin.blogs.store'), rangeBlogPayload(['published_at' => '9999-06-01T10:00']))
        ->assertSessionHasErrors(['published_at' => StorableDateTime::MESSAGE]);

    expect(Blog::count())->toBe(0);
});

test('empty optional dates are still allowed', function () {
    $this->post(route('admin.coupons.store'), rangeCouponPayload(['starts_at' => '', 'expires_at' => '']))->assertSessionHasNoErrors();
    $this->post(route('admin.blogs.store'), rangeBlogPayload(['published_at' => '']))->assertSessionHasNoErrors();
});
