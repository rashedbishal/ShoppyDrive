import {
    Combobox as HeadlessCombobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    Portal,
} from '@headlessui/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useRef, useState, type ReactNode } from 'react';
import { CloseOnEscapeInsideDialog, CloseOnScroll } from '@/components/ui/select';
import { useDialogPortalTarget } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export type ComboboxItem = { value: string; label: string };

type ComboboxFieldProps<T extends ComboboxItem> = {
    value: string;
    onChange: (value: string) => void;
    options: T[];
    /** Custom content for each row. Defaults to the option's label. */
    renderOption?: (option: T, state: { selected: boolean }) => ReactNode;
    placeholder?: string;
    error?: string;
    disabled?: boolean;
    /** Caps how many matches are rendered at once — keeps huge lists (1000+ items) cheap to mount. */
    maxResults?: number;
    inputClassName?: string;
    /** Wrapper classes for the open panel — override to lay options out as a grid (e.g. color swatches). */
    optionsClassName?: string;
    /** Wrapper classes applied to every `ComboboxOption` — override for compact grid cells. */
    optionClassName?: string;
    emptyMessage?: string;
};

/**
 * A searchable, virtual-free alternative to `Select` for option lists too large to
 * mount in full (hundreds to thousands of items) — e.g. icon names or CSS colors.
 * Typing filters the list client-side and only the first `maxResults` matches are
 * ever rendered, so opening it stays instant no matter how big `options` is.
 */
export function ComboboxField<T extends ComboboxItem>({
    value,
    onChange,
    options,
    renderOption,
    placeholder = 'Search…',
    error,
    disabled,
    maxResults = 60,
    inputClassName,
    optionsClassName,
    optionClassName,
    emptyMessage = 'No matches.',
}: ComboboxFieldProps<T>): React.JSX.Element {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        const matches = q === '' ? options : options.filter((option) => option.label.toLowerCase().includes(q));

        return matches.slice(0, maxResults);
    }, [options, query, maxResults]);

    const inputRef = useRef<HTMLInputElement | null>(null);
    const dialogPortalTarget = useDialogPortalTarget();

    return (
        <HeadlessCombobox
            value={value}
            onChange={(next) => {
                if (next !== null) {
                    onChange(next);
                }
            }}
            onClose={() => setQuery('')}
            disabled={disabled}
            immediate
        >
            {({ open }) => {
                const input = (
                    <div className="relative">
                        <ComboboxInput
                            ref={inputRef}
                            autoComplete="off"
                            displayValue={(itemValue: string) =>
                                options.find((option) => option.value === itemValue)?.label ?? ''
                            }
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={placeholder}
                            aria-invalid={Boolean(error) || undefined}
                            className={cn(
                                'h-11 w-full rounded-lg border bg-white pr-9 pl-3 text-sm focus:outline-none focus:ring-1',
                                error
                                    ? 'border-red-400 focus:ring-red-400'
                                    : 'border-slate-200 focus:ring-[#c8102e]',
                                inputClassName,
                            )}
                        />
                        <ComboboxButton className="absolute inset-y-0 right-0 flex items-center px-2.5">
                            <ChevronDown
                                className={cn(
                                    'size-4 text-slate-400 transition-transform duration-200',
                                    open && 'rotate-180',
                                )}
                            />
                        </ComboboxButton>
                    </div>
                );

                const panel = (
                    <ComboboxOptions
                        anchor={{ to: 'bottom start', gap: 6, padding: 12 }}
                        modal={false}
                        transition
                        className={cn(
                            'z-20 max-h-[min(var(--anchor-max-height),20rem)] min-w-[max(var(--button-width),14rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg outline-none transition duration-150 ease-out [--anchor-max-height:20rem] data-closed:-translate-y-1 data-closed:scale-[0.98] data-closed:opacity-0',
                            optionsClassName,
                        )}
                    >
                        {filtered.length === 0 ? (
                            <p className="col-span-full px-3 py-6 text-center text-sm text-slate-400">
                                {emptyMessage}
                            </p>
                        ) : (
                            filtered.map((option) => (
                                <ComboboxOption
                                    key={option.value}
                                    value={option.value}
                                    className={
                                        optionClassName ??
                                        'group relative flex cursor-pointer items-center gap-2 rounded-lg py-2 pr-3 pl-3 text-sm text-slate-700 select-none data-focus:bg-red-50 data-focus:text-[#c8102e] data-selected:font-semibold'
                                    }
                                >
                                    {renderOption ? (
                                        renderOption(option, { selected: option.value === value })
                                    ) : (
                                        <span className="min-w-0 flex-1 truncate">{option.label}</span>
                                    )}
                                </ComboboxOption>
                            ))
                        )}
                        {query.trim() === '' && options.length > filtered.length && (
                            <p className="col-span-full px-3 pt-1.5 pb-1 text-center text-xs text-slate-400">
                                Showing {maxResults} of {options.length} — type to search
                            </p>
                        )}
                    </ComboboxOptions>
                );

                return (
                    <>
                        {open ? (
                            <CloseOnScroll buttonRef={inputRef} onClose={() => inputRef.current?.blur()} />
                        ) : null}
                        {open && dialogPortalTarget !== null ? (
                            <CloseOnEscapeInsideDialog onClose={() => inputRef.current?.blur()} />
                        ) : null}
                        {input}
                        {dialogPortalTarget ? (
                            <Portal.Group target={dialogPortalTarget}>{panel}</Portal.Group>
                        ) : (
                            panel
                        )}
                    </>
                );
            }}
        </HeadlessCombobox>
    );
}
