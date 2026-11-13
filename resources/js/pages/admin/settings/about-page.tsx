import { Head, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { Plus, Trash2 } from 'lucide-react';
import { AdminFade, AdminReveal } from '@/components/admin/admin-motion';
import { RichTextEditor } from '@/components/admin/RichTextEditor';
import {
    SettingsField,
    settingsInputClass,
} from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { IconPicker } from '@/components/ui/icon-picker';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useRowKeys } from '@/hooks/use-row-keys';

type AboutStat = { value: string; label: string };
type AboutFeature = { icon: string; label: string };
type AboutValue = { icon: string; title: string; description: string };
type AboutTeamMember = { name: string; role: string; initials: string };

type Props = {
    settings: {
        about_hero_eyebrow?: string | null;
        about_hero_title?: string | null;
        about_hero_title_highlight?: string | null;
        about_hero_description?: string | null;
        about_stats?: AboutStat[] | null;
        about_mission_eyebrow?: string | null;
        about_mission_title?: string | null;
        about_mission_body?: string | null;
        about_mission_features?: AboutFeature[] | null;
        about_values?: AboutValue[] | null;
        about_team?: AboutTeamMember[] | null;
        about_cta_title?: string | null;
        about_cta_description?: string | null;
    };
};

export default function AboutPageConfig({ settings }: Props) {
    const { data, setData, put, processing, recentlySuccessful } = useForm({
        about_hero_eyebrow: settings.about_hero_eyebrow ?? 'Our Story',
        about_hero_title: settings.about_hero_title ?? "Bangladesh's Smartest",
        about_hero_title_highlight:
            settings.about_hero_title_highlight ?? 'Shopping Experience',
        about_hero_description:
            settings.about_hero_description ??
            'ShoppyDrive was built to make premium shopping accessible to every Bangladeshi — with honest prices, genuine products, and world-class delivery.',
        about_stats: settings.about_stats ?? ([] as AboutStat[]),
        about_mission_eyebrow: settings.about_mission_eyebrow ?? 'Our Mission',
        about_mission_title:
            settings.about_mission_title ??
            'Empowering Bangladeshis to shop with confidence',
        about_mission_body: settings.about_mission_body ?? '',
        about_mission_features:
            settings.about_mission_features ?? ([] as AboutFeature[]),
        about_values: settings.about_values ?? ([] as AboutValue[]),
        about_team: settings.about_team ?? ([] as AboutTeamMember[]),
        about_cta_title: settings.about_cta_title ?? 'Ready to start shopping?',
        about_cta_description:
            settings.about_cta_description ??
            "Join thousands of happy customers and discover why ShoppyDrive is Bangladesh's most trusted online store.",
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    const addStat = () =>
        setData('about_stats', [...data.about_stats, { value: '', label: '' }]);
    const statRowKeys = useRowKeys(data.about_stats.length);

    const removeStat = (index: number) => {
        statRowKeys.removeKey(index);
        setData(
            'about_stats',
            data.about_stats.filter((_, i) => i !== index),
        );
    };
    const updateStat = (index: number, patch: Partial<AboutStat>) =>
        setData(
            'about_stats',
            data.about_stats.map((s, i) =>
                i === index ? { ...s, ...patch } : s,
            ),
        );

    const addFeature = () =>
        setData('about_mission_features', [
            ...data.about_mission_features,
            { icon: 'package', label: '' },
        ]);
    const featureRowKeys = useRowKeys(data.about_mission_features.length);

    const removeFeature = (index: number) => {
        featureRowKeys.removeKey(index);
        setData(
            'about_mission_features',
            data.about_mission_features.filter((_, i) => i !== index),
        );
    };
    const updateFeature = (index: number, patch: Partial<AboutFeature>) =>
        setData(
            'about_mission_features',
            data.about_mission_features.map((f, i) =>
                i === index ? { ...f, ...patch } : f,
            ),
        );

    const addValue = () =>
        setData('about_values', [
            ...data.about_values,
            { icon: 'shield-check', title: '', description: '' },
        ]);
    const valueRowKeys = useRowKeys(data.about_values.length);

    const removeValue = (index: number) => {
        valueRowKeys.removeKey(index);
        setData(
            'about_values',
            data.about_values.filter((_, i) => i !== index),
        );
    };
    const updateValue = (index: number, patch: Partial<AboutValue>) =>
        setData(
            'about_values',
            data.about_values.map((v, i) =>
                i === index ? { ...v, ...patch } : v,
            ),
        );

    const addTeamMember = () =>
        setData('about_team', [
            ...data.about_team,
            { name: '', role: '', initials: '' },
        ]);
    const teamRowKeys = useRowKeys(data.about_team.length);

    const removeTeamMember = (index: number) => {
        teamRowKeys.removeKey(index);
        setData(
            'about_team',
            data.about_team.filter((_, i) => i !== index),
        );
    };
    const updateTeamMember = (index: number, patch: Partial<AboutTeamMember>) =>
        setData(
            'about_team',
            data.about_team.map((t, i) =>
                i === index ? { ...t, ...patch } : t,
            ),
        );

    return (
        <>
            <Head title="About Page — Admin" />
            <form onSubmit={handleSubmit} className="space-y-3">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold text-slate-900">
                        About Page
                    </h1>
                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        <AnimatePresence initial={false}>
                            {recentlySuccessful && (
                                <AdminFade key="saved-0">
                                    <span className="text-xs font-medium text-emerald-600">
                                        Saved.
                                    </span>
                                </AdminFade>
                            )}
                        </AnimatePresence>
                    </div>
                </div>

                <Card className="gap-3 py-4 shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-3">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Hero
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 pt-0">
                        <div className="sm:w-1/2">
                            <SettingsField label="Eyebrow badge">
                                <input
                                    maxLength={60}
                                    className={settingsInputClass}
                                    value={data.about_hero_eyebrow}
                                    onChange={(e) =>
                                        setData(
                                            'about_hero_eyebrow',
                                            e.target.value,
                                        )
                                    }
                                />
                                <CharCount
                                    value={data.about_hero_eyebrow}
                                    max={60}
                                    className="mt-0.5 block text-right text-[11px] leading-none"
                                />
                            </SettingsField>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <SettingsField
                                label="Title (first line)"
                                hint="Shown before the highlighted line"
                            >
                                <input
                                    maxLength={100}
                                    className={settingsInputClass}
                                    value={data.about_hero_title}
                                    onChange={(e) =>
                                        setData(
                                            'about_hero_title',
                                            e.target.value,
                                        )
                                    }
                                />
                                <CharCount
                                    value={data.about_hero_title}
                                    max={100}
                                    className="mt-0.5 block text-right text-[11px] leading-none"
                                />
                            </SettingsField>
                            <SettingsField
                                label="Title (highlighted line)"
                                hint="Shown on its own line, in the brand color"
                            >
                                <input
                                    maxLength={60}
                                    className={settingsInputClass}
                                    value={data.about_hero_title_highlight}
                                    onChange={(e) =>
                                        setData(
                                            'about_hero_title_highlight',
                                            e.target.value,
                                        )
                                    }
                                />
                                <CharCount
                                    value={data.about_hero_title_highlight}
                                    max={60}
                                    className="mt-0.5 block text-right text-[11px] leading-none"
                                />
                            </SettingsField>
                        </div>
                        <SettingsField label="Description">
                            <Textarea
                                maxLength={200}
                                showCount
                                textareaClassName={settingsInputClass}
                                rows={3}
                                value={data.about_hero_description}
                                onChange={(e) =>
                                    setData(
                                        'about_hero_description',
                                        e.target.value,
                                    )
                                }
                            />
                        </SettingsField>
                    </CardContent>
                </Card>

                <Card className="gap-3 py-4 shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-3">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Stats Strip
                        </CardTitle>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addStat}
                        >
                            <Plus className="mr-1 size-3.5" /> Add Stat
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-2.5 pt-0">
                        <AnimatePresence initial={false}>
                            {data.about_stats.map((stat, index) => (
                                <AdminReveal key={statRowKeys.keys[index]}>
                                    <div className="flex items-start gap-2">
                                        <Input
                                            maxLength={20}
                                            showCount
                                            containerClassName="flex-1"
                                            placeholder="Value (e.g. 50,000+)"
                                            value={stat.value}
                                            onChange={(e) =>
                                                updateStat(index, {
                                                    value: e.target.value,
                                                })
                                            }
                                        />
                                        <Input
                                            maxLength={100}
                                            showCount
                                            containerClassName="flex-1"
                                            placeholder="Label (e.g. Happy Customers)"
                                            value={stat.label}
                                            onChange={(e) =>
                                                updateStat(index, {
                                                    label: e.target.value,
                                                })
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0 text-red-400"
                                            onClick={() => removeStat(index)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </AdminReveal>
                            ))}
                        </AnimatePresence>
                        {data.about_stats.length === 0 && (
                            <p className="py-4 text-center text-xs text-slate-600 italic">
                                No stats added. Max 4 recommended.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card className="gap-3 py-4 shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-3">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Mission
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 pt-0">
                        <div className="grid gap-2 sm:grid-cols-2">
                            <SettingsField label="Eyebrow">
                                <input
                                    maxLength={60}
                                    className={settingsInputClass}
                                    value={data.about_mission_eyebrow}
                                    onChange={(e) =>
                                        setData(
                                            'about_mission_eyebrow',
                                            e.target.value,
                                        )
                                    }
                                />
                                <CharCount
                                    value={data.about_mission_eyebrow}
                                    max={60}
                                    className="mt-0.5 block text-right text-[11px] leading-none"
                                />
                            </SettingsField>
                            <SettingsField label="Title">
                                <input
                                    maxLength={100}
                                    className={settingsInputClass}
                                    value={data.about_mission_title}
                                    onChange={(e) =>
                                        setData(
                                            'about_mission_title',
                                            e.target.value,
                                        )
                                    }
                                />
                                <CharCount
                                    value={data.about_mission_title}
                                    max={100}
                                    className="mt-0.5 block text-right text-[11px] leading-none"
                                />
                            </SettingsField>
                        </div>
                        <SettingsField label="Body">
                            <RichTextEditor
                                value={data.about_mission_body}
                                maxLength={20000}
                                maxTextLength={400}
                                onChange={(html) =>
                                    setData('about_mission_body', html)
                                }
                            />
                        </SettingsField>

                        <div className="border-t border-slate-100 pt-4">
                            <div className="mb-3 flex items-center justify-between">
                                <h5 className="text-xs font-bold text-slate-600 uppercase">
                                    Feature Badges
                                </h5>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addFeature}
                                >
                                    <Plus className="mr-1 size-3.5" /> Add
                                    Feature
                                </Button>
                            </div>
                            <div className="space-y-3">
                                <AnimatePresence initial={false}>
                                    {data.about_mission_features.map(
                                        (feature, index) => (
                                            <AdminReveal
                                                key={featureRowKeys.keys[index]}
                                            >
                                                <div className="flex items-start gap-2">
                                                    <IconPicker
                                                        className="w-40 shrink-0"
                                                        value={feature.icon}
                                                        onChange={(icon) =>
                                                            updateFeature(
                                                                index,
                                                                {
                                                                    icon,
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <Input
                                                        maxLength={60}
                                                        showCount
                                                        containerClassName="flex-1"
                                                        placeholder="Label (e.g. Nationwide Delivery)"
                                                        value={feature.label}
                                                        onChange={(e) =>
                                                            updateFeature(
                                                                index,
                                                                {
                                                                    label: e
                                                                        .target
                                                                        .value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="shrink-0 text-red-400"
                                                        onClick={() =>
                                                            removeFeature(index)
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </AdminReveal>
                                        ),
                                    )}
                                </AnimatePresence>
                                {data.about_mission_features.length === 0 && (
                                    <p className="py-4 text-center text-xs text-slate-600 italic">
                                        No feature badges added. Max 4
                                        recommended.
                                    </p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="gap-3 py-4 shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-3">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Core Values
                        </CardTitle>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addValue}
                        >
                            <Plus className="mr-1 size-3.5" /> Add Value
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-2 pt-0">
                        <AnimatePresence initial={false}>
                            {data.about_values.map((value, index) => (
                                <AdminReveal key={valueRowKeys.keys[index]}>
                                    <div className="space-y-2 rounded-xl border border-slate-200 p-4">
                                        <div className="flex items-start gap-2">
                                            <IconPicker
                                                className="w-40 shrink-0"
                                                value={value.icon}
                                                onChange={(icon) =>
                                                    updateValue(index, { icon })
                                                }
                                            />
                                            <Input
                                                maxLength={60}
                                                showCount
                                                containerClassName="flex-1"
                                                placeholder="Title (e.g. Trust & Transparency)"
                                                value={value.title}
                                                onChange={(e) =>
                                                    updateValue(index, {
                                                        title: e.target.value,
                                                    })
                                                }
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="shrink-0 text-red-400"
                                                onClick={() =>
                                                    removeValue(index)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                        <Textarea
                                            maxLength={250}
                                            showCount
                                            textareaClassName={
                                                settingsInputClass
                                            }
                                            rows={2}
                                            placeholder="Description"
                                            value={value.description}
                                            onChange={(e) =>
                                                updateValue(index, {
                                                    description: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </AdminReveal>
                            ))}
                        </AnimatePresence>
                        {data.about_values.length === 0 && (
                            <p className="py-4 text-center text-xs text-slate-600 italic">
                                No values added. Max 4 recommended.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card className="gap-3 py-4 shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-3">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Team
                        </CardTitle>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addTeamMember}
                        >
                            <Plus className="mr-1 size-3.5" /> Add Member
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-2.5 pt-0">
                        <AnimatePresence initial={false}>
                            {data.about_team.map((member, index) => (
                                <AdminReveal key={teamRowKeys.keys[index]}>
                                    <div className="flex items-start gap-2">
                                        <Input
                                            maxLength={60}
                                            showCount
                                            containerClassName="flex-1"
                                            placeholder="Name"
                                            value={member.name}
                                            onChange={(e) =>
                                                updateTeamMember(index, {
                                                    name: e.target.value,
                                                })
                                            }
                                        />
                                        <Input
                                            maxLength={60}
                                            showCount
                                            containerClassName="flex-1"
                                            placeholder="Role"
                                            value={member.role}
                                            onChange={(e) =>
                                                updateTeamMember(index, {
                                                    role: e.target.value,
                                                })
                                            }
                                        />
                                        <Input
                                            containerClassName="w-20 shrink-0"
                                            placeholder="RI"
                                            maxLength={4}
                                            showCount
                                            value={member.initials}
                                            onChange={(e) =>
                                                updateTeamMember(index, {
                                                    initials: e.target.value,
                                                })
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0 text-red-400"
                                            onClick={() =>
                                                removeTeamMember(index)
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </AdminReveal>
                            ))}
                        </AnimatePresence>
                        {data.about_team.length === 0 && (
                            <p className="py-4 text-center text-xs text-slate-600 italic">
                                No team members added.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card className="gap-3 py-4 shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-3">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Closing Call-to-Action
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 pt-0">
                        <SettingsField label="Title">
                            <input
                                maxLength={100}
                                className={settingsInputClass}
                                value={data.about_cta_title}
                                onChange={(e) =>
                                    setData('about_cta_title', e.target.value)
                                }
                            />
                            <CharCount
                                value={data.about_cta_title}
                                max={100}
                                className="mt-0.5 block text-right text-[11px] leading-none"
                            />
                        </SettingsField>
                        <SettingsField label="Description">
                            <Textarea
                                maxLength={250}
                                showCount
                                textareaClassName={settingsInputClass}
                                rows={2}
                                value={data.about_cta_description}
                                onChange={(e) =>
                                    setData(
                                        'about_cta_description',
                                        e.target.value,
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
                        className="bg-[#c8102e] px-8 text-white hover:bg-[#a50d25]"
                    >
                        {processing ? 'Saving…' : 'Save Changes'}
                    </Button>
                    <AnimatePresence initial={false}>
                        {recentlySuccessful && (
                            <AdminFade key="saved-1">
                                <span className="text-xs font-medium text-emerald-600">
                                    Saved.
                                </span>
                            </AdminFade>
                        )}
                    </AnimatePresence>
                </div>
            </form>
        </>
    );
}
