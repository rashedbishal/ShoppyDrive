import { Head, useForm, usePage } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';

type Props = {
    token: string;
    slug: string;
    amount: number;
    orderNumber: string;
    provider: 'bkash' | 'nagad';
    bkashNumber: string | null;
    nagadNumber: string | null;
    siteName: string;
    siteLogo: string | null;
};

export default function LandingPayment({ token, slug, amount, orderNumber, provider, bkashNumber, nagadNumber, siteName, siteLogo }: Props) {
    const providerNumber = provider === 'bkash' ? bkashNumber : nagadNumber;
    const providerLabel = provider === 'bkash' ? 'Bkash' : 'Nagad';
    const providerColor = provider === 'bkash' ? '#e2136e' : '#f4821f';

    const { errors: pageErrors } = usePage<any>().props as { errors: Record<string, string> };
    const { data, setData, post, processing, errors } = useForm({ transaction_id: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('lp.payment.submit', { slug, token }));
    };

    return (
        <>
            <Head title={`Complete Payment — ${siteName}`} />
            <div className="min-h-screen bg-slate-50 flex flex-col">
                <header className="border-b border-slate-100 bg-white px-4 py-3">
                    <div className="mx-auto flex max-w-lg items-center gap-2">
                        {siteLogo ? (
                            <img src={siteLogo} alt={siteName} className="h-8 w-auto object-contain" />
                        ) : (
                            <span className="flex size-8 items-center justify-center rounded-lg bg-[#c8102e]">
                                <ShoppingBag className="size-4 text-white" />
                            </span>
                        )}
                        <span className="text-sm font-bold text-slate-800">{siteName}</span>
                    </div>
                </header>

                <main className="flex-1 px-4 py-10">
                    <div className="mx-auto max-w-md space-y-6">
                        <div className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-6 space-y-5">
                            <div className="text-center">
                                <div className="inline-flex size-14 items-center justify-center rounded-full mb-3" style={{ backgroundColor: providerColor + '20' }}>
                                    <span className="text-lg font-extrabold" style={{ color: providerColor }}>{providerLabel[0]}</span>
                                </div>
                                <h1 className="text-lg font-bold text-slate-900">Pay via {providerLabel}</h1>
                                <p className="text-sm text-slate-500 mt-0.5">Order #{orderNumber}</p>
                            </div>

                            <div className="rounded-xl p-4 text-center" style={{ backgroundColor: providerColor + '10', borderColor: providerColor + '30', borderWidth: 1 }}>
                                <p className="text-xs text-slate-500 mb-1">Send exactly</p>
                                <p className="text-3xl font-extrabold text-slate-900">৳{amount.toLocaleString()}</p>
                                <p className="text-xs text-slate-500 mt-2">to this {providerLabel} number</p>
                                <p className="text-xl font-bold mt-1" style={{ color: providerColor }}>{providerNumber}</p>
                                <p className="text-xs text-slate-400 mt-2">Send Money → Personal</p>
                            </div>

                            <ol className="space-y-2 text-sm text-slate-600">
                                <li className="flex gap-2"><span className="font-bold text-slate-800">1.</span> Open your {providerLabel} app</li>
                                <li className="flex gap-2"><span className="font-bold text-slate-800">2.</span> Go to <strong>Send Money</strong> and enter the number above</li>
                                <li className="flex gap-2"><span className="font-bold text-slate-800">3.</span> Send exactly <strong>৳{amount.toLocaleString()}</strong></li>
                                <li className="flex gap-2"><span className="font-bold text-slate-800">4.</span> Copy the <strong>Transaction ID</strong> from the confirmation SMS</li>
                                <li className="flex gap-2"><span className="font-bold text-slate-800">5.</span> Enter it below</li>
                            </ol>

                            <form onSubmit={handleSubmit} className="space-y-3">
                                {pageErrors.ip && <p className="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">{pageErrors.ip}</p>}
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">Transaction ID</label>
                                    <input
                                        className="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-[#c8102e]/30 focus:border-[#c8102e]"
                                        value={data.transaction_id}
                                        onChange={(e) => setData('transaction_id', e.target.value)}
                                        placeholder="e.g. 8G3H2K9P..."
                                    />
                                    {errors.transaction_id && <p className="mt-1 text-xs text-red-600">{errors.transaction_id}</p>}
                                </div>

                                <button type="submit" disabled={processing || !data.transaction_id} className="w-full rounded-xl py-3 text-sm font-bold text-white disabled:opacity-60 transition-colors" style={{ backgroundColor: providerColor }}>
                                    {processing ? 'Verifying…' : 'Confirm Payment'}
                                </button>
                            </form>
                        </div>

                        <p className="text-center text-xs text-slate-400">
                            Your order will be confirmed once we verify the transaction.
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
