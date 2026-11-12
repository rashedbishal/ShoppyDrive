import { Head, router } from '@inertiajs/react';
import { Calendar, Check, Copy, Gift, Lock, Users } from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { getXsrfToken } from '@/lib/csrf';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { PaginatedData, PointTransaction } from '@/types';

type DailyLoginStatus = {
    streakDay: number;
    claimedToday: boolean;
    points: number;
};

interface ReferralProps {
    referralCode: string;
    referralLink: string;
    pointsBalance: number;
    referredCount: number;
    transactions: PaginatedData<PointTransaction>;
    referralEnabled: boolean;
    inviteUnlocked: boolean;
    unlockOrderCount: number;
    deliveredOrderCount: number;
    pointsValue: number;
    pointsPerReferral: number;
    referralRewardDelayDays: number;
    referralMinOrderAmount: number;
    referralDiscountType: 'percent' | 'fixed';
    referralDiscountValue: number;
    loyaltyEarnRateBdtPerPoint: number;
    loyaltyEarnDelayDays: number;
    dailyLogin: DailyLoginStatus;
    dailyLoginBasePoints: number;
    dailyLoginCapDays: number;
}

const TYPE_LABEL: Record<PointTransaction['type'], string> = {
    earn_order: 'Earned from order',
    earn_referral: 'Referral reward',
    earn_registration: 'Signup bonus',
    earn_review: 'Review bonus',
    earn_daily_login: 'Daily login',
    redeem: 'Redeemed',
    revoke: 'Reversed',
    refund: 'Refunded',
    revoke_order: 'Reversed (order refunded)',
};

const TYPE_VARIANT: Record<
    PointTransaction['type'],
    'default' | 'secondary' | 'destructive'
> = {
    earn_order: 'default',
    earn_referral: 'default',
    earn_registration: 'default',
    earn_review: 'default',
    earn_daily_login: 'default',
    refund: 'default',
    redeem: 'secondary',
    revoke: 'destructive',
    revoke_order: 'destructive',
};

