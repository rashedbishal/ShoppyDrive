import { BANGLA_FONT_FAMILY } from '@/lib/landing-i18n';
import type { LandingText } from '@/lib/landing-i18n';
import { cn } from '@/lib/utils';

/**
 * DISC-65: the EN | বাং pill on a landing page that offers Bangla. `tone`
 * matches the header it sits in (the landing page's dark header, or the
 * payment/confirmation pages' light one).
 */
export default function LanguageSwitch({
    text,
    tone = 'dark',
    className,
}: {
    text: LandingText;
    tone?: 'dark' | 'light';
    className?: string;
}) {
    if (!text.banglaEnabled) {
        return null;
    }

    const options = [
        { value: 'en', label: 'EN', lang: 'en' },
        { value: 'bn', label: 'বাং', lang: 'bn' },
    ] as const;

    return (
        <div
            role="group"
            aria-label={text.t('language')}
            className={cn(
                'inline-flex shrink-0 items-center rounded-full p-0.5 text-xs font-bold',
                tone === 'dark'
                    ? 'bg-white/10 ring-1 ring-white/20'
                    : 'bg-slate-100 ring-1 ring-slate-200',
                className,
            )}
        >
            {options.map((option) => {
                const active = text.lang === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        lang={option.lang}
                        aria-pressed={active}
                        onClick={() => text.setLang(option.value)}
                        style={
                            option.value === 'bn'
                                ? { fontFamily: BANGLA_FONT_FAMILY }
                                : undefined
                        }
                        className={cn(
                            'h-7 min-w-9 rounded-full px-2.5 transition-colors',
                            option.value === 'bn' && 'text-[13px]',
                            active
                                ? 'bg-brand text-brand-foreground shadow-sm'
                                : tone === 'dark'
                                  ? 'text-white/75 hover:text-white'
                                  : 'text-slate-600 hover:text-slate-900',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
