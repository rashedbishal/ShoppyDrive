/**
 * F1-02: whether framer-motion's animation features (lib/motion-features.ts,
 * loaded after the page by <LazyMotion> in app.tsx) have arrived. Before
 * then an `m` component can't animate: one whose `animate` target changes
 * in that window (e.g. a product card revealing itself as it scrolls into
 * view) would stay in its first state, so such components check this when
 * they mount and skip that animation if the features aren't here yet.
 */
let ready = false;

export function markMotionReady(): void {
    ready = true;
}

export function isMotionReady(): boolean {
    return ready;
}
