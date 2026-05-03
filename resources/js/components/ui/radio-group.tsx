import { createContext, useContext, useId, type InputHTMLAttributes, type ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type RadioGroupContextValue = {
    name: string;
    value?: string;
    onValueChange?: (value: string) => void;
};

const RadioGroupContext = createContext<RadioGroupContextValue>({ name: '' });

type RadioGroupProps = {
    name?: string;
    value?: string;
    onValueChange?: (value: string) => void;
    children?: ReactNode;
    className?: string;
    label?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    containerClassName?: string;
    labelClassName?: string;
    optionClassName?: string;
    options?: RadioOption[];
};

type RadioOption = {
    label: string;
    value: string;
    description?: string;
    disabled?: boolean;
};

function RadioGroup({
    name,
    value,
    onValueChange,
    children,
    className,
    label,
    hint,
    error,
    required,
    containerClassName,
    labelClassName,
    options,
    optionClassName,
}: RadioGroupProps): React.JSX.Element {
    const generatedName = useId();
    const groupName = name ?? generatedName;

    return (
        <RadioGroupContext.Provider value={{ name: groupName, value, onValueChange }}>
            <div className={cn('space-y-2', containerClassName)}>
                {label ? (
                    <Label className={cn('text-sm font-medium text-[var(--color-text-primary)]', labelClassName)}>
                        {label}
                        {required ? <span className="ml-1 text-destructive">*</span> : null}
                    </Label>
                ) : null}
                <div role="radiogroup" className={cn('space-y-3', className)}>
                    {children}
                    {options?.map((option) => (
                        <OptionItem key={option.value} option={option} groupName={groupName} value={value} onValueChange={onValueChange} optionClassName={optionClassName} />
                    ))}
                </div>
                {hint ? <p className="text-xs text-[var(--color-text-secondary)]">{hint}</p> : null}
                {error ? <p className="text-xs text-destructive">{error}</p> : null}
            </div>
        </RadioGroupContext.Provider>
    );
}

function OptionItem({
    option,
    groupName,
    value,
    onValueChange,
    optionClassName,
}: {
    option: RadioOption;
    groupName: string;
    value?: string;
    onValueChange?: (value: string) => void;
    optionClassName?: string;
}): React.JSX.Element {
    const optionId = `${groupName}-${option.value}`;
    const isSelected = value === option.value;

    return (
        <label
            htmlFor={optionId}
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 transition-all hover:border-[var(--color-primary)]/40 hover:bg-[var(--color-primary-light)]',
                isSelected ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)]' : '',
                option.disabled ? 'cursor-not-allowed opacity-60' : '',
                optionClassName,
            )}
        >
            <input
                id={optionId}
                type="radio"
                name={groupName}
                value={option.value}
                checked={isSelected}
                onChange={() => onValueChange?.(option.value)}
                disabled={option.disabled}
                className="mt-1 size-4 accent-[var(--color-primary)]"
            />
            <span className="space-y-0.5">
                <span className="block text-sm font-medium text-[var(--color-text-primary)]">{option.label}</span>
                {option.description ? (
                    <span className="block text-xs text-[var(--color-text-secondary)]">{option.description}</span>
                ) : null}
            </span>
        </label>
    );
}

type RadioGroupItemProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'name' | 'checked' | 'onChange'> & {
    value: string;
};

function RadioGroupItem({ value, className, id, ...props }: RadioGroupItemProps): React.JSX.Element {
    const { name, value: groupValue, onValueChange } = useContext(RadioGroupContext);
    const isChecked = groupValue === value;

    return (
        <input
            {...props}
            id={id}
            type="radio"
            name={name}
            value={value}
            checked={isChecked}
            onChange={() => onValueChange?.(value)}
            data-state={isChecked ? 'checked' : 'unchecked'}
            className={cn('size-4 accent-[var(--color-primary)]', className)}
        />
    );
}

export { RadioGroup, RadioGroupItem };
