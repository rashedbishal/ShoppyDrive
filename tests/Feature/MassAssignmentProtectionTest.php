<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

test('a customer cannot escalate their own role or verification status through the profile update form', function () {
    $user = User::factory()->create(['role' => UserRole::CUSTOMER, 'email_verified_at' => now()]);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Still Just A Customer',
        'email' => $user->email,
        'role' => 'admin',
        'has_password' => false,
        'email_verified_at' => null,
    ])->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->role)->toBe(UserRole::CUSTOMER);
    expect($user->has_password)->toBeTrue();
    expect($user->name)->toBe('Still Just A Customer');
});

test('a customer cannot submit a pre-approved or mis-attributed product review', function () {
    $author = User::factory()->create();
    $impersonated = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($author)->post(route('product.reviews.store', $product->slug), [
        'rating' => 5,
        'body' => 'Great product, exactly as described and arrived quickly.',
        'is_approved' => false,
        'user_id' => $impersonated->id,
        'order_id' => 999999,
    ])->assertRedirect();

    $review = Review::where('product_id', $product->id)->firstOrFail();
    expect($review->user_id)->toBe($author->id);
    expect($review->is_approved)->toBeTrue();
    expect($review->order_id)->toBeNull();
});

test('a customer cannot reassign an address to another account via the update form', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $address = Address::create([
        'user_id' => $owner->id, 'name' => 'Owner', 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
    ]);

    $this->actingAs($owner)->put(route('account.addresses.update', $address), [
        'name' => 'Owner', 'phone' => '01700000000',
        'address_line1' => 'House 1 Updated', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'user_id' => $other->id,
    ])->assertRedirect();

    expect($address->refresh()->user_id)->toBe($owner->id);
    expect($address->address_line1)->toBe('House 1 Updated');
});

test('a customer cannot update or view another customer\'s address at all', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $address = Address::create([
        'user_id' => $owner->id, 'name' => 'Owner', 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
    ]);

    $this->actingAs($attacker)->put(route('account.addresses.update', $address), [
        'name' => 'Hijacked', 'phone' => '01799999999',
        'address_line1' => 'Attacker House', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
    ])->assertForbidden();

    expect($address->refresh()->address_line1)->toBe('House 1');
});
