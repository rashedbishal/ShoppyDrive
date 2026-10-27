import { Pipette, Shuffle } from 'lucide-react';
import React from 'react';
import { ComboboxField } from '@/components/ui/combobox';
import { CATEGORY_COLOR_OPTIONS, isHexColor, resolveCategoryColor } from '@/lib/category-colors';
import { cn } from '@/lib/utils';

const CUSTOM_VALUE = '__custom__';
const AUTO_VALUE = '__auto__';
const DEFAULT_CUSTOM_HEX = '#3b82f6';

const PICKER_OPTIONS = [
    { value: AUTO_VALUE, label: 'Automatic (rotate colors)' },
    ...CATEGORY_COLOR_OPTIONS,
    { value: CUSTOM_VALUE, label: 'Custom color…' },
];

interface ColorPickerProps {
    value: string | null;
    onChange: (color: string | null) => void;
    error?: string;
    className?: string;
}

export function ColorPicker({ value, onChange, error, className }: ColorPickerProps): React.JSX.Element {
    const isCustom = isHexColor(value);
    const selectValue = value == null ? AUTO_VALUE : isCustom ? CUSTOM_VALUE : value;
    const previewColor = resolveCategoryColor(value, 0);

    const handleSelect = (next: string) => {
        if (next === AUTO_VALUE) {
            onChange(null);
        } else if (next === CUSTOM_VALUE) {
            onChange(isCustom ? value : DEFAULT_CUSTOM_HEX);
        } else {
            onChange(next);
        }
    };

    return (
        <div className={cn('w-full', className)}>
            <div className="flex items-center gap-3">
                <span
                    className={cn(
                        'size-11 shrink-0 rounded-lg border',
                        error ? 'border-red-400' : 'border-slate-200',
                    )}
                    style={{ backgroundColor: previewColor }}
                />
                <ComboboxField
                    value={selectValue}
                    onChange={handleSelect}
                    options={PICKER_OPTIONS}
                    error={error}
                    placeholder="Search colors… (e.g. rose, sky, slate)"
                    emptyMessage="No colors match that search."
                    maxResults={200}
                    optionsClassName="grid grid-cols-9 gap-1 sm:grid-cols-10"
                    optionClassName="group relative flex items-center justify-center rounded-md p-0.5 outline-none cursor-pointer data-focus:ring-2 data-focus:ring-[#c8102e]/50"
                    renderOption={(option, { selected }) => {
                        if (option.value === AUTO_VALUE) {
                            return (
                                <span
                                    title={option.label}
                                    className={cn(
                                        'flex size-7 items-center justify-center rounded-md border border-dashed bg-white text-slate-400',
                                        selected ? 'border-[#c8102e] text-[#c8102e]' : 'border-slate-300',
                                    )}
                                >
                                    <Shuffle className="size-3.5" />
                                </span>
                            );
                        }

                        if (option.value === CUSTOM_VALUE) {
                            return (
                                <span
                                    title={option.label}
                                    className={cn(
                                        'flex size-7 items-center justify-center rounded-md border bg-white text-slate-400',
                                        selected ? 'border-[#c8102e] text-[#c8102e]' : 'border-slate-300',
                                    )}
                                >
                                    <Pipette className="size-3.5" />
                                </span>
                            );
                        }

                        return (
                            <span
                                title={option.label}
                                style={{ backgroundColor: option.value }}
                                className={cn(
                                    'size-7 rounded-md border',
                                    selected
                                        ? 'border-white ring-2 ring-[#c8102e] ring-offset-1'
                                        : 'border-black/5',
                                )}
                            />
                        );
                    }}
                />
                {isCustom && (
                    <div className="flex shrink-0 items-center gap-2">
                        <input
                            type="color"
                            value={value ?? DEFAULT_CUSTOM_HEX}
                            onChange={(e) => onChange(e.target.value)}
                            className="size-11 cursor-pointer rounded-lg border border-slate-200 p-1"
                        />
                        <span className="text-xs text-slate-500">{value}</span>
                    </div>
                )}
            </div>
            {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
        </div>
    );
}
