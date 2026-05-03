import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle, Mail, RefreshCw } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function VerifyEmail({ status }: { status?: string }): React.JSX.Element {
    const { post, processing } = useForm({});

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <Head title="Verify Email — ShoppyDrive" />

            <div className="w-full max-w-[440px]">
                <div className="mb-8 flex justify-center">
                    <Link href="/" className="inline-flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-[#c8102e]">
                            <AppLogoIcon className="size-4 fill-current text-white" />
                        </span>
                        <span className="text-lg font-bold text-gray-900">ShoppyDrive</span>
                    </Link>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
                    <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-red-50">
                        <Mail className="size-8 text-[#c8102e]" />
                    </div>

                    <h1 className="mb-2 text-2xl font-bold text-gray-900">Check your inbox</h1>
                    <p className="mb-6 text-sm leading-6 text-gray-500">
                        We sent a verification link to your email. Click it to activate your ShoppyDrive account.
                    </p>

                    {status === 'verification-link-sent' && (
                        <div className="mb-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-left">
                            <CheckCircle className="size-4 shrink-0 text-green-600" />
                            <p className="text-sm font-medium text-green-700">New verification link sent to your email.</p>
                        </div>
                    )}

                    {/* Steps */}
                    <div className="mb-6 rounded-xl border border-gray-100 bg-gray-50 p-4 text-left space-y-3">
                        {[
                            '1. Check your inbox for an email from ShoppyDrive.',
                            '2. Click the verification link inside.',
                            "3. You're verified — start shopping!",
                        ].map((s) => (
                            <p key={s} className="text-sm text-gray-600">{s}</p>
                        ))}
                    </div>

                    <button
                        onClick={() => post(route('verification.send'))}
                        disabled={processing}
                        className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#c8102e] text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-60"
                    >
                        {processing ? <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" /> : <><RefreshCw className="size-4" /><span>Resend Verification Email</span></>}
                    </button>

                    <p className="mt-4 text-xs text-gray-400">Check spam/junk if you don't see it.</p>

                    <Link href={route('logout')} method="post" as="button" className="mt-4 block text-sm text-gray-500 transition hover:text-gray-800">
                        Sign out and use a different account
                    </Link>
                </div>
            </div>
        </div>
    );
}
