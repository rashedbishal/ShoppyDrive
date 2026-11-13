import React from 'react';
import { useNow } from '@/hooks/use-now';

interface FlashSaleCountdownProps {
    ends_at: string;
    className?: string;
}

interface TimeLeft {
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
}

function calculateTimeLeft(ends_at: string, nowMs: number): TimeLeft | null {
    const difference = +new Date(ends_at) - nowMs;

    if (difference <= 0) {
        return null;
    }

    return {
        days: Math.floor(difference / (1000 * 60 * 60 * 24)),
        hours: Math.floor((difference / (1000 * 60 * 60)) % 24),
        minutes: Math.floor((difference / 1000 / 60) % 60),
        seconds: Math.floor((difference / 1000) % 60),
    };
}

export function FlashSaleCountdown({
    ends_at,
    className,
}: FlashSaleCountdownProps): React.JSX.Element {
    const nowMs = useNow();
    // Zeros, not "SALE ENDED", for the moment before the browser's clock is
    // read, so a page load never flashes the sale as over.
    const timeLeft: TimeLeft | null =
        nowMs === null
            ? { days: 0, hours: 0, minutes: 0, seconds: 0 }
            : calculateTimeLeft(ends_at, nowMs);

    if (!timeLeft) {
        return (
            <div
                className={`font-mono font-bold text-[var(--color-error)] ${className}`}
            >
                SALE ENDED
            </div>
        );
    }

    const formatNumber = (num: number) => num.toString().padStart(2, '0');

    return (
        <div className={`flex items-center gap-2 font-mono ${className}`}>
            {timeLeft.days > 0 && (
                <>
                    <div className="flex flex-col items-center">
                        <span className="min-w-[36px] rounded bg-[var(--color-secondary)] px-2 py-1 text-center text-lg font-bold text-white">
                            {formatNumber(timeLeft.days)}
                        </span>
                        <span className="mt-1 font-sans text-[10px] font-bold text-[var(--color-text-secondary)] uppercase">
                            Days
                        </span>
                    </div>
                    <span className="mt-1 self-start text-xl font-bold text-[var(--color-secondary)]">
                        :
                    </span>
                </>
            )}

            <div className="flex flex-col items-center">
                <span className="min-w-[36px] rounded bg-[var(--color-secondary)] px-2 py-1 text-center text-lg font-bold text-white">
                    {formatNumber(timeLeft.hours)}
                </span>
                <span className="mt-1 font-sans text-[10px] font-bold text-[var(--color-text-secondary)] uppercase">
                    Hrs
                </span>
            </div>

            <span className="mt-1 self-start text-xl font-bold text-[var(--color-secondary)]">
                :
            </span>

            <div className="flex flex-col items-center">
                <span className="min-w-[36px] rounded bg-[var(--color-secondary)] px-2 py-1 text-center text-lg font-bold text-white">
                    {formatNumber(timeLeft.minutes)}
                </span>
                <span className="mt-1 font-sans text-[10px] font-bold text-[var(--color-text-secondary)] uppercase">
                    Min
                </span>
            </div>

            <span className="mt-1 self-start text-xl font-bold text-[var(--color-secondary)]">
                :
            </span>

            <div className="flex flex-col items-center">
                <span className="min-w-[36px] animate-pulse rounded bg-[var(--color-primary)] px-2 py-1 text-center text-lg font-bold text-white">
                    {formatNumber(timeLeft.seconds)}
                </span>
                <span className="mt-1 font-sans text-[10px] font-bold text-[var(--color-text-secondary)] uppercase">
                    Sec
                </span>
            </div>
        </div>
    );
}
