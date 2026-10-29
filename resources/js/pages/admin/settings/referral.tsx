import { Head, useForm } from '@inertiajs/react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

type Props = {
    settings: Record<string, string | number | boolean | null>;
};

export default function ReferralConfig({ settings }: Props) {
    const { data, setData, put, processing, recentlySuccessful } = useForm({
        referral_enabled: Boolean(settings.referral_enabled),
        referral_discount_type:
            (settings.referral_discount_type as string) ?? 'percent',
        referral_discount_value: Number(settings.referral_discount_value ?? 0),
        referral_min_order_amount: Number(
            settings.referral_min_order_amount ?? 0,
        ),
        referral_reward_points: Number(settings.referral_reward_points ?? 0),
        referral_reward_delay_days: Number(
            settings.referral_reward_delay_days ?? 0,
        ),
        referral_unlock_order_count: Number(
            settings.referral_unlock_order_count ?? 0,
        ),
        referral_reward_cap_amount: Number(
            settings.referral_reward_cap_amount ?? 0,
        ),
        referral_reward_cap_period:
            (settings.referral_reward_cap_period as string) ?? 'month',
        loyalty_earn_rate_bdt_per_point: Number(
            settings.loyalty_earn_rate_bdt_per_point ?? 0,
        ),
        loyalty_earn_delay_days: Number(settings.loyalty_earn_delay_days ?? 0),
        registration_bonus_points: Number(
            settings.registration_bonus_points ?? 0,
        ),
        review_bonus_points: Number(settings.review_bonus_points ?? 0),
        daily_login_base_points: Number(settings.daily_login_base_points ?? 0),
        daily_login_cap_days: Number(settings.daily_login_cap_days ?? 0),
        points_redeem_value_bdt: Number(settings.points_redeem_value_bdt ?? 0),
        points_redeem_max_percent: Number(
            settings.points_redeem_max_percent ?? 0,
        ),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Referral & Loyalty Program — Admin" />
            <div className="max-w-2xl space-y-5">
                <h1 className="text-xl font-bold text-slate-900">
                    Referral &amp; Loyalty Program
                </h1>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Referral Program
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <SettingsField
                                label="Enable Referral Program"
                                hint="When off, existing referral links stop creating new referral relationships (registration still works), and no new referral discount or points are granted."
                            >
                                <Switch
                                    checked={data.referral_enabled}
                                    onCheckedChange={(checked) =>
                                        setData('referral_enabled', checked)
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="First-Order Discount Type"
                                hint="Applied automatically to a referred customer's first qualifying order."
                            >
                                <Select
                                    selectClassName={settingsInputClass}
                                    value={data.referral_discount_type}
                                    onChange={(e) =>
                                        setData(
                                            'referral_discount_type',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="percent">
                                        Percentage off
                                    </option>
                                    <option value="fixed">
                                        Fixed amount off
                                    </option>
                                </Select>
                            </SettingsField>
                            <SettingsField
                                label="First-Order Discount Value"
                                hint={
                                    data.referral_discount_type === 'percent'
                                        ? 'Percentage of the subtotal, e.g. 10 for 10%.'
                                        : 'Flat currency amount, e.g. 100.'
                                }
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.referral_discount_value}
                                    onChange={(e) =>
                                        setData(
                                            'referral_discount_value',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Minimum Order Amount"
                                hint="The referred customer's order must reach this subtotal (৳) for the discount to apply. Below it, no discount — and their one-time eligibility is not spent, so a later qualifying order can still use it."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.referral_min_order_amount}
                                    onChange={(e) =>
                                        setData(
                                            'referral_min_order_amount',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Referrer Reward Points"
                                hint="Points credited to the referrer once their referred customer's qualifying order has been delivered for the reward delay below, and is still delivered (not cancelled/returned)."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.referral_reward_points}
                                    onChange={(e) =>
                                        setData(
                                            'referral_reward_points',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Reward Delay (days)"
                                hint="Days after delivery before the referrer's points post — gives a cancellation/return time to cancel the reward."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.referral_reward_delay_days}
                                    onChange={(e) =>
                                        setData(
                                            'referral_reward_delay_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Orders To Unlock Invite Link"
                                hint="A customer must have this many of their own delivered orders before their invite link/code is shown."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.referral_unlock_order_count}
                                    onChange={(e) =>
                                        setData(
                                            'referral_unlock_order_count',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Reward Cap Amount"
                                hint="Maximum ৳ value a referrer can earn from referrals per cap period below. A referral that would exceed it is held and granted once the next period starts, not forfeited."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.referral_reward_cap_amount}
                                    onChange={(e) =>
                                        setData(
                                            'referral_reward_cap_amount',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Reward Cap Period"
                                hint="How often the cap above resets (Asia/Dhaka calendar)."
                            >
                                <Select
                                    selectClassName={settingsInputClass}
                                    value={data.referral_reward_cap_period}
                                    onChange={(e) =>
                                        setData(
                                            'referral_reward_cap_period',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="week">Weekly</option>
                                    <option value="month">Monthly</option>
                                </Select>
                            </SettingsField>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Loyalty Points — Earning
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <SettingsField
                                label="BDT Per Point (Orders)"
                                hint="1 point earned per this many ৳ of product spend (excludes delivery charge and any discount) on a delivered order."
                            >
                                <input
                                    type="number"
                                    min={0.01}
                                    step="0.01"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.loyalty_earn_rate_bdt_per_point}
                                    onChange={(e) =>
                                        setData(
                                            'loyalty_earn_rate_bdt_per_point',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Earn Delay (days)"
                                hint="Days after delivery before order-spend points post — same reasoning as the referral reward delay."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.loyalty_earn_delay_days}
                                    onChange={(e) =>
                                        setData(
                                            'loyalty_earn_delay_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="New Account Bonus"
                                hint="Points granted once, right after registration."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.registration_bonus_points}
                                    onChange={(e) =>
                                        setData(
                                            'registration_bonus_points',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Verified Review Bonus"
                                hint="Points granted per approved review that's linked to a delivered order for that product (a verified purchase)."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.review_bonus_points}
                                    onChange={(e) =>
                                        setData(
                                            'review_bonus_points',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Daily Login Bonus
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <SettingsField
                                label="Base Points"
                                hint="Streak day N earns N × this, up to the cap below. E.g. with a base of 2: day 1 = 2, day 2 = 4, day 3 = 6."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.daily_login_base_points}
                                    onChange={(e) =>
                                        setData(
                                            'daily_login_base_points',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Streak Cap (days)"
                                hint="The per-day reward stops growing past this streak day, though the streak itself can keep counting beyond it. Missing a day resets the streak to day 1."
                            >
                                <input
                                    type="number"
                                    min={1}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.daily_login_cap_days}
                                    onChange={(e) =>
                                        setData(
                                            'daily_login_cap_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Redeeming Points At Checkout
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <SettingsField
                                label="Point Value"
                                hint="Currency value of 1 point when redeemed at checkout, e.g. 1 for ৳1 per point."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.points_redeem_value_bdt}
                                    onChange={(e) =>
                                        setData(
                                            'points_redeem_value_bdt',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Max Redemption"
                                hint="The most a customer can cover with points, as a % of the product subtotal (excludes delivery charge)."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.points_redeem_max_percent}
                                    onChange={(e) =>
                                        setData(
                                            'points_redeem_max_percent',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : 'Save'}
                        </Button>
                        {recentlySuccessful && (
                            <span className="text-xs font-medium text-emerald-600">
                                Saved.
                            </span>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
