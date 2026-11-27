/**
 * F1-02: framer-motion's animation engine (incl. drag and layout/layoutId,
 * which the header underline and the home hero/AutoSlider use), loaded as
 * its own chunk after the page instead of in every page's first download.
 * Storefront components import the lightweight `m` component (aliased as
 * `motion`) and get these features from <LazyMotion> in app.tsx; until the
 * chunk arrives they render in their `initial` state.
 */
export { domMax as default } from 'framer-motion';
