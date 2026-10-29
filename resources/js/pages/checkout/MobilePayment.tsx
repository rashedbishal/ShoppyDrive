import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    ChevronLeft,
    Copy,
    Lock,
    Smartphone,
} from 'lucide-react';
import React, { useState } from 'react';
import bkashImg from '@/assets/bkash.png';
import nagadImg from '@/assets/nagad.png';
import CheckoutLayout from '@/components/layout/CheckoutLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/utils';

interface Props {
    token: string;
    amount: number;
    orderNumber: string;
    bkashNumber: string | null;
    nagadNumber: string | null;
}

type Provider = 'bkash' | 'nagad';

const PROVIDERS: {
    id: Provider;
    label: string;
    brandColor: string;
    color: string;
    bg: string;
    border: string;
    numberLabel: string;
    txnLabel: string;
    txnPlaceholder: string;
    logo: string;
}[] = [
    {
        id: 'bkash',
        label: 'bKash',
        brandColor: '#E2136E',
        color: 'text-[#E2136E]',
        bg: 'bg-[#fdf0f6]',
        border: 'border-[#E2136E]',
        numberLabel: 'bKash Number',
        txnLabel: 'Transaction ID (TrxID)',
        txnPlaceholder: 'e.g. DED66E1DFW',
        logo: bkashImg,
    },
    {
        id: 'nagad',
        label: 'Nagad',
        brandColor: '#F4821F',
        color: 'text-[#F4821F]',
        bg: 'bg-[#fff5ec]',
        border: 'border-[#F4821F]',
        numberLabel: 'Nagad Number',
        txnLabel: 'Transaction ID (TxnID)',
        txnPlaceholder: 'e.g. 75AIB4WJ',
        logo: nagadImg,
    },
];

