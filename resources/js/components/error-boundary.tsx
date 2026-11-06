import { router } from '@inertiajs/react';
import { Component } from 'react';
import type { ErrorInfo, ReactNode } from 'react';
import { getXsrfToken } from '@/lib/csrf';

type Props = { children: ReactNode };
type State = { hasError: boolean };

/**
 * FE-010: without this, any error while a page renders left a blank white
 * screen (including at checkout). Shows a friendly message with Reload /
 * Home instead, resets on the next page visit, and reports the crash once
 * to the server log (ClientErrorController). The cart lives in
 * localStorage and on the server, so it is unaffected.
 */
export default class ErrorBoundary extends Component<Props, State> {
    state: State = { hasError: false };

    private removeNavigateListener: (() => void) | null = null;

    static getDerivedStateFromError(): State {
        return { hasError: true };
    }

    componentDidMount(): void {
        this.removeNavigateListener = router.on('navigate', () => {
            if (this.state.hasError) {
                this.setState({ hasError: false });
            }
        });
    }

    componentWillUnmount(): void {
        this.removeNavigateListener?.();
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        try {
            void fetch(route('client-errors.store'), {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({
                    message: String(error?.message ?? error).slice(0, 2000),
                    path: window.location.pathname,
                    component_stack: (info.componentStack ?? '').slice(0, 4000),
                }),
            }).catch(() => undefined);
        } catch {
            // Reporting must never make the crash screen itself fail.
        }
    }

    render(): ReactNode {
        if (!this.state.hasError) {
            return this.props.children;
        }

        const isAdmin = window.location.pathname.startsWith('/admin');

        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
                <div className="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <h1 className="text-lg font-semibold text-slate-900">
                        Something went wrong on this page.
                    </h1>
                    <p className="mt-2 text-sm text-slate-600">
                        {isAdmin
                            ? 'Nothing was saved or changed. Please reload and try again.'
                            : 'Your cart is safe. Please reload the page or go back to the home page.'}
                    </p>
                    <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                        >
                            Reload page
                        </button>
                        <a
                            href={isAdmin ? '/admin' : '/'}
                            className="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            {isAdmin ? 'Go to dashboard' : 'Go to home page'}
                        </a>
                    </div>
                </div>
            </div>
        );
    }
}
