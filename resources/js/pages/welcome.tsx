import Home from './Home';
import AppLayout from './layouts/AppLayout';

export default function Welcome(): React.JSX.Element {
    return (
        <AppLayout>
            <Home />
        </AppLayout>
    );
}
