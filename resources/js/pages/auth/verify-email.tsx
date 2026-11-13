import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle, Mail, RefreshCw } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import EmailVerificationCodeController from '@/actions/App/Http/Controllers/Auth/EmailVerificationCodeController';
import { SiteLogo } from '@/components/shared/SiteLogo';

type Props = {
    status?: string;
    resendAvailableAt?: string | null;
};

export default function VerifyEmail({
    status,
    resendAvailableAt,
}: Props): React.JSX.Element {
    const { post: resend, processing: resending } = useForm({});
    const codeInput = useRef<HTMLInputElement>(null);

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

    const {
        data,
        setData,
        post: submitCode,
        processing: verifying,
        errors,
    } = useForm({ code: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        submitCode(EmailVerificationCodeController.store.url(), {
            onError: () => codeInput.current?.focus(),
        });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <Head title="Verify Email — ShoppyDrive" />

            <div className="w-full max-w-[440px]">
                <div className="mb-8 flex justify-center">
                    <Link href="/" className="inline-flex items-center">
                        <SiteLogo className="h-11" />
                    </Link>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
                    <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-red-50">
                        <Mail className="size-8 text-[#c8102e]" />
                    </div>

                    <h1 className="mb-2 text-2xl font-bold text-gray-900">
                        Check your inbox
                    </h1>
                    <p className="mb-6 text-sm leading-6 text-gray-600">
                        We sent a 6-digit verification code to your email. Enter
                        it below to activate your ShoppyDrive account.
                    </p>

                    {status === 'verification-link-sent' && (
                        <div className="mb-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-left">
                            <CheckCircle className="size-4 shrink-0 text-green-600" />
                            <p className="text-sm font-medium text-green-700">
                                A new code has been sent to your email.
                            </p>
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="mb-6 space-y-3 text-left"
                    >
                        <label
                            htmlFor="code"
                            className="text-sm font-medium text-gray-700"
                        >
                            Verification code
                        </label>
                        <input
                            id="code"
                            ref={codeInput}
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={data.code}
                            onChange={(e) =>
                                setData(
                                    'code',
                                    e.target.value.replace(/\D/g, ''),
                                )
                            }
                            placeholder="123456"
                            className="h-12 w-full rounded-xl border border-gray-200 text-center text-xl font-semibold tracking-[0.3em] text-gray-900 focus:border-[#c8102e] focus:outline-none"
                        />
                        {errors.code && (
                            <p className="text-sm text-red-600">
                                {errors.code}
                            </p>
                        )}

                        <button
                            type="submit"
                            disabled={verifying || data.code.length !== 6}
                            className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#c8102e] text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-60"
                        >
                            {verifying ? (
                                <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            ) : (
                                'Verify Email'
                            )}
                        </button>
                    </form>

                    <button
                        onClick={() => resend(route('verification.send'))}
                        disabled={resending || secondsLeft > 0}
                        className="flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-60"
                    >
                        {resending ? (
                            <span className="size-4 animate-spin rounded-full border-2 border-gray-400 border-t-transparent" />
                        ) : secondsLeft > 0 ? (
                            <span>Resend available in {secondsLeft}s</span>
                        ) : (
                            <>
                                <RefreshCw className="size-4" />
                                <span>Resend Code</span>
                            </>
                        )}
                    </button>

                    <p className="mt-4 text-xs text-gray-600">
                        Check spam/junk if you don't see it.
                    </p>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="mt-4 block text-sm text-gray-600 transition hover:text-gray-800"
                    >
                        Sign out and use a different account
                    </Link>
                </div>
            </div>
        </div>
    );
}
