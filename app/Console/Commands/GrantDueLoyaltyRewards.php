<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rewards:grant-due')]
#[Description('Grant delayed loyalty/referral points that have cleared their post-delivery wait (covers the return window) — run on a schedule, not inline during checkout.')]
class GrantDueLoyaltyRewards extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReferralService $referrals, LoyaltyPointsService $loyalty): int
    {
        $referralCount = $referrals->grantDueReferralRewards();
        $this->info("Granted {$referralCount} referral reward(s).");

        $orderCount = $loyalty->grantDueOrderEarnPoints();
        $this->info("Granted order-spend points for {$orderCount} order(s).");

        return self::SUCCESS;
    }
}
