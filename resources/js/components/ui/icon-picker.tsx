import { Check } from 'lucide-react';
import React from 'react';
import { ComboboxField } from '@/components/ui/combobox';
import { CATEGORY_ICON_OPTIONS, CategoryIcon as CategoryIconPreview } from '@/lib/category-icons';
import { cn } from '@/lib/utils';

interface IconPickerProps {
    value: string | null;
    onChange: (icon: string) => void;
    error?: string;
    className?: string;
}

export function IconPicker({ value, onChange, error, className }: IconPickerProps): React.JSX.Element {
    return (
        <div className={cn('w-full', className)}>
            <div className="flex items-center gap-3">
                <span
                    className={cn(
                        'flex size-11 shrink-0 items-center justify-center rounded-lg border bg-slate-50 text-slate-600',
                        error ? 'border-red-400' : 'border-slate-200',
                    )}
                >
                    <CategoryIconPreview icon={value} className="size-5" />
                </span>
                <ComboboxField
                    value={value ?? 'shopping-bag'}
                    onChange={onChange}
                    options={CATEGORY_ICON_OPTIONS}
                    error={error}
                    placeholder="Search icons… (e.g. shirt, home, tag)"
                    emptyMessage="No icons match that search."
                    maxResults={60}
                    optionClassName="group relative flex cursor-pointer items-center gap-2.5 rounded-lg py-2 pr-8 pl-3 text-sm text-slate-700 select-none data-focus:bg-red-50 data-focus:text-brand data-selected:font-semibold"
                    renderOption={(option, { selected }) => (
                        <>
                            <CategoryIconPreview
                                icon={option.value}
                                className="size-4 shrink-0 text-slate-400 group-data-focus:text-brand"
                            />
                            <span className="min-w-0 flex-1 truncate">{option.label}</span>
                            {selected && (
                                <Check className="absolute right-2.5 size-4 shrink-0 text-brand" />
                            )}
                        </>
                    )}
                />
            </div>
            {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
        </div>
    );
}
