<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\LoyaltyPointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
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
        $review->update(['is_approved' => true]);
        $this->loyalty->grantReviewBonus($review);

        return back()->with('success', 'Review approved.');
    }

    public function reject(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => false]);

        return back()->with('success', 'Review rejected.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}
