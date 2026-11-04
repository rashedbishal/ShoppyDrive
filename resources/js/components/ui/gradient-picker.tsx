import { Label } from '@/components/ui/label';
import {
    buildGradient,
    DEFAULT_BANNER_GRADIENT,
    parseGradient,
    type GradientStop,
} from '@/lib/gradient';

type GradientPickerProps = {
    label?: string;
    value: string;
    onChange: (value: string) => void;
    hint?: string;
    error?: string;
};

export function GradientPicker({
    label,
    value,
    onChange,
    hint,
    error,
}: GradientPickerProps) {
    const parsed =
        parseGradient(value) ?? parseGradient(DEFAULT_BANNER_GRADIENT)!;

    const updateStop = (stop: 'from' | 'to', patch: Partial<GradientStop>) => {
        const from = stop === 'from' ? { ...parsed.from, ...patch } : parsed.from;
        const to = stop === 'to' ? { ...parsed.to, ...patch } : parsed.to;
        onChange(buildGradient(from, to));
    };

    return (
        <div className="space-y-2">
            {label && (
                <Label className="text-sm font-medium text-text-primary">
                    {label}
                </Label>
            )}
            <div
                className="h-12 w-full rounded-lg border border-border"
                style={{ backgroundImage: value || DEFAULT_BANNER_GRADIENT }}
            />
            {/* Stacked rather than a 2-column grid: this widget is often placed in a narrow
                320px form sidebar, and a viewport-based sm:grid-cols-2 split doesn't know
                that — it would squeeze the swatch + slider + percentage into a column far
                narrower than the breakpoint assumes. Full width at any container size avoids that. */}
            <div className="space-y-4">
                {(['from', 'to'] as const).map((stop) => (
                    <div key={stop} className="space-y-1.5">
                        <p className="text-xs font-medium text-text-secondary">
                            {stop === 'from' ? 'Start color' : 'End color'}
                        </p>
                        <div className="flex items-center gap-2">
                            <input
                                type="color"
                                value={parsed[stop].color}
                                onChange={(e) =>
                                    updateStop(stop, { color: e.target.value })
                                }
                                aria-label={`${stop === 'from' ? 'Start' : 'End'} color`}
                                className="h-9 w-9 shrink-0 cursor-pointer rounded-md border border-border bg-transparent p-0.5"
                            />
                            <input
                                type="range"
                                min={0}
                                max={100}
                                value={parsed[stop].opacity}
                                onChange={(e) =>
                                    updateStop(stop, {
                                        opacity: Number(e.target.value),
                                    })
                                }
                                aria-label={`${stop === 'from' ? 'Start' : 'End'} opacity`}
                                className="h-1.5 min-w-0 flex-1 cursor-pointer accent-primary"
                            />
                            <span className="w-9 shrink-0 text-right text-xs text-text-secondary">
                                {parsed[stop].opacity}%
                            </span>
                        </div>
                    </div>
                ))}
            </div>
            {hint ? (
                <p className="text-xs text-text-secondary">{hint}</p>
            ) : null}
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
