type Rule = (
    value: unknown,
    data: Record<string, unknown>,
) => string | undefined;

const isEmpty = (value: unknown): boolean => {
    if (value === null || value === undefined) {
        return true;
    }

    if (typeof value === 'string') {
        return value.trim() === '';
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    return false;
};

export const required =
    (message = 'This field is required.'): Rule =>
    (value) =>
        isEmpty(value) ? message : undefined;

export const requiredIf =
    (
        condition: (data: Record<string, unknown>) => boolean,
        message = 'This field is required.',
    ): Rule =>
    (value, data) =>
        condition(data) && isEmpty(value) ? message : undefined;

export const maxLength =
    (max: number, message?: string): Rule =>
    (value) =>
        typeof value === 'string' && value.length > max
            ? (message ?? `Must not be greater than ${max} characters.`)
            : undefined;

export const minValue =
    (min: number, message?: string): Rule =>
    (value) => {
        if (isEmpty(value)) {
            return undefined;
        }

        const num = Number(value);

        return !Number.isNaN(num) && num < min
            ? (message ?? `Must be at least ${min}.`)
            : undefined;
    };

export const numeric =
    (message = 'Must be a number.'): Rule =>
    (value) =>
        !isEmpty(value) && Number.isNaN(Number(value)) ? message : undefined;

export const email =
    (message = 'Must be a valid email address.'): Rule =>
    (value) =>
        typeof value === 'string' &&
        value !== '' &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
            ? message
            : undefined;

export const url =
    (message = 'Must be a valid URL.'): Rule =>
    (value) => {
        if (typeof value !== 'string' || value === '') {
            return undefined;
        }

        try {
            new URL(value);

            return undefined;
        } catch {
            return message;
        }
    };

export const pattern =
    (regex: RegExp, message = 'Invalid format.'): Rule =>
    (value) =>
        typeof value === 'string' && value !== '' && !regex.test(value)
            ? message
            : undefined;

export const minLength =
    (min: number, message?: string): Rule =>
    (value) =>
        typeof value === 'string' && value !== '' && value.length < min
            ? (message ?? `Must be at least ${min} characters.`)
            : undefined;

export function validate<T extends Record<string, unknown>>(
    data: T,
    rules: Partial<Record<keyof T, Rule[]>>,
): Partial<Record<keyof T, string>> {
    const errors: Partial<Record<keyof T, string>> = {};

    for (const field in rules) {
        const fieldRules = rules[field] ?? [];

        for (const rule of fieldRules) {
            const message = rule(data[field], data);

            if (message) {
                errors[field] = message;
                break;
            }
        }
    }

    return errors;
}
