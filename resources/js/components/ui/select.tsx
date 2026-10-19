import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from "@headlessui/react";
import { Check, ChevronDown } from "lucide-react";
import {
    Children,
    type ChangeEvent,
    type ComponentProps,
    Fragment,
    isValidElement,
    type ReactElement,
    type ReactNode,
    type RefObject,
    type SelectHTMLAttributes,
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from "react";

import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

type SelectOption = {
    label: string;
    value: string;
    disabled?: boolean;
};

type ResolvedOption = {
    label: ReactNode;
    value: string;
    disabled?: boolean;
};

type SelectProps = Omit<SelectHTMLAttributes<HTMLSelectElement>, "value" | "defaultValue"> & {
    value?: string | number | null;
    defaultValue?: string | number;
    placeholder?: string;
    label?: string;
    hint?: string;
    error?: string;
    options?: SelectOption[];
    required?: boolean;
    containerClassName?: string;
    labelClassName?: string;
    selectClassName?: string;
    /** Called with the new value directly, as an alternative to `onChange`. */
    onValueChange?: (value: string) => void;
    children?: ReactNode;
};

type OptionElementProps = { value?: string | number; disabled?: boolean; children?: ReactNode };

/** Read `<option>` children (including mapped arrays, fragments and optgroups) into a flat list. */
function collectOptions(children: ReactNode): ResolvedOption[] {
    const collected: ResolvedOption[] = [];

    Children.forEach(children, (child) => {
        if (!isValidElement(child)) {
            return;
        }

        const element = child as ReactElement<OptionElementProps>;

        if (element.type === Fragment || element.type === "optgroup") {
            collected.push(...collectOptions(element.props.children));

            return;
        }

        if (element.type === "option") {
            const { value, disabled, children: optionLabel } = element.props;

            collected.push({
                value: String(value ?? (typeof optionLabel === "string" ? optionLabel : "")),
                label: optionLabel,
                disabled,
            });
        }
    });

    return collected;
}

/** How far (px) the button may move while open before the list closes. */
const SCROLL_CLOSE_THRESHOLD_PX = 4;

/**
 * Close the open list once any scroll (page, admin content pane, modal…)
 * moves its button — like a native select. Otherwise the floating list
 * would stay pinned while its button slides under headers or off-screen.
 * Scrolling inside the list itself doesn't move the button, so it's unaffected.
 */
function CloseOnScroll({
    buttonRef,
    onClose,
}: {
    buttonRef: RefObject<HTMLButtonElement | null>;
    onClose: () => void;
}): null {
    useEffect(() => {
        const button = buttonRef.current;

        if (!button) {
            return;
        }

        const origin = button.getBoundingClientRect();

        const handleScroll = (): void => {
            const current = button.getBoundingClientRect();
            const hasMoved =
                Math.abs(current.top - origin.top) > SCROLL_CLOSE_THRESHOLD_PX ||
                Math.abs(current.left - origin.left) > SCROLL_CLOSE_THRESHOLD_PX;

            if (hasMoved) {
                onClose();
            }
        };

        // Capture phase catches scrolls of every scroll container, not just the window.
        window.addEventListener("scroll", handleScroll, { capture: true, passive: true });

        return () => window.removeEventListener("scroll", handleScroll, { capture: true });
    }, [buttonRef, onClose]);

    return null;
}

/**
 * Themed dropdown with the same API as a native `<select>`: pass `options`
 * or `<option>` children, and read `event.target.value` in `onChange`.
 *
 * Built on Headless UI's Listbox with `modal={false}`, so opening it never
 * locks page scrolling or hides the scrollbar (no layout shift).
 */
function Select({
    className,
    id,
    label,
    hint,
    error,
    options = [],
    required = false,
    containerClassName,
    labelClassName,
    selectClassName,
    children,
    value,
    defaultValue,
    placeholder,
    onChange,
    onValueChange,
    name,
    disabled,
    ...props
}: SelectProps) {
    const generatedId = useId();
    const selectId = id ?? generatedId;
    const hintId = hint ? `${selectId}-hint` : undefined;
    const errorId = error ? `${selectId}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

    const resolvedOptions = useMemo<ResolvedOption[]>(
        () => [...collectOptions(children), ...options],
        [children, options],
    );

    // `<option value="" disabled>Choose…</option>` is a prompt, not a choice — show it as the placeholder.
    const promptOption = resolvedOptions.find((option) => option.value === "" && option.disabled);
    const itemOptions = promptOption
        ? resolvedOptions.filter((option) => option !== promptOption)
        : resolvedOptions;

    const isControlled = value !== undefined;
    // A native select with no value shows its first option — mirror that for uncontrolled use.
    const [uncontrolledValue, setUncontrolledValue] = useState<string>(() =>
        defaultValue !== undefined ? String(defaultValue) : promptOption ? "" : (itemOptions[0]?.value ?? ""),
    );
    const currentValue = isControlled ? (value === null ? "" : String(value)) : uncontrolledValue;
    const selectedOption = itemOptions.find((option) => option.value === currentValue);
    const placeholderLabel: ReactNode = placeholder ?? promptOption?.label ?? itemOptions[0]?.label;

    // Inside a (Radix) modal dialog the page outside it is inert, so the list must
    // render in place there; everywhere else it's portaled so cards can't clip it.
    const [isInsideDialog, setIsInsideDialog] = useState(false);
    const buttonRef = useRef<HTMLButtonElement | null>(null);
    const attachButton = useCallback((element: HTMLButtonElement | null): void => {
        buttonRef.current = element;

        if (element) {
            setIsInsideDialog(Boolean(element.closest('[role="dialog"]')));
        }
    }, []);
    // Clicking the button while open is how Headless UI's Listbox toggles itself closed.
    const closeList = useCallback((): void => buttonRef.current?.click(), []);

    const handleChange = (nextValue: string): void => {
        if (!isControlled) {
            setUncontrolledValue(nextValue);
        }

        onValueChange?.(nextValue);

        if (onChange) {
            const target = { value: nextValue, name: name ?? "" };
            onChange({ target, currentTarget: target } as unknown as ChangeEvent<HTMLSelectElement>);
        }
    };

    return (
        <div className={cn("space-y-1.5", containerClassName)}>
            {label ? (
                <Label
                    htmlFor={selectId}
                    className={cn("text-sm font-medium text-text-primary", labelClassName)}
                >
                    {label}
                    {required ? <span className="ml-1 text-destructive">*</span> : null}
                </Label>
            ) : null}
            <Listbox value={currentValue} onChange={handleChange} name={name} disabled={disabled}>
                {({ open }) => (
                <>
                {open ? <CloseOnScroll buttonRef={buttonRef} onClose={closeList} /> : null}
                <ListboxButton
                    ref={attachButton}
                    id={selectId}
                    aria-invalid={Boolean(error) || undefined}
                    aria-describedby={describedBy}
                    aria-required={required || undefined}
                    className={cn(
                        "group flex h-11 w-full items-center justify-between gap-2 rounded-lg border border-border bg-surface px-4 text-left text-sm text-text-primary shadow-sm transition-all outline-none hover:border-border-strong focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60 data-open:border-primary data-open:ring-2 data-open:ring-primary/15",
                        error ? "border-destructive focus-visible:ring-destructive" : "",
                        className,
                        selectClassName,
                    )}
                    {...(props as ComponentProps<typeof ListboxButton>)}
                >
                    <span className={cn("min-w-0 flex-1 truncate", !selectedOption && "text-text-muted")}>
                        {selectedOption ? selectedOption.label : placeholderLabel}
                    </span>
                    <ChevronDown className="size-4 shrink-0 text-text-muted transition-transform duration-200 group-data-open:rotate-180 group-data-open:text-primary" />
                </ListboxButton>

                <ListboxOptions
                    anchor={{ to: "bottom start", gap: 6, padding: 12 }}
                    portal={!isInsideDialog}
                    modal={false}
                    transition
                    // z-20: above page content but below every sticky header (landing z-30,
                    // admin z-40, storefront/checkout z-50), so lists never cover a navbar.
                    className="z-20 max-h-[min(var(--anchor-max-height),20rem)] min-w-[max(var(--button-width),10rem)] overflow-y-auto rounded-xl border border-border bg-surface p-1 text-text-primary shadow-[var(--shadow-dropdown)] outline-none transition duration-150 ease-out [--anchor-max-height:20rem] data-closed:-translate-y-1 data-closed:scale-[0.98] data-closed:opacity-0"
                >
                    {itemOptions.map((option) => (
                        <ListboxOption
                            key={option.value}
                            value={option.value}
                            disabled={option.disabled}
                            className="group relative flex cursor-pointer items-center gap-2 rounded-lg py-2 pr-8 pl-3 text-sm text-text-secondary select-none data-disabled:pointer-events-none data-disabled:opacity-50 data-focus:bg-primary-light data-focus:text-primary data-selected:font-semibold data-selected:text-text-primary data-selected:data-focus:text-primary"
                        >
                            <span className="min-w-0 flex-1 truncate">{option.label}</span>
                            <Check className="invisible absolute right-2.5 size-4 text-primary group-data-selected:visible" />
                        </ListboxOption>
                    ))}
                </ListboxOptions>
                </>
                )}
            </Listbox>
            {hint ? (
                <p id={hintId} className="text-xs text-text-secondary">
                    {hint}
                </p>
            ) : null}
            {error ? (
                <p id={errorId} className="text-xs text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

export { Select };
