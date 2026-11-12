<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\MotherPasswordGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mother = createMotherAdmin(['password' => bcrypt('mother-secret')]);
});

function erasureOrder(User $user, OrderStatus $status, array $overrides = []): Order
{
    return Order::create(array_merge([
        'order_number' => 'ORD-ERASE-'.Str::upper(Str::random(6)),
        'user_id' => $user->id,
        'status' => $status,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01712345678'],
        'billing_address' => [],
    ], $overrides));
}

/** Both password steps, as the card sends them. */
function eraseCustomer(int $userId, string $first = 'mother-secret', string $second = 'mother-secret')
{
    test()->postJson(route('admin.settings.security.customer-erasure.confirm', $userId), ['password' => $first]);

    return test()->delete(route('admin.settings.security.customer-erasure.destroy', $userId), ['password' => $second]);
}

/**
 * A customer with a bit of everything: an unfinished order holding stock,
 * a delivered and refunded order with courier records and a return, a
 * coupon use, reviews with photos, an avatar, an address, a cart, a
 * wishlist, a newsletter signup, referral points, and someone they referred.
 *
 * @return array<string, mixed>
 */
function customerWithEverything(): array
{
    Storage::disk('public')->put('avatars/erase-me.webp', 'x');
    Storage::disk('public')->put('reviews/photo-1.webp', 'x');

    $user = User::factory()->create(['email' => 'erase@example.com', 'avatar' => '/storage/avatars/erase-me.webp']);
    $referred = User::factory()->create(['referred_by_id' => $user->id]);
    $product = Product::factory()->create(['stock_quantity' => 7]);
    $coupon = Coupon::factory()->create(['used_count' => 1]);

    $unfinished = erasureOrder($user, OrderStatus::PROCESSING);
    OrderItem::create([
        'order_id' => $unfinished->id, 'product_id' => $product->id, 'product_name' => $product->name, 'sku' => 'ERASE-1',
        'price' => 100, 'quantity' => 3, 'subtotal' => 300, 'product_snapshot' => ['name' => $product->name],
    ]);

    $delivered = erasureOrder($user, OrderStatus::DELIVERED, ['payment_status' => 'paid', 'coupon_id' => $coupon->id, 'coupon_code' => $coupon->code]);
    DB::table('coupon_uses')->insert(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $delivered->id, 'discount_amount' => 10, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('payments')->insert(['order_id' => $delivered->id, 'method' => 'cod', 'amount' => 560, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
    $return = DB::table('return_requests')->insertGetId(['order_id' => $delivered->id, 'user_id' => $user->id, 'reason' => 'Damaged', 'items' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('order_refunds')->insert(['order_id' => $delivered->id, 'return_request_id' => $return, 'source' => 'return', 'amount' => 100, 'created_at' => now()]);
    DB::table('courier_delivery_evidence')->insert(['order_id' => $delivered->id, 'provider' => 'steadfast', 'evidence_type' => 'status', 'source' => 'webhook', 'created_at' => now()]);
    DB::table('reviews')->insert([
        'product_id' => $product->id, 'user_id' => $user->id, 'order_id' => $delivered->id, 'rating' => 5,
        'body' => 'Great', 'images' => json_encode(['/storage/reviews/photo-1.webp']), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('referral_point_transactions')->insert(['user_id' => $user->id, 'type' => 'earn', 'points' => 10, 'created_at' => now(), 'updated_at' => now()]);

    Address::create(['user_id' => $user->id, 'name' => 'Test', 'phone' => '01712345678', 'address_line1' => 'Road 1', 'city' => 'Dhaka', 'state' => 'Dhaka']);
    NewsletterSubscriber::create(['email' => 'erase@example.com', 'token' => Str::random(64), 'is_active' => true, 'subscribed_at' => now()]);

    return compact('user', 'referred', 'product', 'coupon', 'unfinished', 'delivered');
}

test('the mother admin can permanently delete a customer and everything tied to them', function () {
    $c = customerWithEverything();
    $userId = $c['user']->id;
    $orderIds = [$c['unfinished']->id, $c['delivered']->id];
    $other = User::factory()->create();
    $otherOrder = erasureOrder($other, OrderStatus::DELIVERED);

    actingAs($this->mother, 'admin');
    eraseCustomer($userId)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(User::withTrashed()->find($userId))->toBeNull()
        ->and(Order::withTrashed()->whereKey($orderIds)->exists())->toBeFalse()
        ->and(DB::table('order_items')->whereIn('order_id', $orderIds)->exists())->toBeFalse()
        ->and(DB::table('payments')->whereIn('order_id', $orderIds)->exists())->toBeFalse()
        ->and(DB::table('order_refunds')->whereIn('order_id', $orderIds)->exists())->toBeFalse()
        ->and(DB::table('return_requests')->where('user_id', $userId)->exists())->toBeFalse()
        ->and(DB::table('coupon_uses')->where('user_id', $userId)->exists())->toBeFalse()
        ->and(DB::table('courier_delivery_evidence')->whereIn('order_id', $orderIds)->exists())->toBeFalse()
        ->and(DB::table('reviews')->where('user_id', $userId)->exists())->toBeFalse()
        ->and(DB::table('referral_point_transactions')->where('user_id', $userId)->exists())->toBeFalse()
        ->and(DB::table('addresses')->where('user_id', $userId)->exists())->toBeFalse()
        ->and(NewsletterSubscriber::where('email', 'erase@example.com')->exists())->toBeFalse();

    // Files leave the disk.
    expect(Storage::disk('public')->exists('avatars/erase-me.webp'))->toBeFalse()
        ->and(Storage::disk('public')->exists('reviews/photo-1.webp'))->toBeFalse();

    // The unfinished order's 3 units went back; the coupon use was released.
    expect($c['product']->refresh()->stock_quantity)->toBe(10)
        ->and($c['coupon']->refresh()->used_count)->toBe(0);

    // The customer they referred keeps their account, just unlinked; others are untouched.
    expect($c['referred']->refresh()->referred_by_id)->toBeNull()
        ->and(User::find($other->id))->not->toBeNull()
        ->and(Order::find($otherOrder->id))->not->toBeNull();

    // A minimal record remains, with no personal details.
    $entry = Activity::where('event', 'customer.erased')->sole();
    expect($entry->causer_id)->toBe($this->mother->id)
        ->and($entry->properties['user_id'])->toBe($userId)
        ->and($entry->properties['orders'])->toBe(2)
        ->and(json_encode($entry->toArray()))->not->toContain('erase@example.com');
});

test('the summary shows what would be removed, without changing anything', function () {
    $c = customerWithEverything();

    actingAs($this->mother, 'admin')
        ->getJson(route('admin.settings.security.customer-erasure.summary', $c['user']->id))
        ->assertOk()
        ->assertJson(['orders' => 2, 'unfinished_orders' => 1, 'payments' => 1, 'refunds' => 1, 'returns' => 1, 'reviews' => 1, 'addresses' => 1, 'files' => 2]);

    expect(User::find($c['user']->id))->not->toBeNull();
});

test('search finds customers by name, email, phone or number', function () {
    $user = User::factory()->create(['name' => 'Rafiq Islam', 'email' => 'rafiq@example.com', 'phone' => '01799999999']);
    User::factory()->create(['name' => 'Someone Else']);

    foreach (['Rafiq', 'rafiq@example', '0179999', (string) $user->id] as $term) {
        actingAs($this->mother, 'admin')
            ->getJson(route('admin.settings.security.customer-erasure.search', ['q' => $term]))
            ->assertOk()
            ->assertJsonPath('customers.0.id', $user->id);
    }
});

test('deleting without the first password confirmation is refused', function () {
    $user = User::factory()->create();

    actingAs($this->mother, 'admin')
        ->delete(route('admin.settings.security.customer-erasure.destroy', $user->id), ['password' => 'mother-secret'])
        ->assertSessionHasErrors('confirmation');

    expect(User::find($user->id))->not->toBeNull();
});

test('a wrong password at either step is refused and nothing is deleted', function () {
    $user = User::factory()->create();
    actingAs($this->mother, 'admin');

    $this->postJson(route('admin.settings.security.customer-erasure.confirm', $user->id), ['password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The password is incorrect.');

    eraseCustomer($user->id, second: 'wrong')->assertSessionHasErrors('password');

    expect(User::find($user->id))->not->toBeNull();
});

test('the confirmation is single-use and bound to that customer', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    actingAs($this->mother, 'admin');

    $this->postJson(route('admin.settings.security.customer-erasure.confirm', $first->id), ['password' => 'mother-secret'])->assertOk();

    // Confirmed for the first customer, not the second.
    $this->delete(route('admin.settings.security.customer-erasure.destroy', $second->id), ['password' => 'mother-secret'])
        ->assertSessionHasErrors('confirmation');

    // That attempt spent the confirmation, so the first customer needs a fresh one too.
    $this->delete(route('admin.settings.security.customer-erasure.destroy', $first->id), ['password' => 'mother-secret'])
        ->assertSessionHasErrors('confirmation');

    expect(User::whereKey([$first->id, $second->id])->count())->toBe(2);
});

test('an expired confirmation is refused', function () {
    $user = User::factory()->create();
    actingAs($this->mother, 'admin');

    $this->postJson(route('admin.settings.security.customer-erasure.confirm', $user->id), ['password' => 'mother-secret'])->assertOk();
    $this->travel(6)->minutes();

    $this->delete(route('admin.settings.security.customer-erasure.destroy', $user->id), ['password' => 'mother-secret'])
        ->assertSessionHasErrors('confirmation');

    expect(User::find($user->id))->not->toBeNull();
});

test('wrong passwords share the mother-password lockout', function () {
    $user = User::factory()->create();
    actingAs($this->mother, 'admin');

    foreach (range(1, MotherPasswordGate::MAX_FAILURES) as $ignored) {
        $this->postJson(route('admin.settings.security.customer-erasure.confirm', $user->id), ['password' => 'wrong']);
    }

    // Even the right password is refused while locked.
    $this->postJson(route('admin.settings.security.customer-erasure.confirm', $user->id), ['password' => 'mother-secret'])
        ->assertStatus(422);

    expect(User::find($user->id))->not->toBeNull();
});

test('a delivered order does not put stock back', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $order = erasureOrder($user, OrderStatus::DELIVERED);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'sku' => 'ERASE-2',
        'price' => 100, 'quantity' => 2, 'subtotal' => 200, 'product_snapshot' => ['name' => $product->name],
    ]);

    actingAs($this->mother, 'admin');
    eraseCustomer($user->id)->assertSessionHasNoErrors();

    expect($product->refresh()->stock_quantity)->toBe(5);
});

test('admins other than the mother admin cannot search, preview, confirm or delete', function () {
    $user = User::factory()->create();
    $admin = createAdmin(['password' => bcrypt('admin-secret')]);
    actingAs($admin, 'admin');

    $this->getJson(route('admin.settings.security.customer-erasure.search'))->assertForbidden();
    $this->getJson(route('admin.settings.security.customer-erasure.summary', $user->id))->assertForbidden();
    $this->postJson(route('admin.settings.security.customer-erasure.confirm', $user->id), ['password' => 'admin-secret'])->assertForbidden();
    $this->delete(route('admin.settings.security.customer-erasure.destroy', $user->id), ['password' => 'admin-secret'])->assertForbidden();

    expect(User::find($user->id))->not->toBeNull();
});
