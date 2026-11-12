import { Plus, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { CharCount } from '@/components/ui/char-count';

/** Loosely typed on purpose: the page builder's `sections` is one free-form JSON blob. */
type Sections = Record<string, any>;
type SetSec = (patch: Record<string, unknown>) => void;

export type BuilderVariant = {
    id: number;
    attributes: Record<string, string> | null;
    sku: string | null;
    price: number | null;
    stock_quantity: number;
};
export type BuilderMedia = {
    id: number;
    path: string;
    type: 'image' | 'video';
};

const inputClass =
    'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';
const textareaClass =
    'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e] resize-none';
const labelClass = 'mb-1 block text-xs font-medium text-slate-600';
const hintClass = 'mt-1 text-[11px] text-slate-400';

export function variantLabel(variant: BuilderVariant): string {
    const values = Object.values(variant.attributes ?? {});

    return values.length > 0
        ? values.join(' · ')
        : (variant.sku ?? `Variant #${variant.id}`);
}

function Field({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className={labelClass}>{label}</label>
            {children}
            {hint && <p className={hintClass}>{hint}</p>}
        </div>
    );
}

function Check({
    id,
    checked,
    onChange,
    label,
}: {
    id: string;
    checked: boolean;
    onChange: (value: boolean) => void;
    label: string;
}) {
    return (
        <div className="flex items-center gap-2">
            <input
                id={id}
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="rounded border-slate-300"
            />
            <label htmlFor={id} className="text-xs font-medium text-slate-600">
                {label}
            </label>
        </div>
    );
}

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

const ANIMATIONS = [
    { value: 'fade', label: 'Fade' },
    { value: 'slide', label: 'Slide' },
    { value: 'zoom', label: 'Zoom' },
    { value: 'flip', label: 'Flip' },
    { value: 'blur', label: 'Blur in' },
];

/** Hero: badge, second button, the three feature boxes, and the image carousel. */
export function HeroExtras({
    sec,
    setSec,
    pageId,
    media,
}: {
    sec: Sections;
    setSec: SetSec;
    pageId: number;
    media: BuilderMedia[];
}) {
    const hero = sec.hero;
    const carousel = hero.carousel ?? {};
    const features: { title: string; text: string }[] = hero.features ?? [];
    const setHero = (patch: Record<string, unknown>) =>
        setSec({ hero: { ...hero, ...patch } });
    const setCarousel = (patch: Record<string, unknown>) =>
        setHero({ carousel: { ...carousel, ...patch } });
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const hidden: number[] = carousel.hidden_media_ids ?? [];
    const extra: string[] = carousel.extra_slides ?? [];
    const images = media.filter((m) => m.type === 'image');

    const upload = async (file: File) => {
        setUploading(true);
        setUploadError(null);

        try {
            const body = new FormData();
            body.append('image', file);
            const response = await fetch(
                route('admin.landing-pages.slides.store', pageId),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                },
            );

            if (!response.ok) {
                throw new Error(
                    'Upload failed. Use a JPG, PNG, WebP or GIF under 8MB.',
                );
            }

            const { path } = (await response.json()) as { path: string };
            setCarousel({ extra_slides: [...extra, path] });
        } catch (error) {
            setUploadError(
                error instanceof Error ? error.message : 'Upload failed.',
            );
        } finally {
            setUploading(false);
        }
    };

    return (
        <>
            <Field label="Badge (small label above the headline)">
                <input
                    maxLength={40}
                    className={inputClass}
                    value={hero.badge ?? ''}
                    onChange={(e) => setHero({ badge: e.target.value })}
                    placeholder="Limited stock · Eid special"
                />
            </Field>
            <Field
                label="Second button text"
                hint="Scrolls to the product details. Leave empty to hide it."
            >
                <input
                    maxLength={30}
                    className={inputClass}
                    value={hero.secondary_cta_text ?? ''}
                    onChange={(e) =>
                        setHero({ secondary_cta_text: e.target.value })
                    }
                    placeholder="See Details"
                />
            </Field>

            <div className="space-y-2 rounded-xl border border-slate-100 p-3">
                <p className="text-xs font-semibold text-slate-700">
                    Feature boxes (shown under the hero, up to 3)
                </p>
                {[0, 1, 2].map((i) => (
                    <div
                        key={i}
                        className="grid grid-cols-1 gap-2 sm:grid-cols-2"
                    >
                        <input
                            maxLength={60}
                            className={inputClass}
                            value={features[i]?.title ?? ''}
                            onChange={(e) => {
                                const next = [0, 1, 2].map(
                                    (j) =>
                                        features[j] ?? { title: '', text: '' },
                                );
                                next[i] = { ...next[i], title: e.target.value };
                                setHero({ features: next });
                            }}
                            placeholder={`Box ${i + 1} title`}
                        />
                        <input
                            maxLength={140}
                            className={inputClass}
                            value={features[i]?.text ?? ''}
                            onChange={(e) => {
                                const next = [0, 1, 2].map(
                                    (j) =>
                                        features[j] ?? { title: '', text: '' },
                                );
                                next[i] = { ...next[i], text: e.target.value };
                                setHero({ features: next });
                            }}
                            placeholder={`Box ${i + 1} description`}
                        />
                    </div>
                ))}
            </div>

            <div className="space-y-3 rounded-xl border border-slate-100 p-3">
                <p className="text-xs font-semibold text-slate-700">
                    Image carousel
                </p>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Animation">
                        <select
                            className={inputClass}
                            value={carousel.animation ?? 'fade'}
                            onChange={(e) =>
                                setCarousel({ animation: e.target.value })
                            }
                        >
                            {ANIMATIONS.map((a) => (
                                <option key={a.value} value={a.value}>
                                    {a.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Seconds per slide">
                        <input
                            type="number"
                            min={2}
                            max={20}
                            step={0.5}
                            className={inputClass}
                            value={carousel.interval_seconds ?? 4}
                            onChange={(e) =>
                                setCarousel({
                                    interval_seconds: Number(e.target.value),
                                })
                            }
                        />
                    </Field>
                    <Field label="Animation length (ms)">
                        <input
                            type="number"
                            min={200}
                            max={3000}
                            step={100}
                            className={inputClass}
                            value={carousel.transition_ms ?? 800}
                            onChange={(e) =>
                                setCarousel({
                                    transition_ms: Number(e.target.value),
                                })
                            }
                        />
                    </Field>
                </div>
                <div className="flex flex-wrap gap-x-5 gap-y-2">
                    <Check
                        id="car_autoplay"
                        checked={carousel.autoplay !== false}
                        onChange={(v) => setCarousel({ autoplay: v })}
                        label="Auto-slide"
                    />
                    <Check
                        id="car_dots"
                        checked={carousel.show_dots !== false}
                        onChange={(v) => setCarousel({ show_dots: v })}
                        label="Show position dots"
                    />
                    <Check
                        id="car_progress"
                        checked={carousel.show_progress !== false}
                        onChange={(v) => setCarousel({ show_progress: v })}
                        label="Show progress bar"
                    />
                    <Check
                        id="car_product"
                        checked={carousel.use_product_images !== false}
                        onChange={(v) => setCarousel({ use_product_images: v })}
                        label="Use the product's images"
                    />
                </div>
                <p className={hintClass}>
                    Visitors can't swipe or click the carousel — it only plays.
                    With one slide it stays still.
                </p>

                {carousel.use_product_images !== false && images.length > 0 && (
                    <div>
                        <label className={labelClass}>
                            Product images (untick to hide from the carousel)
                        </label>
                        <div className="flex flex-wrap gap-2">
                            {images.map((image) => {
                                const shown = !hidden.includes(image.id);

                                return (
                                    <button
                                        key={image.id}
                                        type="button"
                                        onClick={() =>
                                            setCarousel({
                                                hidden_media_ids: shown
                                                    ? [...hidden, image.id]
                                                    : hidden.filter(
                                                          (id) =>
                                                              id !== image.id,
                                                      ),
                                            })
                                        }
                                        className={`relative size-16 overflow-hidden rounded-lg border-2 ${shown ? 'border-[#c8102e]' : 'border-slate-200 opacity-40'}`}
                                        aria-pressed={shown}
                                        aria-label={
                                            shown
                                                ? 'Hide this image'
                                                : 'Show this image'
                                        }
                                    >
                                        <img
                                            src={image.path}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div>
                    <label className={labelClass}>
                        Extra slides (added after the product images)
                    </label>
                    <div className="flex flex-wrap gap-2">
                        {extra.map((path, i) => (
                            <div
                                key={path}
                                className="group relative size-16 overflow-hidden rounded-lg border border-slate-200"
                            >
                                <img
                                    src={path}
                                    alt=""
                                    className="size-full object-cover"
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        setCarousel({
                                            extra_slides: extra.filter(
                                                (_, j) => j !== i,
                                            ),
                                        })
                                    }
                                    className="absolute inset-0 flex items-center justify-center bg-black/50 text-white opacity-0 transition group-hover:opacity-100"
                                    aria-label="Remove slide"
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        ))}
                        <button
                            type="button"
                            disabled={uploading || extra.length >= 12}
                            onClick={() => fileRef.current?.click()}
                            className="flex size-16 flex-col items-center justify-center gap-0.5 rounded-lg border border-dashed border-slate-300 text-[10px] text-slate-500 hover:border-[#c8102e] hover:text-[#c8102e] disabled:opacity-50"
                        >
                            <Upload className="size-4" />
                            {uploading ? 'Uploading…' : 'Add'}
                        </button>
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];

                                if (file) {
                                    void upload(file);
                                }

                                e.target.value = '';
                            }}
                        />
                    </div>
                    {uploadError && (
                        <p className="mt-1 text-xs text-red-500">
                            {uploadError}
                        </p>
                    )}
                    <p className={hintClass}>
                        Uploaded slides are saved on the page when you press
                        "Save All Changes".
                    </p>
                </div>
            </div>
        </>
    );
}

/** Product-description extras: the badge and the option picker's heading. */
export function DescriptionExtras({
    sec,
    setSec,
}: {
    sec: Sections;
    setSec: SetSec;
}) {
    const description = sec.description;
    const set = (patch: Record<string, unknown>) =>
        setSec({ description: { ...description, ...patch } });

    return (
        <>
            <Field label="Badge (shown above the title)">
                <input
                    maxLength={40}
                    className={inputClass}
                    value={description.badge ?? ''}
                    onChange={(e) => set({ badge: e.target.value })}
                    placeholder="Best seller"
                />
            </Field>
            <Field label="Option picker title">
                <input
                    maxLength={60}
                    className={inputClass}
                    value={description.selection_title ?? ''}
                    onChange={(e) => set({ selection_title: e.target.value })}
                />
            </Field>
            <Field label="Option picker hint">
                <input
                    maxLength={140}
                    className={inputClass}
                    value={description.selection_hint ?? ''}
                    onChange={(e) => set({ selection_hint: e.target.value })}
                />
            </Field>
        </>
    );
}

/** Combos built from the product's variants, plus the order section's own texts. */
export function CombosEditor({
    sec,
    setSec,
    variants,
}: {
    sec: Sections;
    setSec: SetSec;
    variants: BuilderVariant[];
}) {
    const combos: Record<string, any>[] = sec.combos ?? [];
    const selection = sec.selection ?? {};
    const setCombos = (next: Record<string, any>[]) => setSec({ combos: next });
    const update = (index: number, patch: Record<string, unknown>) =>
        setCombos(combos.map((c, i) => (i === index ? { ...c, ...patch } : c)));
    const newId = () => `combo-${Math.random().toString(36).slice(2, 9)}`;

    if (variants.length === 0) {
        return (
            <p className="text-xs text-slate-500">
                This product has no variants, so there is nothing to combine.
                Add variants to the product first.
            </p>
        );
    }

    return (
        <>
            <p className="text-xs text-slate-500">
                Single variants are always orderable on their own. Build combos
                (e.g. a couple set) out of this product's variants — a combo
                takes stock from each variant it contains. Save the page after
                changing the product so combos match it.
            </p>
            {combos.map((combo, index) => {
                const items: { variant_id: number; quantity: number }[] =
                    combo.items ?? [];
                const partsTotal = items.reduce(
                    (sum, item) =>
                        sum +
                        (variants.find((v) => v.id === item.variant_id)
                            ?.price ?? 0) *
                            item.quantity,
                    0,
                );

                return (
                    <div
                        key={combo.id}
                        className="space-y-3 rounded-xl border border-slate-200 p-3"
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-700">
                                Combo {index + 1}
                            </span>
                            <button
                                type="button"
                                onClick={() =>
                                    setCombos(
                                        combos.filter((_, i) => i !== index),
                                    )
                                }
                                className="text-slate-400 hover:text-red-500"
                                aria-label="Remove combo"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </div>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <Field label="Name">
                                <input
                                    maxLength={50}
                                    className={inputClass}
                                    value={combo.name ?? ''}
                                    onChange={(e) =>
                                        update(index, { name: e.target.value })
                                    }
                                    placeholder="Couple set"
                                />
                            </Field>
                            <Field label="Badge (optional)">
                                <input
                                    maxLength={20}
                                    className={inputClass}
                                    value={combo.badge ?? ''}
                                    onChange={(e) =>
                                        update(index, { badge: e.target.value })
                                    }
                                    placeholder="Best value"
                                />
                            </Field>
                        </div>
                        <Field label="Short description (optional)">
                            <input
                                maxLength={100}
                                className={inputClass}
                                value={combo.description ?? ''}
                                onChange={(e) =>
                                    update(index, {
                                        description: e.target.value,
                                    })
                                }
                                placeholder="Bubu + Dudu together"
                            />
                        </Field>
                        <div className="space-y-2">
                            <label className={labelClass}>Contains</label>
                            {items.map((item, itemIndex) => (
                                <div
                                    key={itemIndex}
                                    className="flex items-center gap-2"
                                >
                                    <select
                                        className={inputClass}
                                        value={item.variant_id}
                                        onChange={(e) =>
                                            update(index, {
                                                items: items.map((it, j) =>
                                                    j === itemIndex
                                                        ? {
                                                              ...it,
                                                              variant_id:
                                                                  Number(
                                                                      e.target
                                                                          .value,
                                                                  ),
                                                          }
                                                        : it,
                                                ),
                                            })
                                        }
                                    >
                                        {variants.map((v) => (
                                            <option key={v.id} value={v.id}>
                                                {variantLabel(v)}
                                            </option>
                                        ))}
                                    </select>
                                    <input
                                        type="number"
                                        min={1}
                                        max={10}
                                        className={`${inputClass} w-20`}
                                        value={item.quantity}
                                        onChange={(e) =>
                                            update(index, {
                                                items: items.map((it, j) =>
                                                    j === itemIndex
                                                        ? {
                                                              ...it,
                                                              quantity:
                                                                  Math.max(
                                                                      1,
                                                                      Number(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      ),
                                                                  ),
                                                          }
                                                        : it,
                                                ),
                                            })
                                        }
                                        aria-label="Quantity"
                                    />
                                    <button
                                        type="button"
                                        disabled={items.length <= 1}
                                        onClick={() =>
                                            update(index, {
                                                items: items.filter(
                                                    (_, j) => j !== itemIndex,
                                                ),
                                            })
                                        }
                                        className="shrink-0 text-slate-400 hover:text-red-500 disabled:opacity-30"
                                        aria-label="Remove item"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            ))}
                            <button
                                type="button"
                                disabled={items.length >= 10}
                                onClick={() =>
                                    update(index, {
                                        items: [
                                            ...items,
                                            {
                                                variant_id: variants[0].id,
                                                quantity: 1,
                                            },
                                        ],
                                    })
                                }
                                className="flex items-center gap-1 text-xs text-[#c8102e] hover:underline"
                            >
                                <Plus className="size-3.5" /> Add variant
                            </button>
                        </div>
                        <Field
                            label="Combo price (৳)"
                            hint={`Leave empty to charge the sum of its variants (currently ৳${partsTotal.toLocaleString()}). Set a lower price to offer a discount.`}
                        >
                            <input
                                max={99999999.99}
                                type="number"
                                min={0}
                                step="0.01"
                                className={inputClass}
                                value={combo.price ?? ''}
                                onChange={(e) =>
                                    update(index, {
                                        price:
                                            e.target.value === ''
                                                ? null
                                                : e.target.value,
                                    })
                                }
                            />
                        </Field>
                    </div>
                );
            })}
            <button
                type="button"
                disabled={combos.length >= 12}
                onClick={() =>
                    setCombos([
                        ...combos,
                        {
                            id: newId(),
                            name: '',
                            description: '',
                            badge: '',
                            price: null,
                            items: [
                                { variant_id: variants[0].id, quantity: 1 },
                            ],
                        },
                    ])
                }
                className="flex items-center gap-1 text-xs text-[#c8102e] hover:underline"
            >
                <Plus className="size-3.5" /> Add combo
            </button>

            <div className="space-y-3 rounded-xl border border-slate-100 p-3">
                <p className="text-xs font-semibold text-slate-700">
                    Order section
                </p>
                <Check
                    id="show_singles"
                    checked={selection.show_single_options !== false}
                    onChange={(v) =>
                        setSec({
                            selection: { ...selection, show_single_options: v },
                        })
                    }
                    label="Also let customers order each variant on its own"
                />
                <Field label="Section title">
                    <input
                        maxLength={80}
                        className={inputClass}
                        value={selection.title ?? ''}
                        onChange={(e) =>
                            setSec({
                                selection: {
                                    ...selection,
                                    title: e.target.value,
                                },
                            })
                        }
                    />
                </Field>
                <Field label="Section subtitle">
                    <input
                        maxLength={160}
                        className={inputClass}
                        value={selection.subtitle ?? ''}
                        onChange={(e) =>
                            setSec({
                                selection: {
                                    ...selection,
                                    subtitle: e.target.value,
                                },
                            })
                        }
                    />
                </Field>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <Field label="Notes field label">
                        <input
                            maxLength={40}
                            className={inputClass}
                            value={selection.notes_label ?? ''}
                            onChange={(e) =>
                                setSec({
                                    selection: {
                                        ...selection,
                                        notes_label: e.target.value,
                                    },
                                })
                            }
                        />
                    </Field>
                    <Field label="Notes placeholder">
                        <input
                            maxLength={100}
                            className={inputClass}
                            value={selection.notes_placeholder ?? ''}
                            onChange={(e) =>
                                setSec({
                                    selection: {
                                        ...selection,
                                        notes_placeholder: e.target.value,
                                    },
                                })
                            }
                        />
                    </Field>
                </div>
            </div>
        </>
    );
}

/** Delivery section texts and the areas shown as chips. */
export function DeliveryEditor({
    sec,
    setSec,
}: {
    sec: Sections;
    setSec: SetSec;
}) {
    const delivery = sec.delivery;
    const areas: string[] = delivery.areas ?? [];
    const set = (patch: Record<string, unknown>) =>
        setSec({ delivery: { ...delivery, ...patch } });

    return (
        <>
            <Field label="Title">
                <input
                    maxLength={80}
                    className={inputClass}
                    value={delivery.title ?? ''}
                    onChange={(e) => set({ title: e.target.value })}
                />
            </Field>
            <Field label="Text">
                <textarea
                    maxLength={400}
                    rows={3}
                    className={textareaClass}
                    value={delivery.text ?? ''}
                    onChange={(e) => set({ text: e.target.value })}
                />
                <CharCount
                    value={delivery.text ?? ''}
                    max={400}
                    className="mt-1 block text-right"
                />
            </Field>
            <div>
                <label className={labelClass}>
                    Delivery areas (shown as chips)
                </label>
                <div className="space-y-2">
                    {areas.map((area, i) => (
                        <div key={i} className="flex items-center gap-2">
                            <input
                                maxLength={40}
                                className={inputClass}
                                value={area}
                                onChange={(e) =>
                                    set({
                                        areas: areas.map((a, j) =>
                                            j === i ? e.target.value : a,
                                        ),
                                    })
                                }
                                placeholder="Dhaka"
                            />
                            <button
                                type="button"
                                onClick={() =>
                                    set({
                                        areas: areas.filter((_, j) => j !== i),
                                    })
                                }
                                className="shrink-0 text-slate-400 hover:text-red-500"
                                aria-label="Remove area"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </div>
                    ))}
                    <button
                        type="button"
                        disabled={areas.length >= 30}
                        onClick={() => set({ areas: [...areas, ''] })}
                        className="flex items-center gap-1 text-xs text-[#c8102e] hover:underline"
                    >
                        <Plus className="size-3.5" /> Add area
                    </button>
                </div>
            </div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <Field label="Order button text">
                    <input
                        maxLength={30}
                        className={inputClass}
                        value={delivery.cta_text ?? ''}
                        onChange={(e) => set({ cta_text: e.target.value })}
                    />
                </Field>
                <Field label="Text above the phone number">
                    <input
                        maxLength={60}
                        className={inputClass}
                        value={delivery.contact_label ?? ''}
                        onChange={(e) => set({ contact_label: e.target.value })}
                    />
                </Field>
            </div>
        </>
    );
}

/** Contact details used by the delivery section and the order success page. */
export function ContactEditor({
    sec,
    setSec,
}: {
    sec: Sections;
    setSec: SetSec;
}) {
    const contact = sec.contact ?? {};

    return (
        <>
            <p className="text-xs text-slate-500">
                Shown in the delivery section and on the order success page.
                Leave empty to use the site's contact details.
            </p>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <Field label="Contact phone">
                    <input
                        maxLength={30}
                        className={inputClass}
                        value={contact.phone ?? ''}
                        onChange={(e) =>
                            setSec({
                                contact: { ...contact, phone: e.target.value },
                            })
                        }
                        placeholder="01XXXXXXXXX"
                    />
                </Field>
                <Field label="Support email">
                    <input
                        maxLength={150}
                        className={inputClass}
                        value={contact.email ?? ''}
                        onChange={(e) =>
                            setSec({
                                contact: { ...contact, email: e.target.value },
                            })
                        }
                        placeholder="support@example.com"
                    />
                </Field>
            </div>
        </>
    );
}
