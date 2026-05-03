import AppLayout from './layouts/AppLayout';
import Home from './Home';

export default function Welcome(): React.JSX.Element {
    return (
        <AppLayout>
            <Home />
        </AppLayout>
    );
}
