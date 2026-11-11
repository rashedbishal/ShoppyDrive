<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use App\Services\ReviewImageStorage;
use App\Support\ReviewImageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReviewController extends Controller
{
    public function __construct(
        private readonly LoyaltyPointsService $loyalty,
        private readonly ReviewImageStorage $images,
    ) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            ...$this->imageRules(),
        ], $this->imageMessages());

        if (! $request->user()->hasOrderedProduct($product->id)) {
            return back()->withErrors(['review' => 'Only customers who have ordered this product can review it.']);
        }

        // Photos are resized and saved before the review row is written, so the
        // user-row lock below is never held while images are being encoded. If
        // the review is not created after all, they are removed again.
        try {
            $storedImages = $this->images->store(array_values($request->file('images') ?? []));
        } catch (Throwable $exception) {
            Log::warning('[ReviewImages] Could not store review photos', ['message' => $exception->getMessage()]);

            return back()->withErrors(['images' => 'Your photos could not be saved. Please try again, or submit the review without them.']);
        }

        // BIZ-036: the user-row lock makes two quick submits take turns, so the
        // second one sees the first one's review.
        try {
            $review = DB::transaction(function () use ($request, $product, $validated, $storedImages): ?Review {
                User::whereKey($request->user()->id)->lockForUpdate()->first();

                if ($request->user()->hasReviewedProduct($product->id)) {
                    return null;
                }

                return $this->createReview($request, $product, $validated, $storedImages);
            });
        } catch (Throwable $exception) {
            $this->images->delete($storedImages);

            throw $exception;
        }

        if ($review === null) {
            $this->images->delete($storedImages);

            return back()->withErrors(['review' => 'You have already reviewed this product.']);
        }

        $this->loyalty->grantReviewBonus($review);

        return back()->with('success', 'Your review has been submitted and is awaiting approval. Thank you!');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $storedImages
     */
    private function createReview(Request $request, Product $product, array $validated, array $storedImages): Review
    {
        return Review::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_id' => $this->resolveVerifiedPurchaseOrderId($request->user()->id, $product->id),
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
            'images' => $storedImages === [] ? null : $storedImages,
            // FSA-10: every new review starts pending admin moderation
            // (Admin\ReviewController::approve()/reject()) instead of
            // publishing instantly. grantReviewBonus() already no-ops on a
            // non-approved review, so the bonus is granted only once an
            // admin approves it, not here.
            'is_approved' => false,
        ]);
    }

    /**
     * Photo rules follow the admin's setting: when uploads are off, any photo
     * is refused; when on, at most the admin's number, each a JPG/PNG/WebP up
     * to 5 MB and between 200 and 10000 px on each side (ReviewImageSettings).
     *
     * @return array<string, list<string>>
     */
    private function imageRules(): array
    {
        if (! ReviewImageSettings::enabled()) {
            return ['images' => ['prohibited']];
        }

        return [
            'images' => ['nullable', 'array', 'max:'.ReviewImageSettings::maxPerReview()],
            'images.*' => [
                'file',
                'image',
                'mimes:'.implode(',', ReviewImageSettings::ALLOWED_EXTENSIONS),
                'max:'.ReviewImageSettings::MAX_FILE_KILOBYTES,
                'dimensions:min_width='.ReviewImageSettings::MIN_SIDE_PIXELS.',min_height='.ReviewImageSettings::MIN_SIDE_PIXELS
                    .',max_width='.ReviewImageSettings::MAX_SIDE_PIXELS.',max_height='.ReviewImageSettings::MAX_SIDE_PIXELS,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function imageMessages(): array
    {
        $megabytes = ReviewImageSettings::MAX_FILE_KILOBYTES / 1024;

        return [
            'images.prohibited' => 'Photo uploads are not available for reviews right now.',
            'images.max' => 'You can attach up to :max photo(s) to a review.',
            'images.*.uploaded' => "A photo could not be uploaded. Photos must be {$megabytes} MB or smaller.",
            'images.*.max' => "Each photo must be {$megabytes} MB or smaller.",
            'images.*.image' => 'Photos must be JPG, PNG or WebP images.',
            'images.*.mimes' => 'Photos must be JPG, PNG or WebP images.',
            'images.*.dimensions' => 'Each photo must be at least '.ReviewImageSettings::MIN_SIDE_PIXELS.' × '.ReviewImageSettings::MIN_SIDE_PIXELS.' pixels, and no more than '.ReviewImageSettings::MAX_SIDE_PIXELS.' pixels on a side.',
        ];
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
