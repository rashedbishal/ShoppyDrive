<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $transactions = $user->pointTransactions()
            ->with('referredUser:id,name')
            ->latest()
            ->paginate(15);

        return Inertia::render('account/Referral', [
            'referralCode' => $user->referral_code,
            'referralLink' => route('register', ['ref' => $user->referral_code]),
            'pointsBalance' => $user->points_balance,
            'referredCount' => $user->referredUsers()->count(),
            'transactions' => $transactions,
            'referralEnabled' => (bool) (Setting::find('referral_enabled')?->value ?? false),
            'pointsValue' => (float) (Setting::find('referral_points_value')?->value ?? 0),
            'pointsPerReferral' => (int) (Setting::find('referral_points_per_referral')?->value ?? 0),
        ]);
    }
}
