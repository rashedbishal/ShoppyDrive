import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, KeyRound, ShieldCheck, Smartphone } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';

export default function TwoFactorChallenge(): React.JSX.Element {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ code: '', recovery_code: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('two-factor.login'), { onFinish: () => reset('code', 'recovery_code') });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <Head title="Two-Factor Authentication — ShoppyDrive" />

            <div className="w-full max-w-[420px]">
                <div className="mb-8">
                    <Link href="/" className="inline-flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-[#c8102e]">
                            <AppLogoIcon className="size-4 fill-current text-white" />
                        </span>
                        <span className="text-lg font-bold text-gray-900">ShoppyDrive</span>
                    </Link>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                    <div className="mb-6 flex size-12 items-center justify-center rounded-2xl bg-red-50">
                        {useRecovery ? <KeyRound className="size-6 text-[#c8102e]" /> : <Smartphone className="size-6 text-[#c8102e]" />}
                    </div>

                    <div className="mb-5 space-y-1.5">
                        <h1 className="text-2xl font-bold text-gray-900">Two-factor verification</h1>
                        <p className="text-sm leading-6 text-gray-500">
                            {useRecovery
                                ? 'Enter one of your saved recovery codes to regain access.'
                                : 'Enter the 6-digit code from your authenticator app.'}
                        </p>
                    </div>

                    <div className="mb-5 flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                        <ShieldCheck className="mt-0.5 size-4 shrink-0 text-blue-600" />
                        <p className="text-xs leading-5 text-blue-700">Two-factor authentication protects your account from unauthorized access.</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        {useRecovery ? (
                            <div className="space-y-1.5">
                                <label htmlFor="recovery_code" className="text-sm font-medium text-gray-700">Recovery code</label>
                                <div className="relative">
                                    <KeyRound className="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                                    <input id="recovery_code" type="text" autoComplete="one-time-code" value={data.recovery_code} onChange={e => setData('recovery_code', e.target.value)} placeholder="xxxx-xxxx-xxxx" className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 font-mono text-sm text-gray-900 placeholder:text-gray-400 transition focus:border-[#c8102e] focus:outline-none focus:ring-2 focus:ring-[#c8102e]/20" />
                                </div>
                                <InputError message={errors.recovery_code} />
                            </div>
                        ) : (
                            <div className="space-y-1.5">
                                <label htmlFor="code" className="text-sm font-medium text-gray-700">Authentication code</label>
                                <input id="code" type="text" inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={data.code} onChange={e => setData('code', e.target.value.replace(/\D/g, ''))} placeholder="000000" className="h-14 w-full rounded-xl border border-gray-200 bg-gray-50 text-center text-2xl font-mono tracking-[0.5em] text-gray-900 placeholder:tracking-normal placeholder:text-base placeholder:text-gray-400 transition focus:border-[#c8102e] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#c8102e]/20" />
                                <InputError message={errors.code} />
                            </div>
                        )}

                        <button type="submit" disabled={processing} className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#c8102e] text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-60">
                            {processing ? <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" /> : <><span>Verify & Sign In</span><ArrowRight className="size-4" /></>}
                        </button>
                    </form>

                    <button type="button" onClick={() => setUseRecovery(!useRecovery)} className="mt-4 w-full text-center text-sm text-gray-500 transition hover:text-gray-800">
                        {useRecovery ? '← Use authenticator code instead' : 'Lost your device? Use a recovery code →'}
                    </button>
                </div>
            </div>
        </div>
    );
}
