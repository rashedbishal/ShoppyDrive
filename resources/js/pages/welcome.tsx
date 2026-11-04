// Same component reference app.tsx's layout resolver uses for every other
// storefront page (ProductDetail, ProductListing, Cart, Checkout, ...) —
// 'welcome' is the one page name that resolver maps to `null` (see
// app.tsx), so this page wraps itself instead. Using the shared reference
// here (rather than the structurally-identical but distinct
// resources/js/pages/layouts/AppLayout.tsx) lets React recognize it as the
// same mounted layout across Home <-> other-page navigation instead of
// unmounting and remounting Header/Footer/CartDrawer/etc. every time.
import AppLayout from '@/layouts/app-layout';
import Home from './Home';

export default function Welcome(): React.JSX.Element {
    return (
        <AppLayout>
            <Home />
        </AppLayout>
    );
}
