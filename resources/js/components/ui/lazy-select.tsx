import { ChevronDown } from 'lucide-react';
import {
    Children,
    Fragment,
    isValidElement,
    lazy,
    Suspense,
    useEffect,
    useId,
    useRef,
} from 'react';
import type {
    ComponentProps,
    KeyboardEvent as ReactKeyboardEvent,
    MutableRefObject,
    ReactElement,
    ReactNode,
} from 'react';
import type { Select as SelectComponent } from '@/components/ui/select';
import { cn } from '@/lib/utils';

/**
 * F1-05: the storefront's sort dropdowns (category and product pages) without
 * Headless UI, react-aria and floating-ui (~70 KB) in the page's first
 * download. The real <Select> loads as its own chunk, requested as soon as
 * this module runs; until it arrives, an inert copy of its closed state is
 * shown (same classes, same label), so nothing moves when it swaps in. A
 * click or key press on that copy isn't lost: once the real one arrives it
 * takes the focus and opens (see ResumeIntent). Checkout, landing pages and
 * admin keep importing <Select> directly.
 */
type SelectProps = ComponentProps<typeof SelectComponent>;

const selectModule = import('@/components/ui/select');
// If the chunk can't be fetched, keep showing the inert placeholder rather
// than throwing the page into its error screen.
const RealSelect = lazy(() =>
    selectModule
        .then((module) => ({ default: module.Select }))
        .catch(() => ({ default: SelectPlaceholder })),
);

type OptionElementProps = {
    value?: string | number;
    disabled?: boolean;
    children?: ReactNode;
};

/** The same option list <Select> builds from `options` and child <option>s. */
function optionsOf(
    props: SelectProps,
): { value: string; label: ReactNode; disabled?: boolean }[] {
    const collected: { value: string; label: ReactNode; disabled?: boolean }[] =
        [];

    const walk = (children: ReactNode): void => {
        Children.forEach(children, (child) => {
            if (!isValidElement(child)) {
                return;
            }

            const element = child as ReactElement<OptionElementProps>;

            if (element.type === Fragment || element.type === 'optgroup') {
                walk(element.props.children);
            } else if (element.type === 'option') {
                const { value, disabled, children: label } = element.props;
                collected.push({
                    value: String(
                        value ?? (typeof label === 'string' ? label : ''),
                    ),
                    label,
                    disabled,
                });
            }
        });
    };

    walk(props.children);

    return [...collected, ...(props.options ?? [])];
}

/** What the visitor did with the placeholder before the real select arrived. */
type Intent = 'none' | 'focus' | 'open';

type PlaceholderProps = SelectProps & {
    onIntent?: (intent: Intent | 'blur') => void;
};

/** Keys that open the Headless UI select from its button (Enter doesn't: see ResumeIntent). */
const OPEN_KEYS = new Set([' ', 'ArrowDown', 'ArrowUp']);

/** The closed <Select> as it first renders: label, button text, chevron. */
function SelectPlaceholder({
    onIntent,
    ...props
}: PlaceholderProps): React.JSX.Element {
    const all = optionsOf(props);
    const prompt = all.find((option) => option.value === '' && option.disabled);
    const items = prompt ? all.filter((option) => option !== prompt) : all;
    const current =
        props.value !== undefined
            ? props.value === null
                ? ''
                : String(props.value)
            : props.defaultValue !== undefined
              ? String(props.defaultValue)
              : prompt
                ? ''
                : (items[0]?.value ?? '');
    const selected = items.find((option) => option.value === current);

    return (
        <div className={cn('space-y-1.5', props.containerClassName)}>
            {props.label ? (
                <span
                    className={cn(
                        'block text-sm font-medium text-text-primary',
                        props.labelClassName,
                    )}
                >
                    {props.label}
                </span>
            ) : null}
            <button
                type="button"
                id={props.id}
                aria-label={props['aria-label']}
                aria-busy="true"
                aria-haspopup="listbox"
                aria-expanded="false"
                disabled={props.disabled}
                onClick={() => onIntent?.('open')}
                onKeyDown={(event: ReactKeyboardEvent<HTMLButtonElement>) => {
                    if (OPEN_KEYS.has(event.key)) {
                        event.preventDefault();
                        onIntent?.('open');
                    } else if (event.key === 'Enter') {
                        // A button turns Enter into a click; the real select
                        // doesn't open on Enter, so neither does this copy.
                        event.preventDefault();
                    }
                }}
                onFocus={() => onIntent?.('focus')}
                onBlur={() => onIntent?.('blur')}
                className={cn(
                    'group flex h-11 w-full items-center justify-between gap-2 rounded-lg border border-border bg-surface px-4 text-left text-sm text-text-primary shadow-sm transition-all outline-none hover:border-border-strong focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60',
                    props.error
                        ? 'border-destructive focus-visible:ring-destructive'
                        : '',
                    props.className,
                    props.selectClassName,
                )}
            >
                <span
                    className={cn(
                        'min-w-0 flex-1 truncate',
                        !selected && 'text-text-secondary',
                    )}
                >
                    {selected
                        ? selected.label
                        : (props.placeholder ??
                          prompt?.label ??
                          items[0]?.label)}
                </span>
                <ChevronDown className="size-4 shrink-0 text-text-muted" />
            </button>
        </div>
    );
}

/**
 * Runs once the real select has mounted (it renders after it, inside the same
 * Suspense boundary): hands it the focus the placeholder had and, if the
 * visitor had already clicked or pressed an opening key, opens it the way a
 * keyboard user would: Space on the focused button. (Not Enter: Headless UI
 * treats Enter on a listbox button as "submit the surrounding form", like a
 * native select.)
 */
function ResumeIntent({
    id,
    intent,
}: {
    id: string;
    intent: MutableRefObject<Intent>;
}): null {
    useEffect(() => {
        const pending = intent.current;
        intent.current = 'none';

        if (pending === 'none') {
            return;
        }

        const button = document.getElementById(id);

        if (!(button instanceof HTMLElement)) {
            return;
        }

        button.focus({ preventScroll: true });

        if (pending === 'open') {
            button.dispatchEvent(
                new KeyboardEvent('keydown', {
                    key: ' ',
                    code: 'Space',
                    bubbles: true,
                    cancelable: true,
                }),
            );
        }
    }, [id, intent]);

    return null;
}

export function LazySelect(props: SelectProps): React.JSX.Element {
    const generatedId = useId();
    const id = props.id ?? generatedId;
    const intent = useRef<Intent>('none');

    const handleIntent = (next: Intent | 'blur'): void => {
        if (next === 'blur') {
            if (intent.current === 'focus') {
                intent.current = 'none';
            }

            return;
        }

        // An "open" already asked for isn't downgraded by a later focus.
        if (intent.current !== 'open') {
            intent.current = next;
        }
    };

    return (
        <Suspense
            fallback={
                <SelectPlaceholder
                    {...props}
                    id={id}
                    onIntent={handleIntent}
                />
            }
        >
            <RealSelect {...props} id={id} />
            <ResumeIntent id={id} intent={intent} />
        </Suspense>
    );
}
