import { type LucideIcon } from "lucide-react";
import { Link } from "@inertiajs/react";
import { cn } from "@/lib/utils";

type EmptyStateAction = {
    label: string;
    href?: string;
    /** Runs on click; with `href` it runs as the link is followed (e.g. to close a drawer). */
    onClick?: () => void;
};

type EmptyStateProps = {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: EmptyStateAction;
    className?: string;
};

function EmptyState({ icon: Icon, title, description, action, className }: EmptyStateProps) {
    return (
        <div className={cn("rounded-2xl border border-dashed border-border bg-surface p-8 text-center shadow-sm", className)}>
            <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-surface-alt text-text-muted">
                <Icon className="size-8" />
            </div>
            <div className="mx-auto mt-5 max-w-md space-y-2">
                <h3 className="text-xl font-semibold text-text-primary">{title}</h3>
                <p className="text-sm leading-6 text-text-secondary">{description}</p>
            </div>
            {action ? (
                <div className="mt-6">
                    {action.href ? (
                        <Link
                            href={action.href}
                            onClick={action.onClick}
                            className="inline-flex h-11 items-center justify-center rounded-xl bg-primary px-6 font-semibold text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            {action.label}
                        </Link>
                    ) : (
                        <button
                            type="button"
                            onClick={action.onClick}
                            className="inline-flex h-11 items-center justify-center rounded-xl bg-primary px-6 font-semibold text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            {action.label}
                        </button>
                    )}
                </div>
            ) : null}
        </div>
    );
}

export { EmptyState };
