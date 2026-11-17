import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { usePriceRangeCommit } from '@/lib/hooks/usePriceRangeCommit';
import { cn } from '@/lib/utils';

/*
 * The storefront's product filter sidebar, shared by the catalog/category
 * listing (pages/catalog/ProductListing.tsx) and Search Results
 * (pages/search/SearchResults.tsx) so both look and behave the same
 * (DISC-56). Each page keeps its own filter state and decides which
 * sections to show; these are only the building blocks.
 */

const PRICE_SLIDER_STEP = 10;
/**
 * Invisible native range input stretched over the custom track. Only its thumb
 * (sized to the visual handle) accepts pointer events, so overlapping inputs
 * never steal each other's drags.
 */
const PRICE_SLIDER_INPUT_CLASS = cn(
    // Overhang by half a thumb (12px) so the native thumb centre spans exactly 0–100% of the track.
    'pointer-events-none absolute inset-x-[-12px] top-0 h-full w-[calc(100%+24px)] cursor-pointer appearance-none bg-transparent opacity-0',
    '[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-6 [&::-webkit-slider-thumb]:cursor-grab [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:active:cursor-grabbing',
    '[&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-6 [&::-moz-range-thumb]:cursor-grab [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:active:cursor-grabbing',
);

// ── Price Range Filter ────────────────────────────────────────────────────────
export function PriceRangeFilter({
    lo,
    hi,
    cap,
    onApply,
}: {
    lo: number;
    hi: number;
    cap: number;
    onApply: (lo: number, hi: number) => void;
}): React.JSX.Element {
    const [inputLo, setInputLo] = useState(String(lo));
    const [inputHi, setInputHi] = useState(String(hi));

    // eslint-disable-next-line react-hooks/set-state-in-effect -- resets local draft/UI state when the server props or URL change
    useEffect(() => setInputLo(String(lo)), [lo]);
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resets local draft/UI state when the server props or URL change
    useEffect(() => setInputHi(String(hi)), [hi]);

    const priceCommit = usePriceRangeCommit(onApply);

    const parseLo = (raw: string): number =>
        Math.max(0, Math.min(Number(raw) || 0, cap));
    const parseHi = (raw: string, loValue: number): number =>
        Math.max(loValue, Math.min(raw === '' ? cap : Number(raw) || cap, cap));

    // Slider mirrors the (possibly half-typed) inputs, clamped to a valid range.
    const sliderLo = Math.max(0, Math.min(Number(inputLo) || 0, cap));
    const sliderHi = Math.max(
        sliderLo,
        Math.min(inputHi === '' ? cap : Number(inputHi) || cap, cap),
    );
    const loPercent = cap > 0 ? (sliderLo / cap) * 100 : 0;
    const hiPercent = cap > 0 ? (sliderHi / cap) * 100 : 100;
    // When both handles meet, keep the one that can still move on top.
    const isLoOnTop = loPercent > 50;

    const commitSlider = (): void => priceCommit.flush();
    const isCommitKey = (key: string): boolean =>
        [
            'ArrowLeft',
            'ArrowRight',
            'ArrowUp',
            'ArrowDown',
            'Home',
            'End',
            'PageUp',
            'PageDown',
        ].includes(key);

    const noSpinnerClass =
        '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';

    return (
        <div className="space-y-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]/60 p-3">
            <div className="flex items-end gap-2">
                <div className="flex-1 space-y-1">
                    <label className="block text-[10px] font-bold tracking-[0.12em] text-[var(--color-text-secondary)] uppercase">
                        Min
                    </label>
                    <div className="relative">
                        <span className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-xs font-semibold text-[var(--color-text-secondary)]">
                            ৳
                        </span>
                        <input
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={inputLo}
                            onChange={(e) => {
                                const { value } = e.target;
                                setInputLo(value);
                                const parsedLo = parseLo(value);
                                priceCommit.schedule(
                                    parsedLo,
                                    parseHi(inputHi, parsedLo),
                                );
                            }}
                            className={cn(
                                'h-8 w-full rounded-lg border border-[var(--color-border)] bg-white py-0 pr-2 pl-5 text-[13px] font-medium text-[var(--color-text-primary)] shadow-sm transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/15 focus:outline-none',
                                noSpinnerClass,
                            )}
                        />
                    </div>
                </div>
                <div className="mb-4 h-px w-2.5 shrink-0 bg-[var(--color-border-strong)]" />
                <div className="flex-1 space-y-1">
                    <label className="block text-[10px] font-bold tracking-[0.12em] text-[var(--color-text-secondary)] uppercase">
                        Max
                    </label>
                    <div className="relative">
                        <span className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-xs font-semibold text-[var(--color-text-secondary)]">
                            ৳
                        </span>
                        <input
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={inputHi}
                            onChange={(e) => {
                                const { value } = e.target;
                                setInputHi(value);
                                const parsedLo = parseLo(inputLo);
                                priceCommit.schedule(
                                    parsedLo,
                                    parseHi(value, parsedLo),
                                );
                            }}
                            className={cn(
                                'h-8 w-full rounded-lg border border-[var(--color-border)] bg-white py-0 pr-2 pl-5 text-[13px] font-medium text-[var(--color-text-primary)] shadow-sm transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/15 focus:outline-none',
                                noSpinnerClass,
                            )}
                        />
                    </div>
                </div>
            </div>

            {/* Dual-handle slider. The native range inputs are invisible and ignore
                pointer events except on their own thumb, so each handle is only
                draggable where it is. While a handle is held only the numbers
                move; the filter applies once, on release (usePriceRangeCommit),
                so a slow drag can't flood the server with requests. */}
            <div className="px-1">
                <div className="relative mx-2.5 h-5">
                    <div className="absolute inset-x-0 top-1/2 h-2 -translate-y-1/2 rounded-full bg-[var(--color-border)]" />
                    <div
                        className="absolute top-1/2 h-2 -translate-y-1/2 rounded-full bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-accent)]"
                        style={{
                            left: `${loPercent}%`,
                            width: `${Math.max(0, hiPercent - loPercent)}%`,
                        }}
                    />
                    <input
                        type="range"
                        min={0}
                        max={cap}
                        step={PRICE_SLIDER_STEP}
                        value={sliderLo}
                        aria-label="Minimum price"
                        onChange={(e) => {
                            const nextLo = Math.min(
                                Number(e.target.value),
                                sliderHi,
                            );
                            setInputLo(String(nextLo));
                            priceCommit.schedule(nextLo, sliderHi);
                        }}
                        onPointerDown={priceCommit.startDrag}
                        onKeyUp={(e) => isCommitKey(e.key) && commitSlider()}
                        className={cn(
                            PRICE_SLIDER_INPUT_CLASS,
                            'peer/lo',
                            isLoOnTop ? 'z-30' : 'z-20',
                        )}
                    />
                    <span
                        className="pointer-events-none absolute top-1/2 z-10 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[var(--color-primary)] bg-white shadow-[0_2px_8px_rgba(0,0,0,0.18)] transition-transform peer-hover/lo:scale-110 peer-focus-visible/lo:ring-4 peer-focus-visible/lo:ring-[var(--color-primary)]/20"
                        style={{ left: `${loPercent}%` }}
                    />
                    <input
                        type="range"
                        min={0}
                        max={cap}
                        step={PRICE_SLIDER_STEP}
                        value={sliderHi}
                        aria-label="Maximum price"
                        onChange={(e) => {
                            const nextHi = Math.max(
                                Number(e.target.value),
                                sliderLo,
                            );
                            setInputHi(String(nextHi));
                            priceCommit.schedule(sliderLo, nextHi);
                        }}
                        onPointerDown={priceCommit.startDrag}
                        onKeyUp={(e) => isCommitKey(e.key) && commitSlider()}
                        className={cn(
                            PRICE_SLIDER_INPUT_CLASS,
                            'peer/hi',
                            isLoOnTop ? 'z-20' : 'z-30',
                        )}
                    />
                    <span
                        className="pointer-events-none absolute top-1/2 z-10 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[var(--color-primary)] bg-white shadow-[0_2px_8px_rgba(0,0,0,0.18)] transition-transform peer-hover/hi:scale-110 peer-focus-visible/hi:ring-4 peer-focus-visible/hi:ring-[var(--color-primary)]/20"
                        style={{ left: `${hiPercent}%` }}
                    />
                </div>
                <div className="mt-1 flex justify-between text-[11px] font-semibold text-[var(--color-text-secondary)]">
                    <span>৳0</span>
                    <span>৳{cap.toLocaleString('en-BD')}</span>
                </div>
            </div>
        </div>
    );
}

