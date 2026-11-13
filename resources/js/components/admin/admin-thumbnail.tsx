import { useState } from 'react';
import { cn } from '@/lib/utils';

type AdminThumbnailProps = {
    image: string | null;
    name: string;
    className?: string;
};

/**
 * A small square thumbnail for an admin list row (categories, sub-categories)
 * — shows `image` when it's set and loads successfully, otherwise falls back
 * to the entity's initials. Tracks its own load-error state per instance, so
 * it must be its own component rather than inlined in a `.map()` callback —
 * React hooks can't vary per loop iteration.
 */
export function AdminThumbnail({
    image,
    name,
    className,
}: AdminThumbnailProps): React.JSX.Element {
    const [hasError, setHasError] = useState(false);
    const showImage = Boolean(image) && !hasError;

    if (showImage) {
        return (
            <img
                src={image ?? ''}
                alt={name}
                onError={() => setHasError(true)}
                className={cn(
                    'size-9 shrink-0 rounded-lg border border-slate-100 bg-white object-contain p-1',
                    className,
                )}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-600',
                className,
            )}
        >
            {name.slice(0, 2).toUpperCase()}
        </div>
    );
}
