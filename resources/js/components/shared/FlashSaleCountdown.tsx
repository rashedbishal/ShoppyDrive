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
            <div className={`text-[var(--color-error)] font-bold font-mono ${className}`}>
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
                        <span className="bg-[var(--color-secondary)] text-white px-2 py-1 rounded text-lg font-bold min-w-[36px] text-center">
                            {formatNumber(timeLeft.days)}
                        </span>
                        <span className="text-[10px] uppercase font-sans font-bold mt-1 text-[var(--color-text-muted)]">Days</span>
                    </div>
                    <span className="text-xl font-bold text-[var(--color-secondary)] self-start mt-1">:</span>
                </>
            )}
            
            <div className="flex flex-col items-center">
                <span className="bg-[var(--color-secondary)] text-white px-2 py-1 rounded text-lg font-bold min-w-[36px] text-center">
                    {formatNumber(timeLeft.hours)}
                </span>
                <span className="text-[10px] uppercase font-sans font-bold mt-1 text-[var(--color-text-muted)]">Hrs</span>
            </div>
            
            <span className="text-xl font-bold text-[var(--color-secondary)] self-start mt-1">:</span>
            
            <div className="flex flex-col items-center">
                <span className="bg-[var(--color-secondary)] text-white px-2 py-1 rounded text-lg font-bold min-w-[36px] text-center">
                    {formatNumber(timeLeft.minutes)}
                </span>
                <span className="text-[10px] uppercase font-sans font-bold mt-1 text-[var(--color-text-muted)]">Min</span>
            </div>
            
            <span className="text-xl font-bold text-[var(--color-secondary)] self-start mt-1">:</span>
            
            <div className="flex flex-col items-center">
                <span className="bg-[var(--color-primary)] text-white px-2 py-1 rounded text-lg font-bold min-w-[36px] text-center animate-pulse">
                    {formatNumber(timeLeft.seconds)}
                </span>
                <span className="text-[10px] uppercase font-sans font-bold mt-1 text-[var(--color-text-muted)]">Sec</span>
            </div>
        </div>
    );
}
