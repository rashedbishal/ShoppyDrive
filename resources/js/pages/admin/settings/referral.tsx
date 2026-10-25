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
        referral_points_per_referral: Number(
            settings.referral_points_per_referral ?? 0,
        ),
        referral_points_value: Number(settings.referral_points_value ?? 0),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Referral Program — Admin" />
            <div className="max-w-2xl space-y-5">
                <h1 className="text-xl font-bold text-slate-900">
                    Referral Program
                </h1>

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-4">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Referral &amp; Points Settings
                        </CardTitle>
                    </CardHeader>
                    <form onSubmit={handleSubmit}>
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
                                hint="Applied automatically to a referred customer's first order."
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
                                label="Points Per Referral"
                                hint="Points credited to the referrer once the referred customer's first order is placed."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="1"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.referral_points_per_referral}
                                    onChange={(e) =>
                                        setData(
                                            'referral_points_per_referral',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                            <SettingsField
                                label="Point Value"
                                hint="Currency value of 1 point when a customer redeems points at checkout, e.g. 1 for ৳1 per point."
                            >
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={`${settingsInputClass} max-w-[160px]`}
                                    value={data.referral_points_value}
                                    onChange={(e) =>
                                        setData(
                                            'referral_points_value',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </SettingsField>
                        </CardContent>
                        <div className="flex items-center gap-3 border-t border-slate-100 px-6 py-4">
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
                </Card>
            </div>
        </>
    );
}
