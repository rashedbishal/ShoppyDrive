<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Http\Middleware\RedirectAdminToDashboard;
use App\Mail\ReviewApprovedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * FSA-10: a submitted review must never publish instantly — it starts
 * pending admin moderation (Admin\ReviewController::approve()/reject()),
 * and an unverified account must never create one at all. The review-bonus
 * ("verified purchase") eligibility rule and ReviewController's
 * user_id/order_id mass-assignment protection are unrelated and unchanged.
 */
function reviewPayload(array $overrides = []): array
{
    return array_merge([
        'rating' => 5,
        'body' => 'Great product, exactly as described and arrived quickly.',
    ], $overrides);
}

function deliveredOrderWithProduct(User $user, Product $product): Order
{
    $order = Order::create([
        'order_number' => 'ORD-REVIEW-'.uniqid(),
        'user_id' => $user->id,
        'status' => OrderStatus::DELIVERED,
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'subtotal' => 1000,
        'shipping_cost' => 120,
        'discount' => 0,
        'tax' => 0,
        'total' => 1120,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
        'delivered_at' => now()->subDay(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 1000,
        'quantity' => 1,
        'subtotal' => 1000,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    return $order;
}

test('a verified customer submitting a review gets is_approved false', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    deliveredOrderWithProduct($user, $product);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertRedirect();

    $review = Review::where('product_id', $product->id)->firstOrFail();
    expect($review->is_approved)->toBeFalse();
    expect($review->user_id)->toBe($user->id);
});

test('an unverified customer cannot submit a review at all', function () {
    $user = User::factory()->unverified()->create();
    $product = Product::factory()->create();

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertRedirect(route('verification.notice'));

    expect(Review::where('product_id', $product->id)->exists())->toBeFalse();
});

test('a pending review is excluded from the public product page list and rating breakdown', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    deliveredOrderWithProduct($user, $product);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload(['rating' => 1]))
        ->assertRedirect();

    $response = actingAs(User::factory()->create())->get(route('product.show', $product->slug));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('reviews', [])
        ->where('ratingBreakdown.1', 0));
});

test('a verified-purchase review remains pending and grants no bonus points before approval', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $product = Product::factory()->create();
    deliveredOrderWithProduct($user, $product);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertRedirect();

    $review = Review::where('product_id', $product->id)->firstOrFail();
    expect($review->is_approved)->toBeFalse();
    expect($review->order_id)->not->toBeNull();
    expect($user->refresh()->points_balance)->toBe(0);
});

test('admin approval publishes the review and grants the eligible bonus exactly once', function () {
    Mail::fake();
    $this->admin = createAdmin();
    $user = User::factory()->create(['points_balance' => 0]);
    $product = Product::factory()->create();
    deliveredOrderWithProduct($user, $product);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload());
    $review = Review::where('product_id', $product->id)->firstOrFail();

    // RedirectCustomerFromAdmin bounces every /admin route to `home` while
    // the web guard still has a logged-in customer, regardless of admin
    // auth — the same guard-switch this tracker's FSA-06/FSA-07 notes
    // already worked around.
    auth('web')->logout();

    actingAs($this->admin, 'admin')->patch(route('admin.reviews.approve', $review))->assertRedirect();
    // A double-click/retry must not re-send the mail or re-grant the bonus.
    actingAs($this->admin, 'admin')->patch(route('admin.reviews.approve', $review))->assertRedirect();

    $review->refresh();
    expect($review->is_approved)->toBeTrue();
    Mail::assertQueued(ReviewApprovedMail::class, 1);

    // RedirectAdminToDashboard (global on the web group) bounces storefront
    // requests to /admin/dashboard while the admin guard is still
    // authenticated — same guard-switch concern, the other direction. A
    // plain guard logout doesn't reliably clear it for the *next* simulated
    // request here (the guard's session reference goes stale across test
    // HTTP calls), so this disables the one middleware under test instead —
    // the redirect it performs is covered by its own dedicated test, not
    // this one.
    $this->withoutMiddleware(RedirectAdminToDashboard::class);
    $response = actingAs(User::factory()->create())->get(route('product.show', $product->slug));
    $response->assertInertia(fn (Assert $page) => $page
        ->has('reviews', 1)
        ->where('ratingBreakdown.5', 1));
});

