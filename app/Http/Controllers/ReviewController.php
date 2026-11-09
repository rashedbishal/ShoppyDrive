<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function __construct(
        private readonly LoyaltyPointsService $loyalty,
    ) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if (! $request->user()->hasOrderedProduct($product->id)) {
            return back()->withErrors(['review' => 'Only customers who have ordered this product can review it.']);
        }

        // BIZ-036: the user-row lock makes two quick submits take turns, so the
        // second one sees the first one's review.
        $review = DB::transaction(function () use ($request, $product, $validated): ?Review {
            User::whereKey($request->user()->id)->lockForUpdate()->first();

            $alreadyReviewed = Review::where('product_id', $product->id)
                ->where('user_id', $request->user()->id)
                ->exists();

            if ($alreadyReviewed) {
                return null;
            }

            return Review::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'order_id' => $this->resolveVerifiedPurchaseOrderId($request->user()->id, $product->id),
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'],
                // FSA-10: every new review starts pending admin moderation
                // (Admin\ReviewController::approve()/reject()) instead of
                // publishing instantly. grantReviewBonus() already no-ops on a
                // non-approved review, so the bonus is granted only once an
                // admin approves it, not here.
                'is_approved' => false,
            ]);
        });

        if ($review === null) {
            return back()->withErrors(['review' => 'You have already reviewed this product.']);
        }

        $this->loyalty->grantReviewBonus($review);

        return back()->with('success', 'Your review has been submitted and is awaiting approval. Thank you!');
    }

    /**
     * The id of this reviewer's own delivered order that actually contained
     * this product, if any — what makes a review a "verified purchase"
     * eligible for the review bonus. Null for a review with no matching
     * order (the reviewer never bought this product here, or their order
     * hasn't been delivered yet).
     */
    private function resolveVerifiedPurchaseOrderId(int $userId, int $productId): ?int
    {
        return OrderItem::where('product_id', $productId)
            ->whereHas('order', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('status', OrderStatus::DELIVERED))
            ->value('order_id');
    }
}
