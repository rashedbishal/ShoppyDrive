<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly LoyaltyPointsService $loyalty,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $transactions = $user->pointTransactions()
            ->with('referredUser:id,name')
            ->latest()
            ->paginate(15);

        return Inertia::render('account/Referral', [
            'referralLink' => route('register', ['ref' => $user->referral_code]),
            'pointsBalance' => $user->points_balance,
            'referredCount' => $user->referredUsers()->count(),
            'transactions' => $transactions,
            'referralEnabled' => $this->referrals->isEnabled(),
            'inviteUnlocked' => $this->referrals->hasUnlockedInviteLink($user),
            'unlockOrderCount' => $this->referrals->unlockOrderCount(),
            'deliveredOrderCount' => $this->referrals->deliveredOrderCount($user),
            'pointsValue' => $this->loyalty->pointsValue(),
            'pointsPerReferral' => $this->referrals->rewardPoints(),
            'referralRewardDelayDays' => $this->referrals->rewardDelayDays(),
            'referralMinOrderAmount' => $this->referrals->minOrderAmountForDiscount(),
            'referralDiscountType' => (string) (Setting::find('referral_discount_type')?->value ?? 'percent'),
            'referralDiscountValue' => (float) (Setting::find('referral_discount_value')?->value ?? 0),
            'loyaltyEarnRateBdtPerPoint' => $this->loyalty->earnRateBdtPerPoint(),
            'loyaltyEarnDelayDays' => $this->loyalty->earnDelayDays(),
            'dailyLogin' => $this->loyalty->dailyLoginStatus($user),
            'dailyLoginBasePoints' => $this->loyalty->dailyLoginBasePoints(),
            'dailyLoginCapDays' => $this->loyalty->dailyLoginCapDays(),
        ]);
    }

    public function claimDailyLogin(Request $request): JsonResponse
    {
        return response()->json($this->loyalty->claimDailyLogin($request->user()));
    }
}