// ── Flat collapsible section (Envato-inspired: no card chrome, just a divider) ─
export function FilterSection({
    title,
    defaultOpen = true,
    children,
}: {
    title: string;
    defaultOpen?: boolean;
    children: ReactNode;
}): React.JSX.Element {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className="border-b border-[var(--color-border)] py-3 first:pt-0 last:border-b-0 last:pb-0">
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                className="flex w-full items-center justify-between text-[13px] font-semibold text-[var(--color-text-primary)]"
                aria-expanded={open}
            >
                {title}
                <ChevronDown
                    className={cn(
                        'size-4 text-[var(--color-text-muted)] transition-transform duration-200',
                        open ? '' : '-rotate-90',
                    )}
                />
            </button>
            {open ? <div className="mt-2">{children}</div> : null}
        </div>
    );
}

// ── Filter row primitives (flat rows, right-aligned counts — no card borders) ──
export function FilterCheckboxRow({
    label,
    count,
    checked,
    onChange,
}: {
    label: string;
    count?: number;
    checked: boolean;
    onChange: () => void;
}): React.JSX.Element {
    return (
        <label className="flex cursor-pointer items-center justify-between gap-3 rounded-md px-1 py-1 text-[13px] transition hover:bg-[var(--color-surface-alt)]">
            <span className="flex min-w-0 items-center gap-2 text-[var(--color-text-secondary)]">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={onChange}
                    className="size-3.5 shrink-0 rounded border-[var(--color-border)] text-[var(--color-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]"
                />
                <span className="min-w-0 break-words">{label}</span>
            </span>
            {typeof count === 'number' ? (
                <span className="shrink-0 text-xs text-[var(--color-text-secondary)]">
                    {count}
                </span>
            ) : null}
        </label>
    );
}

