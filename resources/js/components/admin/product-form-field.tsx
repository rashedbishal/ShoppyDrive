export function productInputClass(err?: string): string {
    return `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;
}

export function ProductFormField({
    label,
    error,
    warning,
    children,
}: {
    label: string;
    error?: string;
    /** Advisory only (amber); hidden while the field has a real error. */
    warning?: string | null;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
                {label}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
            {!error && warning && (
                <p className="mt-1 text-xs text-amber-600">{warning}</p>
            )}
        </div>
    );
}
