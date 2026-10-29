import React, { useEffect, useState } from 'react';

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

export function FlashSaleCountdown({
    ends_at,
    className,
}: FlashSaleCountdownProps): React.JSX.Element {
    const [timeLeft, setTimeLeft] = useState<TimeLeft | null>(null);

    useEffect(() => {
        const calculateTimeLeft = () => {
            const difference = +new Date(ends_at) - +new Date();

            if (difference > 0) {
                setTimeLeft({
                    days: Math.floor(difference / (1000 * 60 * 60 * 24)),
                    hours: Math.floor((difference / (1000 * 60 * 60)) % 24),
                    minutes: Math.floor((difference / 1000 / 60) % 60),
                    seconds: Math.floor((difference / 1000) % 60),
                });
            } else {
                setTimeLeft(null);
            }
        };

        calculateTimeLeft();
        const timer = setInterval(calculateTimeLeft, 1000);

        return () => clearInterval(timer);
    }, [ends_at]);

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
                        <span className="mt-1 font-sans text-[10px] font-bold text-[var(--color-text-muted)] uppercase">
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
                <span className="mt-1 font-sans text-[10px] font-bold text-[var(--color-text-muted)] uppercase">
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
                <span className="mt-1 font-sans text-[10px] font-bold text-[var(--color-text-muted)] uppercase">
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
                <span className="mt-1 font-sans text-[10px] font-bold text-[var(--color-text-muted)] uppercase">
                    Sec
                </span>
            </div>
        </div>
    );
}