export default function Referral({
    referralCode,
    referralLink,
    pointsBalance,
    referredCount,
    transactions,
    referralEnabled,
    inviteUnlocked,
    unlockOrderCount,
    deliveredOrderCount,
    pointsValue,
    pointsPerReferral,
    referralRewardDelayDays,
    referralMinOrderAmount,
    referralDiscountType,
    referralDiscountValue,
    dailyLogin,
    dailyLoginCapDays,
}: ReferralProps): React.JSX.Element {
    const [copied, setCopied] = useState(false);
    const [login, setLogin] = useState(dailyLogin);
    const [claiming, setClaiming] = useState(false);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(referralLink);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard API unavailable — nothing more we can do here.
        }
    };

    const handleClaimDailyLogin = async () => {
        if (login.claimedToday || claiming) {
            return;
        }

        setClaiming(true);

        try {
            const response = await fetch(
                route('account.referral.daily-login'),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            const result = (await response.json()) as DailyLoginStatus & {
                awarded: boolean;
            };

            setLogin(result);

            if (result.awarded) {
                router.reload({ only: ['pointsBalance', 'transactions'] });
            }
        } catch {
            // Transient network error — the button stays enabled so the
            // customer can simply try again.
        } finally {
            setClaiming(false);
        }
    };

    const discountLabel =
        referralDiscountType === 'percent'
            ? `${referralDiscountValue}% off`
            : `${formatCurrency(referralDiscountValue)} off`;

    return (
        <>
            <Head title="Referral & Points" />

            <div className="space-y-8">
                <div>
                    <h1 className="mb-1 font-serif text-3xl font-bold text-[var(--color-text-primary)]">
                        Referral &amp; Points
                    </h1>
                    <p className="text-[var(--color-text-secondary)]">
                        Invite friends, earn points on your own orders, and
                        build a daily login streak.
                    </p>
                </div>

                {/* ── Daily login streak ────────────────────────────────── */}
                <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
                    <div className="mb-3 flex items-center gap-2">
                        <Calendar className="size-5 text-[var(--color-primary)]" />
                        <h2 className="font-semibold text-[var(--color-text-primary)]">
                            Daily login streak
                        </h2>
                    </div>
                    <p className="mb-4 text-sm text-[var(--color-text-secondary)]">
                        Claim a bonus once a day. Each day in a row is worth
                        more — up to day {dailyLoginCapDays} — but missing a day
                        resets you back to day 1.
                    </p>
                    <div className="flex flex-wrap items-center gap-4">
                        <div>
                            <p className="text-2xl font-bold text-[var(--color-text-primary)]">
                                Day {login.streakDay}
                            </p>
                            <p className="text-sm text-[var(--color-text-secondary)]">
                                {login.claimedToday
                                    ? `Claimed ${login.points} pts today`
                                    : `Worth ${login.points} pts today`}
                            </p>
                        </div>
                        <Button
                            onClick={handleClaimDailyLogin}
                            disabled={login.claimedToday || claiming}
                            className="ml-auto"
                        >
                            {login.claimedToday
                                ? 'Claimed'
                                : claiming
                                  ? 'Claiming…'
                                  : `Claim ${login.points} pts`}
                        </Button>
                    </div>
                </div>

                {/* ── Referral link ──────────────────────────────────────── */}
                {referralEnabled ? (
                    inviteUnlocked ? (
                        <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
                            <div className="mb-3 flex items-center gap-2">
                                <Gift className="size-5 text-[var(--color-primary)]" />
                                <h2 className="font-semibold text-[var(--color-text-primary)]">
                                    Your referral link
                                </h2>
                            </div>
                            <p className="mb-4 text-sm text-[var(--color-text-secondary)]">
                                Share this link. Anyone who signs up gets{' '}
                                {discountLabel} on their first order of{' '}
                                {formatCurrency(referralMinOrderAmount)} or
                                more. You earn {pointsPerReferral} points{' '}
                                {referralRewardDelayDays} day
                                {referralRewardDelayDays === 1 ? '' : 's'} after
                                that order is delivered — as long as it
                                isn&apos;t cancelled or returned.
                            </p>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <input
                                    readOnly
                                    value={referralLink}
                                    className="flex-1 rounded-xl border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2.5 text-sm text-[var(--color-text-primary)]"
                                    onFocus={(e) => e.target.select()}
                                />
                                <button
                                    type="button"
                                    onClick={handleCopy}
                                    className="flex items-center justify-center gap-2 rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]"
                                >
                                    {copied ? (
                                        <>
                                            <Check className="size-4" />
                                            Copied
                                        </>
                                    ) : (
                                        <>
                                            <Copy className="size-4" />
                                            Copy link
                                        </>
                                    )}
                                </button>
                            </div>
                            <p className="mt-2 text-xs text-[var(--color-text-secondary)]">
                                Your code:{' '}
                                <span className="font-mono font-semibold">
                                    {referralCode}
                                </span>
                            </p>
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
                            <div className="mb-3 flex items-center gap-2">
                                <Lock className="size-5 text-[var(--color-text-secondary)]" />
                                <h2 className="font-semibold text-[var(--color-text-primary)]">
                                    Invite link locked
                                </h2>
                            </div>
                            <p className="text-sm text-[var(--color-text-secondary)]">
                                Place and receive{' '}
                                {unlockOrderCount - deliveredOrderCount} more
                                delivered order
                                {unlockOrderCount - deliveredOrderCount === 1
                                    ? ''
                                    : 's'}{' '}
                                ({deliveredOrderCount}/{unlockOrderCount} so
                                far) to unlock your referral link.
                            </p>
                        </div>
                    )
                ) : (
                    <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6 text-sm text-[var(--color-text-secondary)]">
                        The referral program isn&apos;t active right now. Your
                        existing points balance and history are still shown
                        below.
                    </div>
                )}

                <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
                        <div className="mb-2 flex size-10 items-center justify-center rounded-xl bg-amber-50">
                            <Gift className="size-5 text-amber-600" />
                        </div>
                        <p className="text-2xl font-bold text-[var(--color-text-primary)]">
                            {pointsBalance}
                        </p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                            Points balance
                        </p>
                    </div>
                    <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
                        <div className="mb-2 flex size-10 items-center justify-center rounded-xl bg-blue-50">
                            <Users className="size-5 text-blue-600" />
                        </div>
                        <p className="text-2xl font-bold text-[var(--color-text-primary)]">
                            {referredCount}
                        </p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                            People referred
                        </p>
                    </div>
                    {pointsValue > 0 && (
                        <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
                            <div className="mb-2 flex size-10 items-center justify-center rounded-xl bg-green-50">
                                <Check className="size-5 text-green-600" />
                            </div>
                            <p className="text-2xl font-bold text-[var(--color-text-primary)]">
                                {formatCurrency(pointsBalance * pointsValue)}
                            </p>
                            <p className="text-sm text-[var(--color-text-secondary)]">
                                Worth at checkout
                            </p>
                        </div>
                    )}
                </div>

                <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
                    <div className="border-b border-[var(--color-border)] px-6 py-4">
                        <h2 className="font-semibold text-[var(--color-text-primary)]">
                            Points history
                        </h2>
                    </div>
                    {transactions.data.length === 0 ? (
                        <p className="px-6 py-8 text-center text-sm text-[var(--color-text-secondary)]">
                            No points activity yet.
                        </p>
                    ) : (
                        <div className="divide-y divide-[var(--color-border)]">
                            {transactions.data.map((tx) => (
                                <div
                                    key={tx.id}
                                    className="flex items-center justify-between gap-4 px-6 py-4"
                                >
                                    <div>
                                        <div className="mb-1 flex items-center gap-2">
                                            <Badge
                                                variant={TYPE_VARIANT[tx.type]}
                                            >
                                                {TYPE_LABEL[tx.type]}
                                            </Badge>
                                            <span className="text-xs text-[var(--color-text-secondary)]">
                                                {formatDate(tx.created_at)}
                                            </span>
                                        </div>
                                        {tx.note && (
                                            <p className="text-sm text-[var(--color-text-secondary)]">
                                                {tx.note}
                                            </p>
                                        )}
                                    </div>
                                    <span
                                        className={`shrink-0 text-sm font-semibold ${
                                            tx.points >= 0
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        }`}
                                    >
                                        {tx.points >= 0 ? '+' : ''}
                                        {tx.points} pts
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
