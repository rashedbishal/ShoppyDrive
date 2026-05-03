import { createContext, useContext, useState, type ReactNode } from 'react';
import { motion } from 'framer-motion';
import { cn } from '@/lib/utils';

type TabsContextValue = {
    activeTab: string;
    setActiveTab: (value: string) => void;
};

const TabsContext = createContext<TabsContextValue>({ activeTab: '', setActiveTab: () => {} });

type TabsProps = {
    defaultValue?: string;
    value?: string;
    onValueChange?: (value: string) => void;
    children: ReactNode;
    className?: string;
};

function Tabs({ defaultValue, value, onValueChange, children, className }: TabsProps): React.JSX.Element {
    const [internalValue, setInternalValue] = useState(defaultValue ?? '');
    const activeTab = value ?? internalValue;
    const setActiveTab = (v: string): void => {
        setInternalValue(v);
        onValueChange?.(v);
    };

    return (
        <TabsContext.Provider value={{ activeTab, setActiveTab }}>
            <div className={cn('space-y-4', className)}>{children}</div>
        </TabsContext.Provider>
    );
}

type TabsListProps = { children: ReactNode; className?: string };

function TabsList({ children, className }: TabsListProps): React.JSX.Element {
    return (
        <div role="tablist" className={cn('flex', className)}>
            {children}
        </div>
    );
}

type TabsTriggerProps = {
    value: string;
    children: ReactNode;
    className?: string;
    disabled?: boolean;
};

function TabsTrigger({ value, children, className, disabled }: TabsTriggerProps): React.JSX.Element {
    const { activeTab, setActiveTab } = useContext(TabsContext);
    const isActive = activeTab === value;

    return (
        <button
            type="button"
            role="tab"
            aria-selected={isActive}
            data-state={isActive ? 'active' : 'inactive'}
            disabled={disabled}
            onClick={() => setActiveTab(value)}
            className={cn(
                'relative text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2',
                isActive ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
                disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                className,
            )}
        >
            {children}
            {isActive ? (
                <motion.span
                    layoutId="tabs-underline"
                    className="absolute inset-x-0 bottom-[-1px] h-0.5 rounded-full bg-[var(--color-primary)]"
                />
            ) : null}
        </button>
    );
}

type TabsContentProps = {
    value: string;
    children: ReactNode;
    className?: string;
};

function TabsContent({ value, children, className }: TabsContentProps): React.JSX.Element | null {
    const { activeTab } = useContext(TabsContext);

    if (activeTab !== value) {
        return null;
    }

    return (
        <div role="tabpanel" data-state="active" className={className}>
            {children}
        </div>
    );
}

type TabItem = {
    id: string;
    label: string;
    count?: number;
};

export { Tabs, TabsList, TabsTrigger, TabsContent };
export type { TabItem };
