import { Listbox, ListboxButton, ListboxOption, ListboxOptions, Portal } from "@headlessui/react";
import { Check, ChevronDown } from "lucide-react";
import {
    Children,
    type ChangeEvent,
    type ComponentProps,
    Fragment,
    isValidElement,
    type KeyboardEvent as ReactKeyboardEvent,
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
import { useDialogPortalTarget } from "@/components/ui/dialog";
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

/** Marks the floating options panel so `CloseOnScroll` can tell its own internal scrolling apart from the page/modal scrolling underneath it. */
export const OPTIONS_PANEL_ATTR = "data-select-options-panel";

/**
 * Close the open list once any scroll (page, admin content pane, modal…)
 * moves its button — like a native select. Otherwise the floating list
 * would stay pinned while its button slides under headers or off-screen.
 *
 * Scrolling inside the list itself is explicitly excluded via
 * OPTIONS_PANEL_ATTR rather than just relying on "the button didn't move":
 * for a long list (hundreds-to-thousands of options, e.g. an icon picker),
 * wheel-scrolling through it or typing to jump the active option into view
 * could still nudge the page enough to cross the move threshold below,
 * closing the list out from under a still-in-progress selection.
 */
export function CloseOnScroll({
    buttonRef,
    onClose,
}: {
    buttonRef: RefObject<HTMLElement | null>;
    onClose: () => void;
}): null {
    useEffect(() => {
        const button = buttonRef.current;

        if (!button) {
            return;
        }

        const origin = button.getBoundingClientRect();

        const handleScroll = (event: Event): void => {
            const target = event.target;

            if (target instanceof Element && target.closest(`[${OPTIONS_PANEL_ATTR}]`)) {
                return;
            }

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
 * While the list is open inside a (Radix) dialog, make Escape close only the list,
 * not the whole dialog. Radix dismisses the dialog from a capture-phase listener on
 * `document`, which sees the key before Headless UI does — so this window capture
 * listener runs first, stops the event, and closes the list itself. (Merely
 * preventing default isn't enough: Headless UI skips default-prevented events.)
 */
export function CloseOnEscapeInsideDialog({ onClose }: { onClose: () => void }): null {
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent): void => {
            if (event.key !== "Escape") {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            onClose();
        };

        window.addEventListener("keydown", handleKeyDown, { capture: true });

        return () => window.removeEventListener("keydown", handleKeyDown, { capture: true });
    }, [onClose]);

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

    // Headless UI always portals an anchored list (its `portal` prop is ignored when
    // `anchor` is set) — to the end of <body> by default, so cards can't clip it.
    // Inside a (Radix) modal dialog that would put it behind the dialog and in the
    // inert page outside it, so there the portal is retargeted to a sibling of the
    // dialog's content (see `useDialogPortalTarget`) instead — same stacking context
    // as the dialog, but not nested inside anything that scrolls or clips.
    const dialogPortalTarget = useDialogPortalTarget();
    const isInsideDialog = dialogPortalTarget !== null;
    const buttonRef = useRef<HTMLButtonElement | null>(null);
    // Headless UI has no API to close a Listbox, and a programmatic button click is
    // ignored after a mouse open (it toggles on pointerdown), so remount it instead.
    // Its value lives in our props/state, so remounting loses nothing but the open list.
    const [listboxKey, setListboxKey] = useState(0);
    const shouldFocusButtonRef = useRef(false);
    const closeList = useCallback((): void => {
        shouldFocusButtonRef.current = true;
        setListboxKey((key) => key + 1);
    }, []);

    // Return focus to the (remounted) button, as Headless UI does when it closes the list.
    useEffect(() => {
        if (shouldFocusButtonRef.current) {
            shouldFocusButtonRef.current = false;
            buttonRef.current?.focus({ preventScroll: true });
        }
    }, [listboxKey]);

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

    // Type-to-jump: build up a search buffer from consecutive keystrokes (reset
    // after a short pause, like a native <select>) and select the first option
    // whose label starts with it. Keeps keyboard search working regardless of
    // the floating list's open/closed state.
    const typeaheadRef = useRef<{ query: string; timeout: ReturnType<typeof setTimeout> | null }>({
        query: "",
        timeout: null,
    });

    const handleTypeahead = useCallback(
        (event: ReactKeyboardEvent<HTMLButtonElement>): void => {
            if (event.key.length !== 1 || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }

            const state = typeaheadRef.current;

            if (state.timeout) {
                clearTimeout(state.timeout);
            }

            state.query += event.key.toLowerCase();
            state.timeout = setTimeout(() => {
                state.query = "";
            }, 600);

            const query = state.query;
            const match = itemOptions.find(
                (option) =>
                    !option.disabled &&
                    typeof option.label === "string" &&
                    option.label.toLowerCase().startsWith(query),
            );

            if (match) {
                handleChange(match.value);
            }
        },
        [itemOptions, handleChange],
    );

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
            <Listbox key={listboxKey} value={currentValue} onChange={handleChange} name={name} disabled={disabled}>
                {({ open }) => {
                const listboxButton = (
                <ListboxButton
                    ref={buttonRef}
                    id={selectId}
                    aria-invalid={Boolean(error) || undefined}
                    aria-describedby={describedBy}
                    aria-required={required || undefined}
                    // Only while closed — mimics a native <select> letting you type to
                    // jump straight to a value without opening the list. Once open,
                    // Headless UI's own typeahead already highlights matches as you
                    // type (Enter/click still confirms); running ours too on every
                    // keystroke fought it for the same keystrokes and committed a
                    // value mid-search, which is what made a long list (e.g. the
                    // ~1500-icon picker) feel like it was closing on you before you
                    // could click anything.
                    onKeyDown={open ? undefined : handleTypeahead}
                    className={cn(
                        "group flex h-11 w-full items-center justify-between gap-2 rounded-lg border border-border bg-surface px-4 text-left text-sm text-text-primary shadow-sm transition-all outline-none hover:border-border-strong focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60 data-open:border-primary data-open:ring-2 data-open:ring-primary/15",
                        error ? "border-destructive focus-visible:ring-destructive" : "",
                        className,
                        selectClassName,
                    )}
                    {...(props as ComponentProps<typeof ListboxButton>)}
                >
                    <span className={cn("min-w-0 flex-1 truncate", !selectedOption && "text-text-secondary")}>
                        {selectedOption ? selectedOption.label : placeholderLabel}
                    </span>
                    <ChevronDown className="size-4 shrink-0 text-text-muted transition-transform duration-200 group-data-open:rotate-180 group-data-open:text-primary" />
                </ListboxButton>
                );

                const listboxOptions = (
                <ListboxOptions
                    {...{ [OPTIONS_PANEL_ATTR]: "" }}
                    anchor={{ to: "bottom start", gap: 6, padding: 12 }}
                    modal={false}
                    transition
                    // z-20: above page content but below every sticky header (landing z-30,
                    // admin z-40, storefront/checkout z-50), so lists never cover a navbar.
                    // pointer-events-auto: re-enables clicks when portaled into a dialog's
                    // pointer-events-none portal target (see dialog.tsx DialogContent).
                    className="pointer-events-auto z-20 max-h-[min(var(--anchor-max-height),20rem)] min-w-[max(var(--button-width),10rem)] overflow-y-auto rounded-xl border border-border bg-popover p-1 text-text-primary shadow-[var(--shadow-dropdown)] backdrop-blur-[var(--ui-blur)] outline-none transition duration-150 ease-out [--anchor-max-height:20rem] data-closed:-translate-y-1 data-closed:scale-[0.98] data-closed:opacity-0"
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
                );

                return (
                <>
                {open ? <CloseOnScroll buttonRef={buttonRef} onClose={closeList} /> : null}
                {open && isInsideDialog ? <CloseOnEscapeInsideDialog onClose={closeList} /> : null}
                {dialogPortalTarget ? (
                    <>
                        {listboxButton}
                        <Portal.Group target={dialogPortalTarget}>{listboxOptions}</Portal.Group>
                    </>
                ) : (
                    <>
                        {listboxButton}
                        {listboxOptions}
                    </>
                )}
                </>
                );
                }}
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
