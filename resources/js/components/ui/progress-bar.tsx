import { cn } from "@/lib/utils";

type ProgressBarProps = {
    value: number;
    max?: number;
    label?: string;
    showValue?: boolean;
    className?: string;
};

function ProgressBar({ value, max = 100, label, showValue = false, className }: ProgressBarProps) {
    const safeMax = max <= 0 ? 100 : max;
    const percentage = Math.min(100, Math.max(0, (value / safeMax) * 100));

    return (
        <div className={cn("space-y-2", className)}>
            {label ? (
                <div className="flex items-center justify-between text-sm font-medium text-text-primary">
                    <span>{label}</span>
                    {showValue ? <span>{Math.round(percentage)}%</span> : null}
                </div>
            ) : null}
            <div
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={safeMax}
                aria-valuenow={Math.round(value)}
                className="h-2 w-full overflow-hidden rounded-full bg-surface-alt"
            >
                <div
                    className="h-full rounded-full bg-primary transition-all duration-300"
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}

export { ProgressBar };
