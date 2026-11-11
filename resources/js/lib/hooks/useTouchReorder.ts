import { useCallback, useEffect, useId, useRef, useState } from 'react';
import type { CSSProperties, PointerEvent as ReactPointerEvent } from 'react';

/**
 * Browsers only start a native HTML5 drag on touch after a long press, so on
 * phones and tablets a draggable row feels broken. This hook gives touch and
 * pen input an immediate, pointer-event-based drag instead; mouse input keeps
 * using the native drag-and-drop the lists already have.
 *
 * Spread `handleProps(index)` on the element the finger grabs and `rowProps(index)`
 * on every row of the same list. While a touch drag is running the handle has
 * `touch-action: none`, so dragging it never scrolls the page; the rest of the
 * row still scrolls normally. The list edge auto-scrolls while dragging.
 */

const EDGE_PX = 72;
const MAX_SCROLL_STEP = 18;

type Options = {
    enabled: boolean;
    onStart?: (index: number) => void;
    onDrop: (fromIndex: number, toIndex: number) => void;
    onEnd?: () => void;
};

type DragState = { from: number; over: number };

function scrollParentOf(element: HTMLElement | null): HTMLElement | null {
    let node = element?.parentElement ?? null;

    while (node) {
        const { overflowY } = getComputedStyle(node);

        if (
            (overflowY === 'auto' || overflowY === 'scroll') &&
            node.scrollHeight > node.clientHeight
        ) {
            return node;
        }

        node = node.parentElement;
    }

    return null;
}

function startAutoScroll(
    frameRef: { current: number | null },
    scrollerRef: { current: HTMLElement | null },
    pointerY: { current: number },
): void {
    const tick = () => {
        const scroller = scrollerRef.current;
        const rect = scroller?.getBoundingClientRect();
        const top = rect ? rect.top : 0;
        const bottom = rect ? rect.bottom : window.innerHeight;
        const y = pointerY.current;
        let step = 0;

        if (y < top + EDGE_PX) {
            step = -Math.min(MAX_SCROLL_STEP, (top + EDGE_PX - y) / 4);
        } else if (y > bottom - EDGE_PX) {
            step = Math.min(MAX_SCROLL_STEP, (y - (bottom - EDGE_PX)) / 4);
        }

        if (step !== 0) {
            if (scroller) {
                scroller.scrollTop += step;
            } else {
                window.scrollBy(0, step);
            }
        }

        frameRef.current = requestAnimationFrame(tick);
    };

    frameRef.current = requestAnimationFrame(tick);
}

export function useTouchReorder({ enabled, onStart, onDrop, onEnd }: Options) {
    const groupId = useId();
    const [drag, setDrag] = useState<DragState | null>(null);
    const dragRef = useRef<DragState | null>(null);
    const pointerY = useRef(0);
    const frameRef = useRef<number | null>(null);
    const scrollerRef = useRef<HTMLElement | null>(null);

    const stopAutoScroll = useCallback(() => {
        if (frameRef.current !== null) {
            cancelAnimationFrame(frameRef.current);
            frameRef.current = null;
        }
    }, []);

    useEffect(() => stopAutoScroll, [stopAutoScroll]);

    const update = (next: DragState | null) => {
        dragRef.current = next;
        setDrag(next);
    };

    const finish = (commit: boolean) => {
        stopAutoScroll();
        const current = dragRef.current;
        update(null);

        if (commit && current && current.from !== current.over) {
            onDrop(current.from, current.over);
        }

        onEnd?.();
    };

    const handleProps = (index: number) => ({
        style: (enabled
            ? { touchAction: 'none', userSelect: 'none' }
            : undefined) as CSSProperties | undefined,
        onClick: (event: React.MouseEvent) => event.stopPropagation(),
        onPointerDown: (event: ReactPointerEvent<HTMLElement>) => {
            if (!enabled || event.pointerType === 'mouse' || event.button > 0) {
                return;
            }

            event.currentTarget.setPointerCapture(event.pointerId);
            pointerY.current = event.clientY;
            scrollerRef.current = scrollParentOf(event.currentTarget);
            update({ from: index, over: index });
            onStart?.(index);
            startAutoScroll(frameRef, scrollerRef, pointerY);
        },
        onPointerMove: (event: ReactPointerEvent<HTMLElement>) => {
            const current = dragRef.current;

            if (!current) {
                return;
            }

            pointerY.current = event.clientY;
            const row = document
                .elementFromPoint(event.clientX, event.clientY)
                ?.closest<HTMLElement>(`[data-reorder-group="${groupId}"]`);
            const over = row ? Number(row.dataset.reorderIndex) : NaN;

            if (!Number.isNaN(over) && over !== current.over) {
                update({ from: current.from, over });
            }
        },
        onPointerUp: () => finish(true),
        onPointerCancel: () => finish(false),
    });

    const rowProps = (index: number) => ({
        'data-reorder-group': groupId,
        'data-reorder-index': index,
    });

    return {
        handleProps,
        rowProps,
        /** Index being dragged by touch, or null (also true to disable native drag meanwhile). */
        touchDraggingIndex: drag?.from ?? null,
        /** Index the dragged row would land on. */
        touchOverIndex: drag?.over ?? null,
    };
}
