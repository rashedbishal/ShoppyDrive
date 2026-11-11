/**
 * Browser-side check that a "confirm password" field matches the password, so
 * the customer sees the problem as they type instead of after the form has
 * been sent. The server still validates the same rule (it always has the final
 * say); this only saves the round trip.
 */
export const PASSWORDS_DO_NOT_MATCH = 'The passwords do not match.';

/** The message to show now, or undefined while the two agree or the confirmation is still empty. */
export function passwordMismatchMessage(
    password: string,
    confirmation: string,
): string | undefined {
    return confirmation !== '' && password !== confirmation
        ? PASSWORDS_DO_NOT_MATCH
        : undefined;
}

/** True when the form may be sent (an empty confirmation is left to the field's own `required`). */
export function passwordsMatch(
    password: string,
    confirmation: string,
): boolean {
    return password === confirmation;
}
