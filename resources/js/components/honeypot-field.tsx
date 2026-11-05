/**
 * BIZ-011: an order-form field real customers never see or fill in. Simple
 * bots fill every input, and the server refuses any order where this field
 * isn't empty. Moved off-screen rather than display:none, which some bots
 * skip, and hidden from screen readers, keyboard focus and browser autofill.
 */
export const HONEYPOT_FIELD = 'sd_confirm_ref';

export default function HoneypotField({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div
            aria-hidden="true"
            style={{
                position: 'absolute',
                left: '-10000px',
                width: '1px',
                height: '1px',
                overflow: 'hidden',
            }}
        >
            <label htmlFor={HONEYPOT_FIELD}>Leave this field empty</label>
            <input
                id={HONEYPOT_FIELD}
                name={HONEYPOT_FIELD}
                type="text"
                tabIndex={-1}
                autoComplete="off"
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
        </div>
    );
}
