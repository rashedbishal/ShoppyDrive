<?php

declare(strict_types=1);

/*
 * FE-012: one shared cleaner (App\Support\BangladeshiPhone) turns Bangla
 * digits, +880 / 00880 / 880 prefixes, spaces and dashes into 01XXXXXXXXX
 * on every form that takes a phone number, and profile/saved-address forms
 * now apply the same rule checkout does.
 */

use App\Models\Cart;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\BangladeshiPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

test('the cleaner turns every common way of writing a number into the local form', function (string $input) {
    expect(BangladeshiPhone::normalize($input))->toBe('01712345678');
})->with([
    'Bangla digits' => '০১৭১২৩৪৫৬৭৮',
    'country code' => '+8801712345678',
    'country code, spaced and dashed' => '+880 1712-345678',
    'international 00 prefix' => '008801712345678',
    'country code without +' => '8801712345678',
    'brackets and dots' => '(017) 1234.5678',
    'already local' => '01712345678',
]);

test('the cleaner never reshapes letters or other junk into a valid number', function (string $input) {
    expect(BangladeshiPhone::isValid(BangladeshiPhone::normalize($input)))->toBeFalse();
})->with(['0171234567a', 'call me 01712345678', '+1 555 0100', '880123']);

function phoneCheckoutPayload(Product $product, string $phone): array
{
    return [
        'name' => 'Ayesha', 'email' => 'ayesha@example.com', 'phone' => $phone,
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'save_address' => false, 'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => (float) $product->price]],
    ];
}

test('checkout accepts a number typed in Bangla digits or with +880 and stores the local form', function (string $phone) {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();
    $product = Product::factory()->create(['price' => 100]);

    $this->actingAs($user)->post(route('checkout.store'), phoneCheckoutPayload($product, $phone))
        ->assertSessionHasNoErrors();

    expect(Order::firstOrFail()->shipping_address['phone'])->toBe('01712345678');
})->with(['০১৭১২৩৪৫৬৭৮', '+880 1712-345678']);

test('a landing page order accepts Bangla digits', function () {
    $product = Product::factory()->create(['price' => 100]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    $this->post(route('lp.order', $page->slug), [
        'name' => 'Ayesha', 'phone' => '০১৭১২৩৪৫৬৭৮', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'quantity' => 1, 'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    expect(Order::firstOrFail()->shipping_address['phone'])->toBe('01712345678');
});

test('a saved address stores the cleaned number and rejects one checkout would refuse', function () {
    $user = User::factory()->create();
    $address = ['name' => 'Rahim', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'];

    $this->actingAs($user)->post(route('account.addresses.store'), [...$address, 'phone' => '+8801712345678'])
        ->assertSessionHasNoErrors();
    expect($user->addresses()->value('phone'))->toBe('01712345678');

    $this->actingAs($user)->post(route('account.addresses.store'), [...$address, 'phone' => '12345'])
        ->assertSessionHasErrors(['phone' => BangladeshiPhone::INVALID_MESSAGE]);
    expect($user->addresses()->count())->toBe(1);
});

test('the profile phone stays optional, is cleaned, and rejects an invalid number', function () {
    $user = User::factory()->create();
    $profile = ['name' => $user->name, 'email' => $user->email];

    $this->actingAs($user)->post(route('account.profile.update'), [...$profile, 'phone' => '০১৮১২৩৪৫৬৭৮'])->assertSessionHasNoErrors();
    expect($user->refresh()->phone)->toBe('01812345678');

    $this->actingAs($user)->post(route('account.profile.update'), [...$profile, 'phone' => 'not a phone'])->assertSessionHasErrors('phone');
    expect($user->refresh()->phone)->toBe('01812345678');

    $this->actingAs($user)->post(route('account.profile.update'), [...$profile, 'phone' => ''])->assertSessionHasNoErrors();
});

test('order tracking finds the order when the phone is typed in Bangla digits', function () {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();
    $product = Product::factory()->create(['price' => 100]);
    $this->actingAs($user)->post(route('checkout.store'), phoneCheckoutPayload($product, '01712345678'));
    $order = Order::firstOrFail();
    auth()->logout();

    $this->get(route('order.track', ['order_number' => $order->order_number, 'phone' => '০১৭১২৩৪৫৬৭৮']))
        ->assertInertia(fn ($page) => $page->where('error', null)->where('order.order_number', $order->order_number));
});
