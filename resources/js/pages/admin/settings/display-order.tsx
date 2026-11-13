import { Head, useForm, usePage } from '@inertiajs/react';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';

type Props = {
    scenarios: Record<string, string>;
    orders: string[];
    values: Record<string, string>;
};

const ORDER_LABELS: Record<string, string> = {
    descending: 'Descending (newest first)',
    random: 'Random (reshuffled every page load)',
};

export default function DisplayOrderSettings({
    scenarios,
    orders,
    values,
}: Props) {
    const { props } = usePage<{ flash?: { success?: string } }>();
    const flash = props.flash;

    const { data, setData, post, processing, recentlySuccessful } = useForm({
        values,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.settings.display-order.update'), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Display Order Settings — Admin" />
            <div className="w-full space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Display Order
                    </h1>
                    <p className="mt-1 text-sm text-slate-600">
                        The category and search results pages keep their own
                        sort dropdown for shoppers and aren't listed here.
                        Everything below has no customer-facing sort control —
                        pick how each one orders its cards instead.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Sections
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5 pt-5">
                            {Object.entries(scenarios).map(([key, label]) => (
                                <SettingsField key={key} label={label}>
                                    <Select
                                        selectClassName={settingsInputClass}
                                        value={data.values[key]}
                                        onChange={(e) =>
                                            setData('values', {
                                                ...data.values,
                                                [key]: e.target.value,
                                            })
                                        }
                                    >
                                        {orders.map((order) => (
                                            <option key={order} value={order}>
                                                {ORDER_LABELS[order] ?? order}
                                            </option>
                                        ))}
                                    </Select>
                                </SettingsField>
                            ))}
                        </CardContent>
                    </Card>

                    <div className="mt-5 flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-600">
                                Saved.
                            </span>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
