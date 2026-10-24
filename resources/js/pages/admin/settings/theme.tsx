import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { SettingsField, settingsInputClass } from '@/components/admin/settings-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RadioGroup } from '@/components/ui/radio-group';
import { Select } from '@/components/ui/select';

type ThemeName = 'default' | 'neomorphism' | 'glassmorphism' | 'aurora';

type AbVariant = {
    theme: ThemeName;
    weight: number;
};

type ThemeConfig = {
    mode: 'global' | 'ab_test' | 'region';
    global_theme?: ThemeName;
    ab_test?: { variants: AbVariant[] };
    region_map?: Record<string, ThemeName>;
};

type Props = {
    themeConfig: ThemeConfig;
    availableThemes: ThemeName[];
    divisions: string[];
};

const THEME_LABELS: Record<ThemeName, string> = {
    default: 'Classic (current look)',
    neomorphism: 'Neomorphism — soft, embossed UI',
    glassmorphism: 'Glassmorphism — frosted glass',
    aurora: 'Aurora — vivid animated gradient',
};

export default function ThemeSettings({ themeConfig, availableThemes, divisions }: Props) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm<{
        mode: 'global' | 'ab_test' | 'region';
        global_theme: ThemeName;
        ab_test: { variants: AbVariant[] };
        region_map: Record<string, ThemeName>;
    }>({
        mode: themeConfig.mode ?? 'global',
        global_theme: themeConfig.global_theme ?? 'default',
        ab_test: {
            variants:
                themeConfig.ab_test?.variants ?? [
                    { theme: 'default', weight: 50 },
                    { theme: 'neomorphism', weight: 50 },
                ],
        },
        region_map:
            themeConfig.region_map ??
            Object.fromEntries([...divisions, 'default'].map((key) => [key, 'default'])) as Record<
                string,
                ThemeName
            >,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.settings.theme.update'), { preserveScroll: true });
    };

    const updateVariant = (index: number, patch: Partial<AbVariant>) => {
        const variants = data.ab_test.variants.map((variant, i) =>
            i === index ? { ...variant, ...patch } : variant,
        );
        setData('ab_test', { variants });
    };

    const addVariant = () => {
        const used = new Set(data.ab_test.variants.map((v) => v.theme));
        const nextTheme = availableThemes.find((t) => !used.has(t)) ?? availableThemes[0];
        setData('ab_test', {
            variants: [...data.ab_test.variants, { theme: nextTheme, weight: 10 }],
        });
    };

    const removeVariant = (index: number) => {
        setData('ab_test', {
            variants: data.ab_test.variants.filter((_, i) => i !== index),
        });
    };

    const duplicateVariantThemes =
        new Set(data.ab_test.variants.map((v) => v.theme)).size !== data.ab_test.variants.length;

    const canSubmit =
        data.mode !== 'ab_test' ||
        (data.ab_test.variants.length >= 1 &&
            data.ab_test.variants.every((v) => v.weight >= 1) &&
            !duplicateVariantThemes);

    return (
        <>
            <Head title="Theme — Admin" />
            <div className="max-w-2xl space-y-5">
                <h1 className="text-xl font-bold text-slate-900">Theme</h1>
                <p className="text-sm text-slate-500">
                    Pick how visitors see the storefront: one theme for everyone, a weighted A/B
                    split, or different themes per Bangladesh division.
                </p>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Mode
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <RadioGroup
                                value={data.mode}
                                onValueChange={(value) =>
                                    setData('mode', value as 'global' | 'ab_test' | 'region')
                                }
                                options={[
                                    {
                                        label: 'Global',
                                        value: 'global',
                                        description: 'Show one theme to every visitor.',
                                    },
                                    {
                                        label: 'A/B test',
                                        value: 'ab_test',
                                        description:
                                            'Split visitors across weighted theme variants, sticky per browser.',
                                    },
                                    {
                                        label: 'Region-targeted',
                                        value: 'region',
                                        description:
                                            'Show different themes by the visitor’s Bangladesh division, detected from their IP address.',
                                    },
                                ]}
                            />
                        </CardContent>
                    </Card>

                    {data.mode === 'global' ? (
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Global theme
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <SettingsField label="Theme">
                                    <Select
                                        selectClassName={settingsInputClass}
                                        value={data.global_theme}
                                        onChange={(e) =>
                                            setData('global_theme', e.target.value as ThemeName)
                                        }
                                    >
                                        {availableThemes.map((theme) => (
                                            <option key={theme} value={theme}>
                                                {THEME_LABELS[theme]}
                                            </option>
                                        ))}
                                    </Select>
                                </SettingsField>
                            </CardContent>
                        </Card>
                    ) : null}

                    {data.mode === 'ab_test' ? (
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    A/B variants
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-4">
                                {data.ab_test.variants.map((variant, index) => (
                                    <div key={index} className="flex items-end gap-2">
                                        <SettingsField label="Theme">
                                            <Select
                                                selectClassName={settingsInputClass}
                                                value={variant.theme}
                                                onChange={(e) =>
                                                    updateVariant(index, {
                                                        theme: e.target.value as ThemeName,
                                                    })
                                                }
                                            >
                                                {availableThemes.map((theme) => (
                                                    <option key={theme} value={theme}>
                                                        {THEME_LABELS[theme]}
                                                    </option>
                                                ))}
                                            </Select>
                                        </SettingsField>
                                        <SettingsField label="Weight">
                                            <input
                                                type="number"
                                                min={1}
                                                max={100}
                                                className={`${settingsInputClass} w-24`}
                                                value={variant.weight}
                                                onChange={(e) =>
                                                    updateVariant(index, {
                                                        weight: Number(e.target.value),
                                                    })
                                                }
                                            />
                                        </SettingsField>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            disabled={data.ab_test.variants.length <= 1}
                                            onClick={() => removeVariant(index)}
                                            aria-label="Remove variant"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}

                                {duplicateVariantThemes ? (
                                    <p className="text-xs text-red-600">
                                        Each variant must use a different theme.
                                    </p>
                                ) : null}
                                {errors['ab_test.variants'] ? (
                                    <p className="text-xs text-red-600">
                                        {errors['ab_test.variants']}
                                    </p>
                                ) : null}

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addVariant}
                                    disabled={data.ab_test.variants.length >= availableThemes.length}
                                >
                                    <Plus className="size-4" />
                                    Add variant
                                </Button>
                            </CardContent>
                        </Card>
                    ) : null}

                    {data.mode === 'region' ? (
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Theme by division
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-4">
                                <p className="text-xs text-slate-400">
                                    IP-based geolocation for Bangladesh is a best effort — mobile
                                    and ISP IPs often resolve to Dhaka regardless of the visitor's
                                    real location. Treat this as directional, not precise.
                                </p>
                                {divisions.map((division) => (
                                    <SettingsField key={division} label={division}>
                                        <Select
                                            selectClassName={settingsInputClass}
                                            value={data.region_map[division] ?? 'default'}
                                            onChange={(e) =>
                                                setData('region_map', {
                                                    ...data.region_map,
                                                    [division]: e.target.value as ThemeName,
                                                })
                                            }
                                        >
                                            {availableThemes.map((theme) => (
                                                <option key={theme} value={theme}>
                                                    {THEME_LABELS[theme]}
                                                </option>
                                            ))}
                                        </Select>
                                    </SettingsField>
                                ))}
                                <SettingsField
                                    label="Other regions (fallback)"
                                    hint="Used for visitors outside Bangladesh, or when the division can't be determined."
                                >
                                    <Select
                                        selectClassName={settingsInputClass}
                                        value={data.region_map.default ?? 'default'}
                                        onChange={(e) =>
                                            setData('region_map', {
                                                ...data.region_map,
                                                default: e.target.value as ThemeName,
                                            })
                                        }
                                    >
                                        {availableThemes.map((theme) => (
                                            <option key={theme} value={theme}>
                                                {THEME_LABELS[theme]}
                                            </option>
                                        ))}
                                    </Select>
                                </SettingsField>
                                {errors.region_map ? (
                                    <p className="text-xs text-red-600">{errors.region_map}</p>
                                ) : null}
                            </CardContent>
                        </Card>
                    ) : null}

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Preview as visitor
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2 pt-4">
                            {availableThemes.map((theme) => (
                                <a
                                    key={theme}
                                    href={`/?preview_theme=${theme}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:border-slate-300 hover:bg-slate-50"
                                >
                                    {THEME_LABELS[theme]}
                                </a>
                            ))}
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing || !canSubmit}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : 'Save'}
                        </Button>
                        {recentlySuccessful ? (
                            <span className="text-xs font-medium text-emerald-600">Saved.</span>
                        ) : null}
                    </div>
                </form>
            </div>
        </>
    );
}
