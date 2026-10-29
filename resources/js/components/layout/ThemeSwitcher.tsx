import { Check, Palette } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SITE_THEMES, useSiteTheme } from '@/hooks/use-theme';

/** Lets a visitor pick their own storefront look. Purely client-side — stored in localStorage, no backend involved. */
export default function ThemeSwitcher(): React.JSX.Element {
    const { theme, setTheme } = useSiteTheme();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label="Change theme"
                    className="inline-flex size-10 items-center justify-center rounded-xl text-[var(--color-text-secondary)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]"
                >
                    <Palette className="size-5" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuLabel>Theme</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {SITE_THEMES.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        onClick={() => setTheme(option.value)}
                        className="flex items-center justify-between"
                    >
                        {option.label}
                        {theme === option.value ? (
                            <Check className="size-4" />
                        ) : null}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
