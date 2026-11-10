import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';

type FormPageHeaderProps = {
    title: string;
    description?: string;
    backHref: string;
    backLabel: string;
};

/**
 * Title bar for an admin create/edit screen shown as a full page: a back
 * arrow to the list, the title and a one-line description.
 */
export function FormPageHeader({
    title,
    description,
    backHref,
    backLabel,
}: FormPageHeaderProps): React.JSX.Element {
    return (
        <div className="flex items-center gap-3">
            <Button
                asChild
                variant="ghost"
                size="icon"
                className="shrink-0 rounded-full"
            >
                <Link href={backHref} aria-label={backLabel}>
                    <ArrowLeft className="size-5" />
                </Link>
            </Button>
            <div className="min-w-0">
                <h1 className="truncate text-xl font-bold text-slate-900">
                    {title}
                </h1>
                {description && (
                    <p className="truncate text-sm text-slate-500">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}
