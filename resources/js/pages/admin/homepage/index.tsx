import { Head, Link } from '@inertiajs/react';
import {
    Check,
    Eye,
    EyeOff,
    GripVertical,
    Images,
    LayoutTemplate,
    ListOrdered,
    Megaphone,
    Pencil,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getXsrfToken } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import type { HomepageSetting } from '@/types';

type Props = {
    sections: HomepageSetting[];
};

export default function HomepageIndex({ sections }: Props) {
    // Persists a confirmed reorder locally so the new order shows immediately
    // without an Inertia round-trip back to this page — only ever replaced by
    // a confirmed save, never by `sections` itself (that prop is only fresh
    // on the next real page load).
    const [committedOrder, setCommittedOrder] = useState<
        HomepageSetting[] | null
    >(null);
    // A thin overlay for the eye-icon toggle, kept separate from ordering
    // state entirely so the two features can never clobber each other.
    const [visibilityOverrides, setVisibilityOverrides] = useState<
        Record<number, boolean>
    >({});
    const baseSections = (committedOrder ?? sections).map((section) =>
        section.id in visibilityOverrides
            ? { ...section, is_visible: visibilityOverrides[section.id] }
            : section,
    );

    const [isReordering, setIsReordering] = useState(false);
    const [draftOrder, setDraftOrder] = useState<HomepageSetting[] | null>(
        null,
    );
    const [savingOrder, setSavingOrder] = useState(false);
    const [draggingId, setDraggingId] = useState<number | null>(null);
    const dragIndexRef = useRef<number | null>(null);

    const displayedSections =
        isReordering && draftOrder ? draftOrder : baseSections;

    const startReordering = () => {
        setDraftOrder(baseSections);
        setIsReordering(true);
    };

    const cancelReordering = () => {
        setDraftOrder(null);
        setIsReordering(false);
        setDraggingId(null);
        dragIndexRef.current = null;
    };

    const confirmReordering = async () => {
        if (!draftOrder) {
            return;
        }

        setSavingOrder(true);

        // A plain fetch, not router.post — this only ever rewrites sort_order,
        // so an Inertia visit back to this page would re-fetch and re-render
        // every section just to persist a list of ids.
        try {
            const response = await fetch(route('admin.homepage.reorder'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    ids: draftOrder.map((section) => section.id),
                }),
            });

            if (!response.ok) {
                throw new Error('Failed to save the new section order.');
            }

            setCommittedOrder(draftOrder);
            setDraftOrder(null);
            setIsReordering(false);
        } catch {
            // Leave reorder mode active with the draft intact so nothing is
            // lost — the admin can retry Confirm or back out with Cancel.
        } finally {
            setSavingOrder(false);
        }
    };

    const handleDrop = (dropIndex: number) => {
        const dragIndex = dragIndexRef.current;
        dragIndexRef.current = null;
        setDraggingId(null);

        if (!draftOrder || dragIndex === null || dragIndex === dropIndex) {
            return;
        }

        const reordered = [...draftOrder];
        const [moved] = reordered.splice(dragIndex, 1);
        reordered.splice(dropIndex, 0, moved);
        setDraftOrder(reordered);
    };

    const toggleVisibility = async (section: HomepageSetting) => {
        const optimisticValue = !section.is_visible;
        setVisibilityOverrides((prev) => ({
            ...prev,
            [section.id]: optimisticValue,
        }));

        // A plain fetch, not router.patch — this only ever flips one boolean,
        // so going through an Inertia visit would re-fetch and re-render the
        // whole section list for a one-field change. The optimistic flip
        // above already updated the icon; this just persists it quietly.
        try {
            const response = await fetch(
                route('admin.homepage.toggle-visibility', section.id),
                {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                throw new Error('Failed to toggle section visibility.');
            }

            const { is_visible } = await response.json();
            setVisibilityOverrides((prev) => ({
                ...prev,
                [section.id]: is_visible,
            }));
        } catch {
            // Revert the optimistic flip — the request didn't actually land.
            setVisibilityOverrides((prev) => {
                const next = { ...prev };
                delete next[section.id];

                return next;
            });
        }
    };

    return (
        <>
            <Head title="Homepage Management — Admin" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Homepage Management
                        </h1>
                        <p className="text-sm text-slate-500">
                            Control sections and content on your storefront home
                            page.
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="transition-shadow hover:shadow-md">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Images className="size-4 text-blue-500" />
                                Hero Slides
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-xs text-slate-500">
                                Manage the large sliding banners at the top of
                                your homepage.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="w-full"
                            >
                                <Link href={route('admin.hero-slides.index')}>
                                    Manage Slides
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="transition-shadow hover:shadow-md">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Megaphone className="size-4 text-amber-500" />
                                Promotional Banners
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-xs text-slate-500">
                                Control the wide visual banners between product
                                sections.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="w-full"
                            >
                                <Link
                                    href={route(
                                        'admin.promotional-banners.index',
                                    )}
                                >
                                    Manage Banners
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="transition-shadow hover:shadow-md">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <LayoutTemplate className="size-4 text-emerald-500" />
                                Active Flash Sale
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-xs text-slate-500">
                                Set which flash sale is currently active on the
                                homepage.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="w-full"
                            >
                                <Link href={route('admin.flash-sales.index')}>
                                    Go to Flash Sales
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card
                    className={cn(
                        isReordering &&
                            'ring-2 ring-[#c8102e]/20 ring-offset-2',
                    )}
                >
                    <CardHeader className="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle className="text-base font-semibold">
                                Page Sections
                            </CardTitle>
                            <p className="mt-1 text-xs text-slate-500">
                                {isReordering
                                    ? 'Drag rows by their handle, then confirm to save or cancel to discard.'
                                    : 'Enter reorder mode to change the order sections appear on the storefront homepage.'}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {isReordering ? (
                                <>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="w-full text-slate-500 sm:w-auto"
                                        onClick={cancelReordering}
                                        disabled={savingOrder}
                                    >
                                        <X className="mr-1.5 size-4" />
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25] sm:w-auto"
                                        onClick={confirmReordering}
                                        disabled={savingOrder}
                                    >
                                        <Check className="mr-1.5 size-4" />
                                        {savingOrder
                                            ? 'Saving…'
                                            : 'Confirm Order'}
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-full sm:w-auto"
                                    onClick={startReordering}
                                >
                                    <ListOrdered className="mr-1.5 size-4" />
                                    Reorder Sections
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y divide-slate-100">
                            {displayedSections.map((section, index) => (
                                <div
                                    key={section.id}
                                    draggable={isReordering}
                                    onDragStart={() => {
                                        if (!isReordering) {
                                            return;
                                        }

                                        dragIndexRef.current = index;
                                        setDraggingId(section.id);
                                    }}
                                    onDragOver={(e) =>
                                        isReordering && e.preventDefault()
                                    }
                                    onDrop={() =>
                                        isReordering && handleDrop(index)
                                    }
                                    onDragEnd={() => {
                                        dragIndexRef.current = null;
                                        setDraggingId(null);
                                    }}
                                    className={cn(
                                        'flex items-center gap-4 px-6 py-4 transition-colors',
                                        isReordering
                                            ? 'bg-white hover:bg-slate-50'
                                            : 'hover:bg-slate-50/50',
                                        draggingId === section.id &&
                                            'opacity-40',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'transition-colors',
                                            isReordering
                                                ? 'cursor-grab text-slate-400 hover:text-slate-600 active:cursor-grabbing'
                                                : 'cursor-default text-slate-200',
                                        )}
                                    >
                                        <GripVertical className="size-5" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <h4 className="text-sm font-semibold text-slate-800">
                                                {section.section_name}
                                            </h4>
                                            {!section.is_visible && (
                                                <Badge
                                                    variant="secondary"
                                                    className="h-4 px-1.5 text-[10px] opacity-60"
                                                >
                                                    Hidden
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="truncate text-xs text-slate-400">
                                            {section.title ||
                                                'Using default title'}{' '}
                                            {section.description
                                                ? `· ${section.description}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-slate-400 hover:text-slate-600 disabled:opacity-30"
                                            title={
                                                isReordering
                                                    ? 'Finish reordering to change visibility'
                                                    : section.is_visible
                                                      ? 'Visible on storefront — click to hide'
                                                      : 'Hidden from storefront — click to show'
                                            }
                                            disabled={isReordering}
                                            onClick={() =>
                                                toggleVisibility(section)
                                            }
                                        >
                                            {section.is_visible ? (
                                                <Eye className="size-4 text-emerald-500" />
                                            ) : (
                                                <EyeOff className="size-4 text-slate-300" />
                                            )}
                                        </Button>
                                        {isReordering ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8 text-slate-300 opacity-30"
                                                title="Finish reordering to edit this section"
                                                disabled
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                        ) : (
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                                className="size-8 text-slate-400 hover:text-slate-600"
                                            >
                                                <Link
                                                    href={route(
                                                        'admin.homepage.edit',
                                                        section.id,
                                                    )}
                                                >
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
