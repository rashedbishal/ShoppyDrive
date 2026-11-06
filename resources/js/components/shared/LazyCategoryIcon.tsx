import { ShoppingBag } from 'lucide-react';
import { lazy, Suspense } from 'react';
import type { JSX } from 'react';

// PERF-003: the full icon registry is about 50 KB gzip, so storefront pages
// paint first and fetch it afterwards. The bag is what the icon falls back to.
const CategoryIcon = lazy(() =>
    import('@/lib/category-icons').then((module) => ({
        default: module.CategoryIcon,
    })),
);

type LazyCategoryIconProps = {
    icon?: string | null;
    className?: string;
};

export function LazyCategoryIcon({
    icon,
    className,
}: LazyCategoryIconProps): JSX.Element {
    return (
        <Suspense
            fallback={<ShoppingBag className={className} strokeWidth={1.75} />}
        >
            <CategoryIcon icon={icon} className={className} />
        </Suspense>
    );
}
