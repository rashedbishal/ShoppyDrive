<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * FSA-18: ProductController::show()'s rating breakdown, average rating and
 * review count were all computed from $product->reviews — a collection
 * capped at the 50 *most recent* approved reviews for display purposes —
 * so every one of them silently went wrong for any product with more than
 * 50 approved reviews. The displayed review list itself stays capped at 50
 * (unchanged, intentional); the aggregates below must not be.
 */
function approvedReview(Product $product, User $user, int $rating, ?CarbonInterface $createdAt = null): Review
{
    $review = Review::create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'rating' => $rating,
        'body' => 'A perfectly adequate review body for test fixture purposes.',
        'is_approved' => true,
    ]);

    if ($createdAt !== null) {
        $review->forceFill(['created_at' => $createdAt])->saveQuietly();
    }

    return $review;
}

function unapprovedReview(Product $product, User $user, int $rating): Review
{
    return Review::create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'rating' => $rating,
        'body' => 'Pending moderation, must never affect any aggregate.',
        'is_approved' => false,
    ]);
}

test('exactly 50 approved reviews are all counted correctly', function (): void {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    for ($i = 0; $i < 30; $i++) {
        approvedReview($product, $user, 5);
    }
    for ($i = 0; $i < 20; $i++) {
        approvedReview($product, $user, 1);
    }

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ratingBreakdown.5', 30)
            ->where('ratingBreakdown.1', 20)
            ->where('ratingBreakdown.4', 0)
            ->where('ratingBreakdown.3', 0)
            ->where('ratingBreakdown.2', 0)
            ->where('product.reviews_count', 50)
            ->where('product.average_rating', fn ($value) => round((float) $value, 4) === round((30 * 5 + 20 * 1) / 50, 4)));
});

test('51 approved reviews with mixed ratings are all counted, past the old 50-review cap', function (): void {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    for ($i = 0; $i < 30; $i++) {
        approvedReview($product, $user, 5);
    }
    for ($i = 0; $i < 21; $i++) {
        approvedReview($product, $user, 1);
    }

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ratingBreakdown.5', 30)
            ->where('ratingBreakdown.1', 21)
            ->where('product.reviews_count', 51)
            ->where('product.average_rating', fn ($value) => round((float) $value, 4) === round((30 * 5 + 21 * 1) / 51, 4)));
});

test('when the newest 50 reviews have a different distribution than older ones, every approved review still counts', function (): void {
    // Reproduces the exact FSA-18 bug: ->latest()->limit(50) would keep
    // the 6 newest (1-star) plus 44 of the 45 older 5-star reviews,
    // excluding the single oldest 5-star review and under-reporting both
    // the total and the 5-star count.
    $product = Product::factory()->create();
    $user = User::factory()->create();

    for ($i = 0; $i < 45; $i++) {
        approvedReview($product, $user, 5, now()->subDays(100)->addSeconds($i));
    }
    for ($i = 0; $i < 6; $i++) {
        approvedReview($product, $user, 1, now()->subMinutes($i));
    }

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ratingBreakdown.5', 45)
            ->where('ratingBreakdown.1', 6)
            ->where('product.reviews_count', 51)
            // The displayed review list itself is still capped at 50 —
            // unchanged, intentional, not part of this fix.
            ->has('reviews', 50));
});

test('unapproved reviews are excluded from every rating aggregate', function (): void {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    approvedReview($product, $user, 5);
    approvedReview($product, $user, 5);
    unapprovedReview($product, $user, 1);
    unapprovedReview($product, $user, 1);
    unapprovedReview($product, $user, 1);

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ratingBreakdown.5', 2)
            ->where('ratingBreakdown.1', 0)
            ->where('product.reviews_count', 2)
            ->where('product.average_rating', fn ($value) => round((float) $value, 4) === 5.0));
});

test('products with no approved reviews report null average and zero count, not a fabricated value', function (): void {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    unapprovedReview($product, $user, 1);

    // FSA-18: average_rating must be null (not 0 or missing) and the count
    // zero, so ProductDetail.tsx shows "No reviews yet" rather than a rating
    // (its old made-up '5.0' fallback was removed on 2026-11-11).
    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.average_rating', null)
            ->where('product.reviews_count', 0)
            ->where('ratingBreakdown.5', 0)
            ->where('ratingBreakdown.4', 0)
            ->where('ratingBreakdown.3', 0)
            ->where('ratingBreakdown.2', 0)
            ->where('ratingBreakdown.1', 0));
});
