/**
 * DISC-65: Bangla for a landing page in the admin. The settings (Allow
 * Bangla, default language) sit beside the page's other settings; the
 * Bangla tab lists every text field of the page with a Bangla box under
 * the English text it translates. Items in lists (feature boxes, delivery
 * areas, reviews, FAQ) are added, removed and reordered in the English tab
 * only — their Bangla boxes follow by position. Empty Bangla boxes show the
 * English text on the page.
 */
import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import type { BuilderVariant } from '@/components/admin/landing-builder-fields';
import { RichTextEditor } from '@/components/admin/RichTextEditor';
import { CharCount } from '@/components/ui/char-count';
import { cn } from '@/lib/utils';

/** Loosely typed on purpose, like the page builder's own `sections`. */
type Sections = Record<string, any>;
export type BanglaSections = Record<string, any>;

const inputClass =
    'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-brand';
const textareaClass =
    'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand resize-none';
const labelClass = 'mb-1 block text-xs font-medium text-slate-600';
const banglaFont = {
    fontFamily:
        "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif, 'Kalpurush'",
};

/** The "Allow Bangla" switch and, when it's on, the default-language choice. */
export function BanglaSettingsFields({
    idPrefix,
    enabled,
    defaultLanguage,
    onEnabledChange,
    onDefaultLanguageChange,
    error,
}: {
    idPrefix: string;
    enabled: boolean;
    defaultLanguage: 'en' | 'bn';
    onEnabledChange: (enabled: boolean) => void;
    onDefaultLanguageChange: (language: 'en' | 'bn') => void;
    error?: string;
}) {
    return (
        <div className="space-y-2 rounded-lg border border-slate-100 p-3">
            <div className="flex items-center gap-2">
                <input
                    type="checkbox"
                    id={`${idPrefix}-bangla`}
                    checked={enabled}
                    onChange={(e) => onEnabledChange(e.target.checked)}
                    className="rounded border-slate-300"
                />
                <label
                    htmlFor={`${idPrefix}-bangla`}
                    className="text-sm font-medium text-slate-700"
                >
                    Allow Bangla (বাংলা) on this page
                </label>
            </div>
            <p className="text-xs text-slate-600">
                Visitors get an EN | বাং switch in the page header, and the
                page's text can be written in Bangla too. Their choice is
                remembered on their device.
            </p>
            {enabled && (
                <fieldset>
                    <legend className={labelClass}>Language shown first</legend>
                    <div className="flex flex-wrap gap-4">
                        {(
                            [
                                ['en', 'English'],
                                ['bn', 'বাংলা (Bangla)'],
                            ] as const
                        ).map(([value, label]) => (
                            <label
                                key={value}
                                className="flex cursor-pointer items-center gap-1.5 text-sm text-slate-700"
                            >
                                <input
                                    type="radio"
                                    name={`${idPrefix}-default-language`}
                                    value={value}
                                    checked={defaultLanguage === value}
                                    onChange={() =>
                                        onDefaultLanguageChange(value)
                                    }
                                    className="accent-brand"
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    );
}

/** The English | বাংলা switch at the top of the page editor. */
export function EditorLanguageTabs({
    value,
    onChange,
    banglaHasErrors,
}: {
    value: 'en' | 'bn';
    onChange: (value: 'en' | 'bn') => void;
    banglaHasErrors: boolean;
}) {
    return (
        <div
            role="tablist"
            aria-label="Editing language"
            className="inline-flex rounded-lg bg-slate-100 p-1"
        >
            {(
                [
                    ['en', 'English'],
                    ['bn', 'বাংলা'],
                ] as const
            ).map(([tab, label]) => (
                <button
                    key={tab}
                    type="button"
                    role="tab"
                    aria-selected={value === tab}
                    onClick={() => onChange(tab)}
                    style={tab === 'bn' ? banglaFont : undefined}
                    className={cn(
                        'relative rounded-md px-4 py-1.5 text-sm font-semibold transition-colors',
                        value === tab
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-600 hover:text-slate-900',
                    )}
                >
                    {label}
                    {tab === 'bn' && banglaHasErrors && (
                        <span
                            className="absolute top-1 right-1 size-2 rounded-full bg-red-500"
                            aria-label="has errors"
                        />
                    )}
                </button>
            ))}
        </div>
    );
}

/** Attribute names and their option values, in the order the product's variants use them. */
export function productAttributes(
    variants: BuilderVariant[],
): { name: string; values: string[] }[] {
    const attributes = new Map<string, string[]>();

    for (const variant of variants) {
        for (const [name, value] of Object.entries(variant.attributes ?? {})) {
            const values = attributes.get(name) ?? [];

            if (!values.includes(value)) {
                values.push(value);
            }

            attributes.set(name, values);
        }
    }

    return [...attributes.entries()].map(([name, values]) => ({
        name,
        values,
    }));
}

/**
 * The Bangla text as it should be saved: lists trimmed to the English
 * lists' length, combos limited to the page's current combos, and the
 * attribute/option name lists rebuilt from the product's current options
 * (keeping every Bangla name already written).
 */
export function pruneBangla(
    sec: Sections,
    bn: BanglaSections,
    variants: BuilderVariant[],
): BanglaSections {
    const next: BanglaSections = { ...bn };
    const trim = (section: string, key: string, length: number) => {
        if (Array.isArray(next[section]?.[key])) {
            next[section] = {
                ...next[section],
                [key]: next[section][key]
                    .slice(0, length)
                    .map((item: unknown) => item ?? null),
            };
        }
    };

    trim('hero', 'features', 3);
    trim('delivery', 'areas', (sec.delivery?.areas ?? []).length);
    trim('testimonials', 'items', (sec.testimonials?.items ?? []).length);
    trim('faq', 'items', (sec.faq?.items ?? []).length);

    if (next.combos && typeof next.combos === 'object') {
        const ids = new Set(
            ((sec.combos ?? []) as { id: string }[]).map((combo) => combo.id),
        );
        next.combos = Object.fromEntries(
            Object.entries(next.combos).filter(([id]) => ids.has(id)),
        );
    }

    const product = next.product ?? {};
    const attributes = productAttributes(variants);
    const attributeBn = new Map<string, string>(
        ((product.attribute_names ?? []) as { en: string; bn: string }[]).map(
            (row) => [row.en, row.bn],
        ),
    );
    const valueBn = new Map<string, string>(
        (
            (product.value_names ?? []) as {
                attribute: string;
                en: string;
                bn: string;
            }[]
        ).map((row) => [`${row.attribute}\u0000${row.en}`, row.bn]),
    );

    next.product = {
        ...product,
        attribute_names: attributes.map((attribute) => ({
            en: attribute.name,
            bn: attributeBn.get(attribute.name) ?? '',
        })),
        value_names: attributes.flatMap((attribute) =>
            attribute.values.map((value) => ({
                attribute: attribute.name,
                en: value,
                bn: valueBn.get(`${attribute.name}\u0000${value}`) ?? '',
            })),
        ),
    };

    return next;
}

/** Removes position `index` from one of the Bangla lists, so it keeps lining up with the English list. */
export function removeBanglaItem(
    bn: BanglaSections,
    section: string,
    key: string,
    index: number,
): BanglaSections {
    const list = bn[section]?.[key];

    if (!Array.isArray(list)) {
        return bn;
    }

    return {
        ...bn,
        [section]: {
            ...bn[section],
            [key]: list.filter((_: unknown, i: number) => i !== index),
        },
    };
}

function BanglaCard({
    title,
    hidden,
    children,
}: {
    title: string;
    hidden?: boolean;
    children: ReactNode;
}) {
    return (
        <details className="group rounded-xl border border-slate-200 bg-white shadow-sm">
            <summary className="flex cursor-pointer list-none items-center justify-between px-4 py-2.5">
                <span className="flex items-center gap-3">
                    <span className="text-sm font-semibold text-slate-700">
                        {title}
                    </span>
                    {hidden && (
                        <span className="text-[10px] tracking-wide text-slate-600 uppercase">
                            hidden on page
                        </span>
                    )}
                </span>
                <ChevronDown className="size-4 text-slate-400 transition-transform group-open:rotate-180" />
            </summary>
            <div className="space-y-3 border-t border-slate-100 px-4 py-3">
                {children}
            </div>
        </details>
    );
}

/** One Bangla box, with the English text it translates shown above it. */
function BanglaField({
    label,
    english,
    value,
    onChange,
    max,
    multiline = false,
    rows = 3,
    error,
}: {
    label: string;
    english: string | null | undefined;
    value: string | null | undefined;
    onChange: (value: string) => void;
    max: number;
    multiline?: boolean;
    rows?: number;
    error?: string;
}) {
    const current = value ?? '';

    return (
        <div>
            <label className={labelClass}>{label}</label>
            <p className="mb-1 line-clamp-3 text-[11px] whitespace-pre-line text-slate-500">
                <span className="font-semibold">English:</span>{' '}
                {english?.trim() ? english : '—'}
            </p>
            {multiline ? (
                <textarea
                    maxLength={max}
                    rows={rows}
                    lang="bn"
                    style={banglaFont}
                    className={textareaClass}
                    value={current}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="বাংলায় লিখুন (খালি রাখলে ইংরেজি দেখাবে)"
                />
            ) : (
                <input
                    maxLength={max}
                    lang="bn"
                    style={banglaFont}
                    className={inputClass}
                    value={current}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="বাংলায় লিখুন (খালি রাখলে ইংরেজি দেখাবে)"
                />
            )}
            <CharCount
                value={current}
                max={max}
                className="mt-0.5 block text-right text-[11px] leading-none"
            />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

/** Plain text of some HTML, for the "English:" reference under a rich-text box. */
function htmlToText(html: string | null | undefined): string {
    return (html ?? '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** The Bangla tab: every text field of the page, section by section. */
export function BanglaEditor({
    sec,
    bn,
    setBn,
    titleEn,
    titleBn,
    setTitleBn,
    productName,
    productDescription,
    variants,
    errors,
}: {
    sec: Sections;
    bn: BanglaSections;
    setBn: (next: BanglaSections) => void;
    titleEn: string;
    titleBn: string;
    setTitleBn: (value: string) => void;
    productName: string;
    productDescription: string | null;
    variants: BuilderVariant[];
    errors: Record<string, string | undefined>;
}) {
    /** Sets one Bangla value inside a section, e.g. set('hero', 'headline', '…'). */
    const set = (section: string, key: string, value: unknown) =>
        setBn({ ...bn, [section]: { ...(bn[section] ?? {}), [key]: value } });

    /** Sets one field of item `index` in a Bangla list that lines up with an English list. */
    const setItem = (
        section: string,
        key: string,
        length: number,
        index: number,
        patch: Record<string, string> | string,
    ) => {
        const current: unknown[] = Array.isArray(bn[section]?.[key])
            ? bn[section][key]
            : [];
        const list = Array.from(
            { length: Math.max(length, current.length) },
            (_, i) => current[i] ?? (typeof patch === 'string' ? '' : {}),
        );
        list[index] =
            typeof patch === 'string'
                ? patch
                : { ...((list[index] as object) ?? {}), ...patch };
        set(section, key, list);
    };

    const get = (section: string, key: string): string =>
        (bn[section]?.[key] as string | undefined) ?? '';
    const err = (path: string) => errors[`sections_bn.${path}`];

    const features = (sec.hero?.features ?? []) as {
        title: string;
        text: string;
    }[];
    const areas = (sec.delivery?.areas ?? []) as string[];
    const testimonials = (sec.testimonials?.items ?? []) as {
        name: string;
        text: string;
    }[];
    const faq = (sec.faq?.items ?? []) as {
        question: string;
        answer: string;
    }[];
    const combos = (sec.combos ?? []) as {
        id: string;
        name: string;
        description?: string;
        badge?: string;
    }[];
    const attributes = productAttributes(variants);
    const product = bn.product ?? {};
    const attributeBn = (name: string): string =>
        ((product.attribute_names ?? []) as { en: string; bn: string }[]).find(
            (row) => row.en === name,
        )?.bn ?? '';
    const valueBn = (attribute: string, value: string): string =>
        (
            (product.value_names ?? []) as {
                attribute: string;
                en: string;
                bn: string;
            }[]
        ).find((row) => row.attribute === attribute && row.en === value)?.bn ??
        '';
    const setAttributeBn = (name: string, value: string) => {
        const rows = attributes.map((attribute) => ({
            en: attribute.name,
            bn: attribute.name === name ? value : attributeBn(attribute.name),
        }));
        set('product', 'attribute_names', rows);
    };
    const setValueBn = (attribute: string, en: string, value: string) => {
        const rows = attributes.flatMap((a) =>
            a.values.map((v) => ({
                attribute: a.name,
                en: v,
                bn:
                    a.name === attribute && v === en
                        ? value
                        : valueBn(a.name, v),
            })),
        );
        set('product', 'value_names', rows);
    };

    return (
        <div className="space-y-3">
            <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Write the Bangla version of each text. Anything left empty shows
                the English text. To add, remove or reorder items (FAQ, reviews,
                delivery areas, combos), use the English tab — the Bangla boxes
                follow.
            </p>

            <BanglaCard title="Page Title">
                <BanglaField
                    label="Page title (browser tab)"
                    english={titleEn}
                    value={titleBn}
                    onChange={setTitleBn}
                    max={100}
                    error={errors.title_bn}
                />
            </BanglaCard>

            <BanglaCard title="Product">
                <BanglaField
                    label="Product name"
                    english={productName}
                    value={product.name}
                    onChange={(v) => set('product', 'name', v)}
                    max={255}
                    error={err('product.name')}
                />
                <div>
                    <label className={labelClass}>
                        Product description (HTML allowed) — used when "Use
                        product's own description" is on
                    </label>
                    <p className="mb-1 line-clamp-3 text-[11px] text-slate-500">
                        <span className="font-semibold">English:</span>{' '}
                        {htmlToText(productDescription) || '—'}
                    </p>
                    <textarea
                        maxLength={20000}
                        rows={6}
                        lang="bn"
                        style={banglaFont}
                        className={textareaClass}
                        value={product.description ?? ''}
                        onChange={(e) =>
                            set('product', 'description', e.target.value)
                        }
                    />
                    <CharCount
                        value={product.description ?? ''}
                        max={20000}
                        className="mt-0.5 block text-right text-[11px] leading-none"
                    />
                    {err('product.description') && (
                        <p className="mt-1 text-xs text-red-600">
                            {err('product.description')}
                        </p>
                    )}
                </div>
                {attributes.length > 0 && (
                    <div className="space-y-3 rounded-lg border border-slate-100 p-3">
                        <p className="text-xs font-semibold text-slate-700">
                            Option names (e.g. Color → রং, Red → লাল)
                        </p>
                        {attributes.map((attribute) => (
                            <div key={attribute.name} className="space-y-2">
                                <BanglaField
                                    label={`Attribute: ${attribute.name}`}
                                    english={attribute.name}
                                    value={attributeBn(attribute.name)}
                                    onChange={(v) =>
                                        setAttributeBn(attribute.name, v)
                                    }
                                    max={100}
                                />
                                <div className="grid grid-cols-1 gap-2 pl-3 sm:grid-cols-2">
                                    {attribute.values.map((value) => (
                                        <BanglaField
                                            key={value}
                                            label={value}
                                            english={value}
                                            value={valueBn(
                                                attribute.name,
                                                value,
                                            )}
                                            onChange={(v) =>
                                                setValueBn(
                                                    attribute.name,
                                                    value,
                                                    v,
                                                )
                                            }
                                            max={100}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </BanglaCard>

            <BanglaCard
                title="Hero Section"
                hidden={sec.hero?.enabled === false}
            >
                <BanglaField
                    label="Badge"
                    english={sec.hero?.badge}
                    value={get('hero', 'badge')}
                    onChange={(v) => set('hero', 'badge', v)}
                    max={40}
                    error={err('hero.badge')}
                />
                <BanglaField
                    label="Headline"
                    english={sec.hero?.headline}
                    value={get('hero', 'headline')}
                    onChange={(v) => set('hero', 'headline', v)}
                    max={120}
                    error={err('hero.headline')}
                />
                <BanglaField
                    label="Sub-headline"
                    english={sec.hero?.subheadline}
                    value={get('hero', 'subheadline')}
                    onChange={(v) => set('hero', 'subheadline', v)}
                    max={250}
                    error={err('hero.subheadline')}
                />
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <BanglaField
                        label="CTA button text"
                        english={sec.hero?.cta_text}
                        value={get('hero', 'cta_text')}
                        onChange={(v) => set('hero', 'cta_text', v)}
                        max={30}
                        error={err('hero.cta_text')}
                    />
                    <BanglaField
                        label="Second button text"
                        english={sec.hero?.secondary_cta_text}
                        value={get('hero', 'secondary_cta_text')}
                        onChange={(v) => set('hero', 'secondary_cta_text', v)}
                        max={30}
                        error={err('hero.secondary_cta_text')}
                    />
                </div>
                {[0, 1, 2].map((i) => (
                    <div
                        key={i}
                        className="grid grid-cols-1 gap-3 rounded-lg border border-slate-100 p-3 sm:grid-cols-2"
                    >
                        <BanglaField
                            label={`Feature box ${i + 1} title`}
                            english={features[i]?.title}
                            value={bn.hero?.features?.[i]?.title}
                            onChange={(v) =>
                                setItem('hero', 'features', 3, i, { title: v })
                            }
                            max={60}
                            error={err(`hero.features.${i}.title`)}
                        />
                        <BanglaField
                            label={`Feature box ${i + 1} description`}
                            english={features[i]?.text}
                            value={bn.hero?.features?.[i]?.text}
                            onChange={(v) =>
                                setItem('hero', 'features', 3, i, { text: v })
                            }
                            max={140}
                            error={err(`hero.features.${i}.text`)}
                        />
                    </div>
                ))}
            </BanglaCard>

            <BanglaCard title="Combos & Order Options">
                {combos.length === 0 && (
                    <p className="text-xs text-slate-600">
                        No combos on this page.
                    </p>
                )}
                {combos.map((combo) => (
                    <div
                        key={combo.id}
                        className="space-y-2 rounded-lg border border-slate-100 p-3"
                    >
                        <BanglaField
                            label="Combo name"
                            english={combo.name}
                            value={bn.combos?.[combo.id]?.name}
                            onChange={(v) =>
                                set('combos', combo.id, {
                                    ...(bn.combos?.[combo.id] ?? {}),
                                    name: v,
                                })
                            }
                            max={50}
                            error={err(`combos.${combo.id}.name`)}
                        />
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <BanglaField
                                label="Badge"
                                english={combo.badge}
                                value={bn.combos?.[combo.id]?.badge}
                                onChange={(v) =>
                                    set('combos', combo.id, {
                                        ...(bn.combos?.[combo.id] ?? {}),
                                        badge: v,
                                    })
                                }
                                max={20}
                                error={err(`combos.${combo.id}.badge`)}
                            />
                            <BanglaField
                                label="Short description"
                                english={combo.description}
                                value={bn.combos?.[combo.id]?.description}
                                onChange={(v) =>
                                    set('combos', combo.id, {
                                        ...(bn.combos?.[combo.id] ?? {}),
                                        description: v,
                                    })
                                }
                                max={100}
                                error={err(`combos.${combo.id}.description`)}
                            />
                        </div>
                    </div>
                ))}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <BanglaField
                        label="Order section title"
                        english={sec.selection?.title}
                        value={get('selection', 'title')}
                        onChange={(v) => set('selection', 'title', v)}
                        max={80}
                        error={err('selection.title')}
                    />
                    <BanglaField
                        label="Order section subtitle"
                        english={sec.selection?.subtitle}
                        value={get('selection', 'subtitle')}
                        onChange={(v) => set('selection', 'subtitle', v)}
                        max={160}
                        error={err('selection.subtitle')}
                    />
                    <BanglaField
                        label="Notes field label"
                        english={sec.selection?.notes_label}
                        value={get('selection', 'notes_label')}
                        onChange={(v) => set('selection', 'notes_label', v)}
                        max={40}
                        error={err('selection.notes_label')}
                    />
                    <BanglaField
                        label="Notes placeholder"
                        english={sec.selection?.notes_placeholder}
                        value={get('selection', 'notes_placeholder')}
                        onChange={(v) =>
                            set('selection', 'notes_placeholder', v)
                        }
                        max={100}
                        error={err('selection.notes_placeholder')}
                    />
                </div>
            </BanglaCard>

            <BanglaCard
                title="Delivery Info"
                hidden={sec.delivery?.enabled === false}
            >
                <BanglaField
                    label="Title"
                    english={sec.delivery?.title}
                    value={get('delivery', 'title')}
                    onChange={(v) => set('delivery', 'title', v)}
                    max={80}
                    error={err('delivery.title')}
                />
                <BanglaField
                    label="Text"
                    english={sec.delivery?.text}
                    value={get('delivery', 'text')}
                    onChange={(v) => set('delivery', 'text', v)}
                    max={400}
                    multiline
                    error={err('delivery.text')}
                />
                {areas.length > 0 && (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {areas.map((area, i) => (
                            <BanglaField
                                key={i}
                                label={`Delivery area ${i + 1}`}
                                english={area}
                                value={bn.delivery?.areas?.[i]}
                                onChange={(v) =>
                                    setItem(
                                        'delivery',
                                        'areas',
                                        areas.length,
                                        i,
                                        v,
                                    )
                                }
                                max={40}
                                error={err(`delivery.areas.${i}`)}
                            />
                        ))}
                    </div>
                )}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <BanglaField
                        label="Order button text"
                        english={sec.delivery?.cta_text}
                        value={get('delivery', 'cta_text')}
                        onChange={(v) => set('delivery', 'cta_text', v)}
                        max={30}
                        error={err('delivery.cta_text')}
                    />
                    <BanglaField
                        label="Text above the phone number"
                        english={sec.delivery?.contact_label}
                        value={get('delivery', 'contact_label')}
                        onChange={(v) => set('delivery', 'contact_label', v)}
                        max={60}
                        error={err('delivery.contact_label')}
                    />
                </div>
            </BanglaCard>

            <BanglaCard
                title="Product Description"
                hidden={sec.description?.enabled === false}
            >
                <BanglaField
                    label="Badge"
                    english={sec.description?.badge}
                    value={get('description', 'badge')}
                    onChange={(v) => set('description', 'badge', v)}
                    max={40}
                    error={err('description.badge')}
                />
                <BanglaField
                    label="Section title"
                    english={sec.description?.title}
                    value={get('description', 'title')}
                    onChange={(v) => set('description', 'title', v)}
                    max={120}
                    multiline
                    error={err('description.title')}
                />
                {!sec.description?.use_product_description && (
                    <div>
                        <label className={labelClass}>
                            Custom content (HTML allowed)
                        </label>
                        <p className="mb-1 line-clamp-3 text-[11px] text-slate-500">
                            <span className="font-semibold">English:</span>{' '}
                            {htmlToText(sec.description?.custom_content) || '—'}
                        </p>
                        <textarea
                            maxLength={20000}
                            rows={6}
                            lang="bn"
                            style={banglaFont}
                            className={textareaClass}
                            value={get('description', 'custom_content')}
                            onChange={(e) =>
                                set(
                                    'description',
                                    'custom_content',
                                    e.target.value,
                                )
                            }
                        />
                        <CharCount
                            value={get('description', 'custom_content')}
                            max={20000}
                            className="mt-0.5 block text-right text-[11px] leading-none"
                        />
                        {err('description.custom_content') && (
                            <p className="mt-1 text-xs text-red-600">
                                {err('description.custom_content')}
                            </p>
                        )}
                    </div>
                )}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <BanglaField
                        label="Option picker title"
                        english={sec.description?.selection_title}
                        value={get('description', 'selection_title')}
                        onChange={(v) =>
                            set('description', 'selection_title', v)
                        }
                        max={60}
                        error={err('description.selection_title')}
                    />
                    <BanglaField
                        label="Option picker hint"
                        english={sec.description?.selection_hint}
                        value={get('description', 'selection_hint')}
                        onChange={(v) =>
                            set('description', 'selection_hint', v)
                        }
                        max={140}
                        error={err('description.selection_hint')}
                    />
                </div>
            </BanglaCard>

            <BanglaCard
                title="Product Gallery"
                hidden={sec.gallery?.enabled === false}
            >
                <BanglaField
                    label="Section title"
                    english={sec.gallery?.title}
                    value={get('gallery', 'title')}
                    onChange={(v) => set('gallery', 'title', v)}
                    max={60}
                    error={err('gallery.title')}
                />
            </BanglaCard>

            <BanglaCard
                title="Customer Testimonials"
                hidden={!sec.testimonials?.enabled}
            >
                <BanglaField
                    label="Section title"
                    english={sec.testimonials?.title}
                    value={get('testimonials', 'title')}
                    onChange={(v) => set('testimonials', 'title', v)}
                    max={80}
                    error={err('testimonials.title')}
                />
                {(sec.testimonials?.mode ?? 'text') === 'text' &&
                    testimonials.map((item, i) => (
                        <div
                            key={i}
                            className="space-y-2 rounded-lg border border-slate-100 p-3"
                        >
                            <span className="text-xs font-semibold text-slate-600">
                                Review #{i + 1}
                            </span>
                            <BanglaField
                                label="Customer name"
                                english={item.name}
                                value={bn.testimonials?.items?.[i]?.name}
                                onChange={(v) =>
                                    setItem(
                                        'testimonials',
                                        'items',
                                        testimonials.length,
                                        i,
                                        { name: v },
                                    )
                                }
                                max={60}
                                error={err(`testimonials.items.${i}.name`)}
                            />
                            <div>
                                <label className={labelClass}>
                                    Review text
                                </label>
                                <p className="mb-1 line-clamp-3 text-[11px] text-slate-500">
                                    <span className="font-semibold">
                                        English:
                                    </span>{' '}
                                    {htmlToText(item.text) || '—'}
                                </p>
                                <RichTextEditor
                                    value={
                                        bn.testimonials?.items?.[i]?.text ?? ''
                                    }
                                    minHeight={110}
                                    maxLength={2000}
                                    maxTextLength={400}
                                    onChange={(html) =>
                                        setItem(
                                            'testimonials',
                                            'items',
                                            testimonials.length,
                                            i,
                                            { text: html },
                                        )
                                    }
                                />
                                {err(`testimonials.items.${i}.text`) && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {err(`testimonials.items.${i}.text`)}
                                    </p>
                                )}
                            </div>
                        </div>
                    ))}
            </BanglaCard>

            <BanglaCard title="FAQ" hidden={!sec.faq?.enabled}>
                <BanglaField
                    label="Section title"
                    english={sec.faq?.title}
                    value={get('faq', 'title')}
                    onChange={(v) => set('faq', 'title', v)}
                    max={80}
                    error={err('faq.title')}
                />
                {faq.map((item, i) => (
                    <div
                        key={i}
                        className="space-y-2 rounded-lg border border-slate-100 p-3"
                    >
                        <span className="text-xs font-semibold text-slate-600">
                            Q&A #{i + 1}
                        </span>
                        <BanglaField
                            label="Question"
                            english={item.question}
                            value={bn.faq?.items?.[i]?.question}
                            onChange={(v) =>
                                setItem('faq', 'items', faq.length, i, {
                                    question: v,
                                })
                            }
                            max={160}
                            error={err(`faq.items.${i}.question`)}
                        />
                        <BanglaField
                            label="Answer"
                            english={item.answer}
                            value={bn.faq?.items?.[i]?.answer}
                            onChange={(v) =>
                                setItem('faq', 'items', faq.length, i, {
                                    answer: v,
                                })
                            }
                            max={800}
                            multiline
                            rows={2}
                            error={err(`faq.items.${i}.answer`)}
                        />
                    </div>
                ))}
            </BanglaCard>

            <BanglaCard title="Footer" hidden={!sec.footer?.enabled}>
                <BanglaField
                    label="Footer text"
                    english={sec.footer?.text}
                    value={get('footer', 'text')}
                    onChange={(v) => set('footer', 'text', v)}
                    max={400}
                    multiline
                    error={err('footer.text')}
                />
            </BanglaCard>
        </div>
    );
}
