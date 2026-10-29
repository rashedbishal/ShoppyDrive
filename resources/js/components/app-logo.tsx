import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-2xl bg-[var(--color-secondary)] text-white shadow-[var(--shadow-card)]">
                <AppLogoIcon className="size-6 fill-current text-white" />
            </div>
            <div className="grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-semibold text-[var(--color-text-primary)]">
                    ShoppyDrive
                </span>
                <span className="truncate text-xs text-[var(--color-text-secondary)]">
                    Premium Bangladesh ecommerce
                </span>
            </div>
        </div>
    );
}
