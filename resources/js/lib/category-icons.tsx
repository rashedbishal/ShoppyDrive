import { ShoppingBag } from 'lucide-react';
import { DynamicIcon, iconNames } from 'lucide-react/dynamic';
import type { IconName } from 'lucide-react/dynamic';
import React from 'react';

export const DEFAULT_CATEGORY_ICON: IconName = 'shopping-bag';

const ICON_NAME_SET = new Set<string>(iconNames);

function titleCase(name: string): string {
    return name
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export const CATEGORY_ICON_OPTIONS: { value: string; label: string }[] = [
    ...iconNames,
]
    .sort((a, b) => a.localeCompare(b))
    .map((name) => ({ value: name, label: titleCase(name) }));

export function isValidCategoryIcon(icon?: string | null): icon is IconName {
    return !!icon && ICON_NAME_SET.has(icon);
}

type CategoryIconProps = {
    icon?: string | null;
    className?: string;
};

function ShoppingBagFallback(): React.JSX.Element {
    return <ShoppingBag strokeWidth={1.75} />;
}

export function CategoryIcon({
    icon,
    className,
}: CategoryIconProps): React.JSX.Element {
    const name = isValidCategoryIcon(icon) ? icon : DEFAULT_CATEGORY_ICON;

    return (
        <DynamicIcon
            name={name}
            className={className}
            fallback={ShoppingBagFallback}
            strokeWidth={1.75}
        />
    );
}
