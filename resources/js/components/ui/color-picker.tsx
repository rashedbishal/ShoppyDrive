import { ChevronDown } from 'lucide-react';
import React from 'react';
import { CATEGORY_COLOR_OPTIONS, isHexColor, resolveCategoryColor } from '@/lib/category-colors';
import { cn } from '@/lib/utils';

const CUSTOM_VALUE = '__custom__';
const AUTO_VALUE = '';
const DEFAULT_CUSTOM_HEX = '#3b82f6';

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

    const handleSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const next = e.target.value;

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
                <div className="relative flex-1">
                    <select
                        value={selectValue}
                        onChange={handleSelect}
                        className={cn(
                            'h-11 w-full appearance-none rounded-lg border bg-white px-3 pr-9 text-sm focus:outline-none focus:ring-1',
                            error ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]',
                        )}
                    >
                        <option value={AUTO_VALUE}>Automatic (rotate colors)</option>
                        {CATEGORY_COLOR_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                        <option value={CUSTOM_VALUE}>Custom color…</option>
                    </select>
                    <ChevronDown className="pointer-events-none absolute inset-y-0 right-3 my-auto size-4 text-slate-400" />
                </div>
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
