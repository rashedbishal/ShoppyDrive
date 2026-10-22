import React from 'react';
import { Select } from '@/components/ui/select';
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
                <Select
                    value={value ?? 'shopping-bag'}
                    onValueChange={onChange}
                    containerClassName="min-w-0 flex-1"
                    selectClassName={cn(
                        'h-11 w-full rounded-lg border bg-white px-3 text-sm focus:outline-none focus:ring-1',
                        error ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]',
                    )}
                >
                    {CATEGORY_ICON_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </div>
            {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
        </div>
    );
}
