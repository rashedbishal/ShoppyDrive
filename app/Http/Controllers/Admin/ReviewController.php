<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Mail\ReviewApprovedMail;
use App\Models\Review;
use App\Services\LoyaltyPointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ReviewController extends Controller
{
    use RecordsAdminActions;

    public function __construct(
        private readonly LoyaltyPointsService $loyalty,
    ) {}

    public function index(Request $request): Response
    {
        $reviews = Review::with(['user:id,name,email', 'product:id,name,slug'])
            ->when($request->search, fn ($q) => $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$request->search}%"))
                ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$request->search}%")))
            ->when($request->status === 'approved', fn ($q) => $q->where('is_approved', true))
            ->when($request->status === 'pending', fn ($q) => $q->where('is_approved', false))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/reviews/index', [
            'reviews' => $reviews,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function approve(Review $review): RedirectResponse
    {
        // Guard before the update, not after — re-approving an
        // already-approved review (e.g. a double-click) must not resend.
        $wasAlreadyApproved = DB::transaction(function () use ($review): bool {
            $wasApproved = (bool) Review::whereKey($review->id)->lockForUpdate()->value('is_approved');

            $review->update(['is_approved' => true]);
            $this->loyalty->grantReviewBonus($review);

            if (! $wasApproved) {
                $this->recordReviewDecision($review, 'approved', $wasApproved);
            }

            return $wasApproved;
        });

        if (! $wasAlreadyApproved && $review->user?->email) {
            Mail::to($review->user->email)->send((new ReviewApprovedMail($review))->afterCommit());
        }

        return back()->with('success', 'Review approved.');
    }

    public function reject(Review $review): RedirectResponse
    {
        DB::transaction(function () use ($review): void {
            $wasApproved = (bool) Review::whereKey($review->id)->lockForUpdate()->value('is_approved');

            $review->update(['is_approved' => false]);

            // Rejecting a pending review changes no column, so the review's
            // last recorded decision tells a real rejection from a repeat.
            if ($wasApproved || $this->lastReviewDecision($review) !== 'rejected') {
                $this->recordReviewDecision($review, 'rejected', $wasApproved);
            }
        });

        return back()->with('success', 'Review rejected.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        DB::transaction(function () use ($review): void {
            $review->delete();

            $this->recordReviewDecision($review, 'deleted', $review->is_approved);
        });

        return back()->with('success', 'Review deleted.');
    }

    /**
     * Identifies the review without its text, or who wrote it.
     */
    private function recordReviewDecision(Review $review, string $decision, bool $wasApproved): void
    {
        $this->recordAdminAction($decision, "Review #{$review->id} {$decision}", $review, [
            'product_id' => $review->product_id,
            'rating' => $review->rating,
            'was_approved' => $wasApproved,
            'is_approved' => $decision === 'approved',
        ]);
    }

    private function lastReviewDecision(Review $review): ?string
    {
        return Activity::query()
            ->where('subject_type', $review->getMorphClass())
            ->where('subject_id', $review->id)
            ->whereIn('event', ['approved', 'rejected'])
            ->latest('id')
            ->value('event');
    }
}
