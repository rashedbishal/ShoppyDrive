import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Palette, RotateCcw } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

type Props = {
    brandColor: string;
    defaultColor: string;
};

/** A few ready-made choices; any colour can still be picked or typed. */
const PRESETS: { label: string; value: string }[] = [
    { label: 'Classic red', value: '#c8102e' },
    { label: 'Royal blue', value: '#1d4ed8' },
    { label: 'Navy', value: '#0b2a86' },
    { label: 'Emerald', value: '#047857' },
    { label: 'Teal', value: '#0f766e' },
    { label: 'Purple', value: '#7c3aed' },
    { label: 'Pink', value: '#db2777' },
    { label: 'Orange', value: '#ea580c' },
    { label: 'Charcoal', value: '#1f2937' },
];

const HEX = /^#[0-9a-fA-F]{6}$/;

/** Mirrors App\Support\BrandColor::readableTextOn() for the live preview. */
function readableTextOn(hex: string): string {
    const channel = (value: number) => {
        const c = value / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const luminance = (h: string) =>
        0.2126 * channel(parseInt(h.slice(1, 3), 16)) +
        0.7152 * channel(parseInt(h.slice(3, 5), 16)) +
        0.0722 * channel(parseInt(h.slice(5, 7), 16));
    const background = luminance(hex);
    const onWhite = 1.05 / (background + 0.05);
    const onDark = (background + 0.05) / (luminance('#1a1a2e') + 0.05);

    return onWhite >= onDark ? '#ffffff' : '#1a1a2e';
}

/**
 * The preview applies the picked colour to its own box only, before saving.
 *
 * DISC-45: the Tailwind `brand` utilities read `--color-brand*`, which
 * app.css's `@theme` defines once on :root as `var(--brand*)`, so the value
 * is fixed there; overriding only `--brand*` on this box left the preview
 * showing the saved colour. Both sets are overridden here.
 */
function previewStyle(hex: string): CSSProperties {
    const shades = {
        brand: hex,
        'brand-hover': `color-mix(in srgb, ${hex} 82.5%, black)`,
        'brand-light': `color-mix(in srgb, ${hex} 6%, white)`,
        'brand-foreground': readableTextOn(hex),
    };

    return Object.fromEntries(
        Object.entries(shades).flatMap(([name, value]) => [
            [`--${name}`, value],
            [`--color-${name}`, value],
        ]),
    ) as CSSProperties;
}

export default function BrandColorSettings({
    brandColor,
    defaultColor,
}: Props) {
    const { props } = usePage<{ flash?: { success?: string } }>();
    const [confirmReset, setConfirmReset] = useState(false);
    const [resetting, setResetting] = useState(false);
    const { data, setData, put, processing, errors, isDirty } = useForm({
        brand_color: brandColor,
    });

    const valid = HEX.test(data.brand_color);
    const preview = valid ? data.brand_color.toLowerCase() : brandColor;
    const isDefault = brandColor.toLowerCase() === defaultColor.toLowerCase();

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        put(route('admin.settings.brand-color.update'), {
            preserveScroll: true,
        });
    };

    const reset = () => {
        router.delete(route('admin.settings.brand-color.destroy'), {
            preserveScroll: true,
            onStart: () => setResetting(true),
            onFinish: () => {
                setResetting(false);
                setConfirmReset(false);
            },
            onSuccess: () => setData('brand_color', defaultColor),
        });
    };

    return (
        <>
            <Head title="Brand Colour — Admin" />
            <div className="space-y-5">
                <div>
                    <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                        <Palette className="size-5 text-brand" /> Brand Colour
                    </h1>
                    <p className="mt-1 text-sm text-slate-600">
                        The main colour of buttons, links, highlights and badges
                        across the admin panel, the website's Classic theme and
                        the emails customers receive. Error and delete warnings
                        stay red so they always stand out.
                    </p>
                </div>

                {props.flash?.success && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                        {props.flash.success}
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
                >
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Choose a colour
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5 pt-3">
                            <div className="flex flex-wrap items-end gap-3">
                                <label className="block">
                                    <span className="mb-1 block text-sm font-medium text-slate-700">
                                        Pick
                                    </span>
                                    <input
                                        type="color"
                                        aria-label="Pick a brand colour"
                                        value={preview}
                                        onChange={(event) =>
                                            setData(
                                                'brand_color',
                                                event.target.value,
                                            )
                                        }
                                        className="h-10 w-16 cursor-pointer rounded-lg border border-slate-200 bg-white p-1"
                                    />
                                </label>
                                <label className="block min-w-0 flex-1">
                                    <span className="mb-1 block text-sm font-medium text-slate-700">
                                        Hex code
                                    </span>
                                    <input
                                        value={data.brand_color}
                                        maxLength={7}
                                        spellCheck={false}
                                        onChange={(event) =>
                                            setData(
                                                'brand_color',
                                                event.target.value.trim(),
                                            )
                                        }
                                        className={`h-10 w-full rounded-lg border px-3 font-mono text-sm uppercase focus:ring-1 focus:outline-none ${
                                            errors.brand_color || !valid
                                                ? 'border-red-400 focus:ring-red-400'
                                                : 'border-slate-200 focus:ring-brand'
                                        }`}
                                    />
                                </label>
                            </div>
                            {(errors.brand_color || !valid) && (
                                <p className="text-xs text-red-500">
                                    {errors.brand_color ??
                                        'Enter a hex code like #1D4ED8.'}
                                </p>
                            )}

                            <div>
                                <p className="mb-2 text-sm font-medium text-slate-700">
                                    Suggestions
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {PRESETS.map((preset) => {
                                        const selected =
                                            preview === preset.value;

                                        return (
                                            <button
                                                key={preset.value}
                                                type="button"
                                                title={preset.label}
                                                aria-label={preset.label}
                                                aria-pressed={selected}
                                                onClick={() =>
                                                    setData(
                                                        'brand_color',
                                                        preset.value,
                                                    )
                                                }
                                                className={`flex size-9 items-center justify-center rounded-full ring-offset-2 transition hover:scale-110 ${
                                                    selected
                                                        ? 'ring-2 ring-slate-900'
                                                        : 'ring-1 ring-slate-200'
                                                }`}
                                                style={{
                                                    backgroundColor:
                                                        preset.value,
                                                }}
                                            >
                                                {selected && (
                                                    <Check
                                                        className="size-4"
                                                        style={{
                                                            color: readableTextOn(
                                                                preset.value,
                                                            ),
                                                        }}
                                                    />
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-between">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={isDefault || resetting}
                                    onClick={() => setConfirmReset(true)}
                                >
                                    <RotateCcw className="size-4" /> Reset to
                                    default red
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || !valid || !isDirty}
                                    className="bg-brand text-brand-foreground hover:bg-brand-hover"
                                >
                                    {processing ? 'Saving…' : 'Save colour'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Preview
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="pt-3">
                            <div
                                style={previewStyle(preview)}
                                className="space-y-4 rounded-xl border border-slate-100 bg-white p-4"
                            >
                                <div className="flex flex-wrap gap-2">
                                    <span className="inline-flex h-9 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-brand-foreground">
                                        Add to cart
                                    </span>
                                    <span className="inline-flex h-9 items-center rounded-lg border border-brand px-4 text-sm font-semibold text-brand">
                                        View details
                                    </span>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-brand-light px-2.5 py-0.5 text-xs font-semibold text-brand">
                                        New arrival
                                    </span>
                                    <span className="rounded-full bg-brand px-2.5 py-0.5 text-xs font-semibold text-brand-foreground">
                                        -20%
                                    </span>
                                    <span className="text-sm font-semibold text-brand underline-offset-2 hover:underline">
                                        A link
                                    </span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-xl font-bold text-brand">
                                        ৳1,250
                                    </span>
                                    <span className="text-sm text-slate-400 line-through">
                                        ৳1,560
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-brand-light">
                                    <div className="h-full w-2/3 rounded-full bg-brand" />
                                </div>
                                <p className="text-xs text-slate-500">
                                    Saved changes apply right away across the
                                    admin panel and the website. Your logo and
                                    favicon are images, so upload new ones if
                                    they should match.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>

            <ConfirmDialog
                open={confirmReset}
                onOpenChange={setConfirmReset}
                title="Reset the brand colour?"
                description="The whole site goes back to the original red."
                confirmLabel="Reset"
                processing={resetting}
                onConfirm={reset}
            />
        </>
    );
}