export function CategoryRow({
    label,
    count,
    active = false,
    indent = false,
    onClick,
}: {
    label: string;
    count?: number;
    active?: boolean;
    indent?: boolean;
    onClick: () => void;
}): React.JSX.Element {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-center justify-between gap-3 rounded-md py-1 text-left text-[13px] transition hover:bg-[var(--color-surface-alt)]',
                indent ? 'pl-4' : '',
                active
                    ? 'font-semibold text-[var(--color-primary)]'
                    : 'text-[var(--color-text-secondary)]',
            )}
        >
            <span className="min-w-0 px-1 break-words">{label}</span>
            {typeof count === 'number' ? (
                <span className="shrink-0 px-1 text-xs text-[var(--color-text-secondary)]">
                    {count}
                </span>
            ) : null}
        </button>
    );
}

// ── Desktop filter panel: sticky, scrolls on its own, "Filters" header with Clear all ─
export function FilterPanel({
    activeCount,
    canClear,
    onClearAll,
    children,
}: {
    activeCount: number;
    canClear: boolean;
    onClearAll: () => void;
    children: ReactNode;
}): React.JSX.Element {
    return (
        <div className="sticky top-28 flex max-h-[calc(100dvh-8.5rem)] flex-col rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] py-3.5 pr-1.5 pl-4 shadow-[var(--shadow-card)]">
            <div className="mr-2.5 mb-3 flex shrink-0 items-center justify-between gap-3 border-b border-[var(--color-border)] pb-2.5">
                <h2 className="text-sm font-bold text-[var(--color-text-primary)]">
                    Filters
                    {activeCount > 0 ? (
                        <span className="ml-1.5 text-xs font-normal text-[var(--color-text-secondary)]">
                            ({activeCount} active)
                        </span>
                    ) : null}
                </h2>
                {canClear ? (
                    <button
                        type="button"
                        onClick={onClearAll}
                        className="text-xs font-semibold text-[var(--color-primary)] hover:underline"
                    >
                        Clear all
                    </button>
                ) : null}
            </div>
            <div className="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain pr-2.5 [scrollbar-width:thin]">
                {children}
            </div>
        </div>
    );
}

// ── Brand checklist: the first few, then "Show N more" ───────────────────────
const BRANDS_SHOWN = 6;

export function BrandFilterList({
    brands,
    selected,
    onToggle,
}: {
    brands: {
        id: number | string;
        slug: string;
        name: string;
        products_count?: number;
    }[];
    selected: string[];
    onToggle: (slug: string) => void;
}): React.JSX.Element {
    const [showAll, setShowAll] = useState(false);
    const visible = showAll ? brands : brands.slice(0, BRANDS_SHOWN);

    return (
        <div className="space-y-0.5">
            {visible.map((brand) => (
                <FilterCheckboxRow
                    key={brand.id}
                    label={brand.name}
                    count={brand.products_count}
                    checked={selected.includes(brand.slug)}
                    onChange={() => onToggle(brand.slug)}
                />
            ))}
            {brands.length > BRANDS_SHOWN ? (
                <button
                    type="button"
                    onClick={() => setShowAll((current) => !current)}
                    className="mt-0.5 inline-flex items-center gap-1 px-1 text-xs font-semibold text-[var(--color-primary)] hover:underline"
                >
                    {showAll
                        ? 'Show less'
                        : `Show ${brands.length - BRANDS_SHOWN} more`}
                    <ChevronDown
                        className={cn(
                            'size-3.5 transition-transform',
                            showAll ? 'rotate-180' : '',
                        )}
                    />
                </button>
            ) : null}
        </div>
    );
}
