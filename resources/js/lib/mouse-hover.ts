import type { PointerEvent } from 'react';

/**
 * Hover effects for a real mouse only (DISC-100).
 *
 * After a tap, iOS/iPadOS Safari sends emulated mouse events (mouseover,
 * mouseenter). When a mouseenter handler changes what is visible (a hover
 * image fades in, a highlight pill appears, a menu opens), Safari treats
 * that first tap as "hover" and does not deliver the click, so a link needs
 * a second tap. Pointer events report the real input type, so hover handlers
 * attached with onPointerEnter / onPointerLeave (never onMouseEnter /
 * onMouseLeave) check this first: desktop hover is unchanged and a tap goes
 * straight to the click.
 */
export function isMousePointer(event: PointerEvent): boolean {
    return event.pointerType === 'mouse';
}
