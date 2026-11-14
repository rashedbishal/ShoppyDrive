import { router } from '@inertiajs/react';
import { Search, Trash2, TriangleAlert, UserX } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Modal } from '@/components/ui/modal';
import { getXsrfToken } from '@/lib/csrf';

type Customer = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    orders_count: number;
    anonymized: boolean;
};

type Summary = {
    orders: number;
    unfinished_orders: number;
    payments: number;
    refunds: number;
    returns: number;
    reviews: number;
    addresses: number;
    files: number;
};

type Step = 'idle' | 'first-password' | 'second-password';

async function requestJson<T>(
    url: string,
    init: RequestInit = {},
): Promise<{ ok: boolean; data: T }> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(init.headers ?? {}),
        },
    });

    return { ok: response.ok, data: (await response.json()) as T };
}

function customerLabel(customer: Customer): string {
    return [`#${customer.id}`, customer.name, customer.email, customer.phone]
        .filter(Boolean)
        .join(' · ');
}

/**
 * Security › "Permanently delete a customer" (CustomerErasureService):
 * search, see what would be removed, then two separate password checks.
 * Rendered for the mother admin only; the routes enforce it too.
 */
export function CustomerErasureCard(): React.JSX.Element {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Customer[]>([]);
    const [searching, setSearching] = useState(false);
    const [selected, setSelected] = useState<Customer | null>(null);
    const [summary, setSummary] = useState<Summary | null>(null);
    const [step, setStep] = useState<Step>('idle');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [working, setWorking] = useState(false);

    useEffect(() => {
        if (selected !== null) {
            return;
        }

        const timer = window.setTimeout(async () => {
            setSearching(true);

            try {
                const { data } = await requestJson<{ customers: Customer[] }>(
                    route('admin.settings.security.customer-erasure.search', {
                        q: query,
                    }),
                );
                setResults(data.customers ?? []);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 300);

        return () => window.clearTimeout(timer);
    }, [query, selected]);

    const choose = async (customer: Customer) => {
        setSelected(customer);
        setSummary(null);

        try {
            const { data } = await requestJson<Summary>(
                route(
                    'admin.settings.security.customer-erasure.summary',
                    customer.id,
                ),
            );
            setSummary(data);
        } catch {
            setSummary(null);
        }
    };

    const reset = () => {
        setSelected(null);
        setSummary(null);
        setStep('idle');
        setPassword('');
        setError(null);
        setQuery('');
    };

    const closeDialog = () => {
        setStep('idle');
        setPassword('');
        setError(null);
    };

    const submitFirstPassword = async (event: React.FormEvent) => {
        event.preventDefault();

        if (selected === null) {
            return;
        }

        setWorking(true);
        setError(null);

        try {
            const { ok, data } = await requestJson<{ message?: string }>(
                route(
                    'admin.settings.security.customer-erasure.confirm',
                    selected.id,
                ),
                { method: 'POST', body: JSON.stringify({ password }) },
            );

            if (ok) {
                setPassword('');
                setStep('second-password');
            } else {
                setError(data.message ?? 'That did not work. Try again.');
            }
        } catch {
            setError('Could not reach the server. Try again.');
        } finally {
            setWorking(false);
        }
    };

    const submitSecondPassword = (event: React.FormEvent) => {
        event.preventDefault();

        if (selected === null) {
            return;
        }

        router.delete(
            route(
                'admin.settings.security.customer-erasure.destroy',
                selected.id,
            ),
            {
                data: { password },
                preserveScroll: true,
                onStart: () => {
                    setWorking(true);
                    setError(null);
                },
                onSuccess: () => reset(),
                onError: (errors) => {
                    setPassword('');
                    setError(
                        errors.password ??
                            errors.confirmation ??
                            'The customer could not be deleted.',
                    );

                    if (errors.confirmation) {
                        setStep('first-password');
                    }
                },
                onFinish: () => setWorking(false),
            },
        );
    };

    const rows: [string, number][] = summary
        ? [
              ['Orders', summary.orders],
              ['Payments', summary.payments],
              ['Refunds', summary.refunds],
              ['Returns', summary.returns],
              ['Reviews', summary.reviews],
              ['Saved addresses', summary.addresses],
              ['Uploaded files (avatar, review photos)', summary.files],
          ]
        : [];

    return (
        <Card className="border-red-200 shadow-sm">
            <CardHeader className="border-b border-red-100 bg-red-50/60">
                <CardTitle className="flex items-center gap-2 text-sm font-semibold text-red-800">
                    <UserX className="size-4" />
                    Permanently delete a customer
                </CardTitle>
                <p className="text-xs leading-5 text-red-700">
                    For database clean-up. Deletes the customer's account and
                    everything tied to it — every order with its payments,
                    refunds, returns and courier records, reviews, addresses,
                    cart, wishlist, and uploaded photos. Stock from unfinished
                    orders goes back into inventory. This cannot be undone, and
                    needs your password twice.
                </p>
            </CardHeader>
            <CardContent className="space-y-4 pt-4">
                {selected === null ? (
                    <div className="space-y-2">
                        <label
                            htmlFor="customer-erasure-search"
                            className="text-xs font-medium text-slate-600"
                        >
                            Find the customer
                        </label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                id="customer-erasure-search"
                                type="search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Name, email, phone or customer number"
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:ring-1 focus:ring-brand focus:outline-none"
                            />
                        </div>
                        <ul className="max-h-64 divide-y divide-slate-100 overflow-auto rounded-lg border border-slate-200">
                            {results.length === 0 ? (
                                <li className="px-3 py-3 text-xs text-slate-600">
                                    {searching
                                        ? 'Searching…'
                                        : 'No customers found.'}
                                </li>
                            ) : (
                                results.map((customer) => (
                                    <li key={customer.id}>
                                        <button
                                            type="button"
                                            onClick={() => choose(customer)}
                                            className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50"
                                        >
                                            <span className="min-w-0 truncate text-slate-700">
                                                {customerLabel(customer)}
                                                {customer.anonymized && (
                                                    <span className="ml-1 text-xs text-slate-600">
                                                        (anonymized)
                                                    </span>
                                                )}
                                            </span>
                                            <span className="shrink-0 text-xs text-slate-600">
                                                {customer.orders_count}{' '}
                                                {customer.orders_count === 1
                                                    ? 'order'
                                                    : 'orders'}
                                            </span>
                                        </button>
                                    </li>
                                ))
                            )}
                        </ul>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                            <span className="text-sm font-medium break-all text-slate-800">
                                {customerLabel(selected)}
                            </span>
                            <button
                                type="button"
                                onClick={reset}
                                className="text-xs font-medium text-slate-600 underline hover:text-slate-800"
                            >
                                Choose someone else
                            </button>
                        </div>

                        {summary === null ? (
                            <p className="text-xs text-slate-600">
                                Checking what would be deleted…
                            </p>
                        ) : (
                            <div className="rounded-lg border border-slate-200 p-3">
                                <p className="mb-2 text-xs font-semibold text-slate-600">
                                    This will permanently delete:
                                </p>
                                <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-3">
                                    {rows.map(([label, count]) => (
                                        <div
                                            key={label}
                                            className="flex justify-between gap-2"
                                        >
                                            <dt className="text-slate-600">
                                                {label}
                                            </dt>
                                            <dd className="font-semibold text-slate-800">
                                                {count}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                {summary.unfinished_orders > 0 && (
                                    <p className="mt-2 text-xs text-slate-600">
                                        {summary.unfinished_orders} unfinished{' '}
                                        {summary.unfinished_orders === 1
                                            ? 'order puts its'
                                            : 'orders put their'}{' '}
                                        stock back into inventory.
                                    </p>
                                )}
                            </div>
                        )}

                        <Button
                            type="button"
                            disabled={summary === null}
                            onClick={() => setStep('first-password')}
                            className="bg-red-600 text-white hover:bg-red-700"
                        >
                            <Trash2 className="size-4" />
                            Delete permanently
                        </Button>
                    </div>
                )}
            </CardContent>

            <Modal
                isOpen={step !== 'idle' && selected !== null}
                onClose={closeDialog}
                title={
                    step === 'first-password'
                        ? 'Confirm with your password (1 of 2)'
                        : 'Final confirmation (2 of 2)'
                }
                size="sm"
            >
                <form
                    onSubmit={
                        step === 'first-password'
                            ? submitFirstPassword
                            : submitSecondPassword
                    }
                    className="space-y-3"
                >
                    <p className="flex items-start gap-2 text-sm text-slate-700">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-red-600" />
                        {step === 'first-password'
                            ? `You're about to permanently delete ${selected ? customerLabel(selected) : 'this customer'} and all their data.`
                            : 'Enter your password once more to delete this customer for good. This cannot be undone.'}
                    </p>
                    <label
                        htmlFor="customer-erasure-password"
                        className="block text-xs font-medium text-slate-600"
                    >
                        Your (mother admin) password
                    </label>
                    <input
                        id="customer-erasure-password"
                        type="password"
                        autoComplete="current-password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        autoFocus
                        required
                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-brand focus:outline-none"
                    />
                    {error && (
                        <p
                            data-form-error
                            className="text-xs font-medium text-red-600"
                            role="alert"
                        >
                            {error}
                        </p>
                    )}
                    <div className="flex justify-end gap-2 pt-1">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDialog}
                            disabled={working}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={working || password === ''}
                            className="bg-red-600 text-white hover:bg-red-700"
                        >
                            {working
                                ? 'Please wait…'
                                : step === 'first-password'
                                  ? 'Continue'
                                  : 'Delete permanently'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </Card>
    );
}