export default function MobilePayment({
    token,
    amount,
    orderNumber,
    bkashNumber,
    nagadNumber,
}: Props): React.JSX.Element {
    const { props } = usePage<any>();
    const flash = props.flash as { error?: string } | undefined;
    const errors = props.errors as { transaction_id?: string } | undefined;

    const [provider, setProvider] = useState<Provider | null>(null);
    const [transactionId, setTransactionId] = useState('');
    const [copied, setCopied] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const selectedProvider = PROVIDERS.find((p) => p.id === provider) ?? null;
    const paymentNumber = provider === 'bkash' ? bkashNumber : nagadNumber;

    const copyNumber = async () => {
        if (!paymentNumber) {
            return;
        }

        await navigator.clipboard.writeText(paymentNumber);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (submitting || !provider || !transactionId.trim()) {
            return;
        }

        setSubmitting(true);
        router.post(
            route('payment.mobile.submit', token),
            { provider, transaction_id: transactionId.trim().toUpperCase() },
            { onFinish: () => setSubmitting(false) },
        );
    };

    return (
        <CheckoutLayout>
            <Head title="Complete Payment" />
            <div className="mx-auto max-w-lg space-y-5">
                {/* Header */}
                <div className="text-center">
                    <div className="mb-4 flex items-center justify-center gap-1.5 text-[var(--color-text-muted)]">
                        <Lock size={14} />
                        <span className="text-xs font-bold tracking-widest uppercase">
                            Secure Checkout
                        </span>
                    </div>
                    <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-pink-100">
                        <Smartphone size={28} className="text-pink-600" />
                    </div>
                    <h1 className="text-xl font-bold text-[var(--color-text-primary)]">
                        Complete Your Payment
                    </h1>
                    <p className="mt-1 text-sm text-[var(--color-text-muted)]">
                        Order {orderNumber}
                    </p>
                </div>

                {flash?.error && (
                    <div className="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <AlertCircle size={16} className="shrink-0" />
                        {flash.error}
                    </div>
                )}

                {/* Step 1 — Choose provider */}
                <div className="space-y-4 rounded-2xl border border-[var(--color-border)] bg-white p-6">
                    <div className="flex items-center gap-2">
                        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-pink-600 text-xs font-bold text-white">
                            1
                        </span>
                        <h2 className="text-sm font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                            Select Payment Method
                        </h2>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        {PROVIDERS.map((p) => {
                            const number =
                                p.id === 'bkash' ? bkashNumber : nagadNumber;
                            const isSelected = provider === p.id;

                            return (
                                <button
                                    key={p.id}
                                    type="button"
                                    disabled={!number}
                                    onClick={() => {
                                        setProvider(p.id);
                                        setTransactionId('');
                                    }}
                                    style={{ borderColor: p.brandColor }}
                                    className="group relative flex h-24 items-center justify-center overflow-hidden rounded-xl border-2 bg-white transition-all duration-300 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    {isSelected && (
                                        <span className="absolute top-2 right-2">
                                            <CheckCircle2
                                                size={16}
                                                style={{ color: p.brandColor }}
                                            />
                                        </span>
                                    )}
                                    <img
                                        src={p.logo}
                                        alt={p.label}
                                        className="h-full w-full object-contain p-1 transition-transform duration-300 ease-in-out group-hover:scale-110"
                                    />
                                    {!number && (
                                        <span className="absolute bottom-1.5 text-[10px] text-slate-400">
                                            Not configured
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Steps 2 & 3 — visible only after provider selected */}
                {provider && selectedProvider && (
                    <>
                        {/* Step 2 — Send money */}
                        <div
                            className={`rounded-2xl border ${selectedProvider.border} ${selectedProvider.bg} space-y-4 p-6`}
                        >
                            <div className="flex items-center gap-2">
                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-pink-600 text-xs font-bold text-white">
                                    2
                                </span>
                                <h2 className="text-sm font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                    Send Money via {selectedProvider.label}
                                </h2>
                            </div>

                            <div
                                className={`rounded-xl ${selectedProvider.bg} border ${selectedProvider.border} p-4 text-center`}
                            >
                                <p
                                    className={`mb-1 text-xs font-semibold tracking-wide uppercase ${selectedProvider.color}`}
                                >
                                    Amount to Send
                                </p>
                                <p
                                    className={`text-3xl font-bold ${selectedProvider.color}`}
                                >
                                    {formatCurrency(amount)}
                                </p>
                            </div>

                            {paymentNumber ? (
                                <div className="flex items-center justify-between gap-3 rounded-xl border border-[var(--color-border)] bg-white p-4">
                                    <div>
                                        <p className="mb-0.5 text-xs font-semibold tracking-wide text-[var(--color-text-muted)] uppercase">
                                            {selectedProvider.numberLabel}
                                        </p>
                                        <p className="font-mono text-xl font-bold text-[var(--color-text-primary)]">
                                            {paymentNumber}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={copyNumber}
                                        className="flex items-center gap-1.5 rounded-lg border border-[var(--color-border)] px-3 py-2 text-xs font-semibold text-[var(--color-text-secondary)] transition-all hover:bg-slate-50"
                                    >
                                        {copied ? (
                                            <CheckCircle2
                                                size={13}
                                                className="text-green-600"
                                            />
                                        ) : (
                                            <Copy size={13} />
                                        )}
                                        {copied ? 'Copied' : 'Copy'}
                                    </button>
                                </div>
                            ) : (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                                    {selectedProvider.label} number not
                                    configured. Please contact support.
                                </div>
                            )}

                            <p className="text-xs text-[var(--color-text-muted)]">
                                Use <strong>Send Money</strong> in your{' '}
                                {selectedProvider.label} app. You'll receive a
                                confirmation SMS with a{' '}
                                <strong>Transaction ID</strong>.
                            </p>
                        </div>

                        {/* Step 3 — Enter TxnID */}
                        <div className="space-y-4 rounded-2xl border border-[var(--color-border)] bg-white p-6">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <span className="flex h-6 w-6 items-center justify-center rounded-full bg-pink-600 text-xs font-bold text-white">
                                        3
                                    </span>
                                    <h2 className="text-sm font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                        Enter Transaction ID
                                    </h2>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setProvider(null)}
                                    className="flex items-center gap-1 text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]"
                                >
                                    <ChevronLeft size={13} />
                                    Change
                                </button>
                            </div>

                            <p className="text-sm text-[var(--color-text-secondary)]">
                                Copy the{' '}
                                <strong>{selectedProvider.txnLabel}</strong>{' '}
                                from the {selectedProvider.label} confirmation
                                SMS.
                            </p>

                            <form onSubmit={handleSubmit} className="space-y-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="transaction_id">
                                        {selectedProvider.txnLabel}
                                    </Label>
                                    <Input
                                        id="transaction_id"
                                        value={transactionId}
                                        onChange={(e) =>
                                            setTransactionId(
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder={
                                            selectedProvider.txnPlaceholder
                                        }
                                        className="h-12 rounded-xl font-mono text-base uppercase"
                                        autoComplete="off"
                                        required
                                    />
                                    {errors?.transaction_id && (
                                        <p className="flex items-center gap-1.5 text-xs text-red-500">
                                            <AlertCircle size={12} />
                                            {errors.transaction_id}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={
                                        submitting || !transactionId.trim()
                                    }
                                    size="lg"
                                    className="h-12 w-full rounded-xl font-bold"
                                >
                                    {submitting
                                        ? 'Verifying…'
                                        : `Confirm ${selectedProvider.label} Payment`}
                                </Button>
                            </form>
                        </div>
                    </>
                )}

                <p className="text-center text-xs text-[var(--color-text-muted)]">
                    Having trouble? Contact support with Order ID:{' '}
                    <strong>{orderNumber}</strong>
                </p>
            </div>
        </CheckoutLayout>
    );
}
