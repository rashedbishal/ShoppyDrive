import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle,
    Lock,
    Mail,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';

export default function ForgotPassword({
    status,
    resendAvailableAt,
}: {
    status?: string;
    resendAvailableAt?: string | null;
}): React.JSX.Element {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    // A tester could spam this button with no feedback before the real
    // server-side limiter eventually refused the request — this mirrors
    // the email-verification resend cooldown's own pattern, just backed
    // by the session (StampPasswordResetRequestedAt) instead of a user
    // column, since a forgot-password request has no account to persist
    // against.
    const targetMs = useMemo(
        () =>
            resendAvailableAt ? new Date(resendAvailableAt).getTime() : null,
        [resendAvailableAt],
    );
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!targetMs) {
            return;
        }

        const interval = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(interval);
    }, [targetMs]);

    const secondsLeft = targetMs
        ? Math.max(0, Math.ceil((targetMs - now) / 1000))
        : 0;

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <Head title="Forgot Password — ShoppyDrive" />

            <div className="w-full max-w-[420px]">
                <div className="mb-8 flex items-center justify-between">
                    <Link href="/" className="inline-flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-[#c8102e]">
                            <AppLogoIcon className="size-4 fill-current text-white" />
                        </span>
                        <span className="text-lg font-bold text-gray-900">
                            ShoppyDrive
                        </span>
                    </Link>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                    <div className="mb-6 flex size-12 items-center justify-center rounded-2xl bg-red-50">
                        <Lock className="size-6 text-[#c8102e]" />
                    </div>

                    <div className="mb-6 space-y-1.5">
                        <h1 className="text-2xl font-bold text-gray-900">
                            Reset your password
                        </h1>
                        <p className="text-sm leading-6 text-gray-500">
                            Enter your email and we'll send you a secure reset
                            link.
                        </p>
                    </div>

                    {status && (
                        <div className="mb-5 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3">
                            <CheckCircle className="mt-0.5 size-4 shrink-0 text-green-600" />
                            <p className="text-sm font-medium text-green-700">
                                {status}
                            </p>
                        </div>
                    )}

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('password.email'));
                        }}
                        className="space-y-4"
                    >
                        <div className="space-y-1.5">
                            <label
                                htmlFor="email"
                                className="text-sm font-medium text-gray-700"
                            >
                                Email address
                            </label>
                            <div className="relative">
                                <Mail className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="you@example.com"
                                    className="h-11 w-full rounded-xl border border-gray-200 bg-white pr-4 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-[#c8102e] focus:ring-2 focus:ring-[#c8102e]/20 focus:outline-none"
                                    required
                                />
                            </div>
                            <InputError message={errors.email} />
                        </div>

                        <button
                            type="submit"
                            disabled={processing || secondsLeft > 0}
                            className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#c8102e] text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-60"
                        >
                            {processing ? (
                                <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            ) : secondsLeft > 0 ? (
                                <span>Try again in {secondsLeft}s</span>
                            ) : (
                                <>
                                    <span>Send Reset Link</span>
                                    <ArrowRight className="size-4" />
                                </>
                            )}
                        </button>
                    </form>

                    <div className="mt-5 flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <div className="flex items-center gap-2 text-xs text-gray-500">
                            <ShieldCheck className="size-3.5 text-[#c8102e]" />
                            256-bit encrypted
                        </div>
                        <div className="h-4 w-px bg-gray-200" />
                        <p className="text-xs text-gray-500">
                            Link expires in 60 min
                        </p>
                    </div>
                </div>

                <Link
                    href={route('login')}
                    className="mt-5 flex items-center justify-center gap-2 text-sm text-gray-500 transition hover:text-gray-800"
                >
                    <ArrowLeft className="size-4" /> Back to sign in
                </Link>
            </div>
        </div>
    );
}