test('admin rejection keeps the review unpublished', function () {
    $this->admin = createAdmin();
    $user = User::factory()->create();
    $product = Product::factory()->create();
    deliveredOrderWithProduct($user, $product);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload());
    $review = Review::where('product_id', $product->id)->firstOrFail();

    auth('web')->logout();
    actingAs($this->admin, 'admin')->patch(route('admin.reviews.reject', $review))->assertRedirect();

    expect($review->refresh()->is_approved)->toBeFalse();

    $this->withoutMiddleware(RedirectAdminToDashboard::class);
    $response = actingAs(User::factory()->create())->get(route('product.show', $product->slug));
    $response->assertInertia(fn (Assert $page) => $page->where('reviews', []));
});

test('the admin dashboard pending_reviews count reflects a newly submitted review', function () {
    $this->admin = createAdmin();
    $user = User::factory()->create();
    $product = Product::factory()->create();
    deliveredOrderWithProduct($user, $product);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload());

    auth('web')->logout();
    actingAs($this->admin, 'admin')->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.pending_reviews', 1));
});

// ── Only customers who ordered the product can review it ────────────────

function orderContainingProduct(User $user, Product $product, OrderStatus $status): Order
{
    $order = deliveredOrderWithProduct($user, $product);
    $order->updateQuietly(['status' => $status]);

    return $order;
}

test('a customer who never ordered the product cannot review it', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertSessionHasErrors(['review' => 'Only customers who have ordered this product can review it.']);

    expect(Review::count())->toBe(0);
});

test('an order for a different product does not allow a review', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    deliveredOrderWithProduct($user, Product::factory()->create());

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertSessionHasErrors('review');

    expect(Review::count())->toBe(0);
});

test('another customer\'s order does not allow a review', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    deliveredOrderWithProduct(User::factory()->create(), $product);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertSessionHasErrors('review');

    expect(Review::count())->toBe(0);
});

test('a cancelled order does not allow a review', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    orderContainingProduct($user, $product, OrderStatus::CANCELLED);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertSessionHasErrors('review');

    expect(Review::count())->toBe(0);
});

test('one cancelled order does not block a customer who also has a live order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    orderContainingProduct($user, $product, OrderStatus::CANCELLED);
    orderContainingProduct($user, $product, OrderStatus::PROCESSING);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertSessionHasNoErrors();

    expect(Review::count())->toBe(1);
});

test('any order that was not cancelled allows a review', function (OrderStatus $status) {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    orderContainingProduct($user, $product, $status);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload())
        ->assertSessionHasNoErrors();

    expect(Review::where('user_id', $user->id)->count())->toBe(1);
})->with([
    'pending' => [OrderStatus::PENDING],
    'processing' => [OrderStatus::PROCESSING],
    'shipped' => [OrderStatus::SHIPPED],
    'delivered' => [OrderStatus::DELIVERED],
    'refunded' => [OrderStatus::REFUNDED],
]);

test('a review before delivery is allowed but is not a verified purchase', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    orderContainingProduct($user, $product, OrderStatus::PENDING);

    actingAs($user)->post(route('product.reviews.store', $product->slug), reviewPayload());

    expect(Review::where('user_id', $user->id)->sole()->order_id)->toBeNull();
});

test('the product page tells the form who may review', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $buyer = User::factory()->create();
    orderContainingProduct($buyer, $product, OrderStatus::PENDING);

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('canReview', false));

    actingAs(User::factory()->create())->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('canReview', false));

    actingAs($buyer)->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('canReview', true));
});
