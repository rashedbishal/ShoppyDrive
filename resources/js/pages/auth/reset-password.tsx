import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, KeyRound, Lock } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordRequirements from '@/components/password-requirements';
import { SiteLogo } from '@/components/shared/SiteLogo';
import { passwordMismatchMessage, passwordsMatch } from '@/lib/password-match';
import type { SharedPageProps } from '@/types';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}): React.JSX.Element {
    const { password_requirements: passwordRequirements } =
        usePage<SharedPageProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const strength =
        data.password.length === 0
            ? 0
            : data.password.length < 6
              ? 1
              : data.password.length < 10
                ? 2
                : 3;
    const strengthColors = [
        'bg-transparent',
        'bg-red-400',
        'bg-amber-400',
        'bg-green-500',
    ];
    const strengthLabels = ['', 'Weak', 'Fair', 'Strong'];

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <Head title="Reset Password — ShoppyDrive" />

            <div className="w-full max-w-[420px]">
                <div className="mb-8">
                    <Link href="/" className="inline-flex items-center">
                        <SiteLogo className="h-11" />
                    </Link>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                    <div className="mb-6 flex size-12 items-center justify-center rounded-2xl bg-red-50">
                        <KeyRound className="size-6 text-[#c8102e]" />
                    </div>

                    <div className="mb-6 space-y-1.5">
                        <h1 className="text-2xl font-bold text-gray-900">
                            Create new password
                        </h1>
                        <p className="text-sm text-gray-500">
                            Setting a new password for{' '}
                            <span className="font-medium text-gray-700">
                                {email}
                            </span>
                        </p>
                    </div>

                    {errors.email && (
                        <div
                            role="alert"
                            className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                        >
                            This reset link is invalid or has expired.{' '}
                            <Link
                                href={route('password.request')}
                                className="font-semibold underline"
                            >
                                Request a new link
                            </Link>
                        </div>
                    )}

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();

                            if (
                                !passwordsMatch(
                                    data.password,
                                    data.password_confirmation,
                                )
                            ) {
                                document
                                    .getElementById('password_confirmation')
                                    ?.focus();

                                return;
                            }

                            post(route('password.update'));
                        }}
                        className="space-y-4"
                    >
                        <div className="space-y-1.5">
                            <label
                                htmlFor="password"
                                className="text-sm font-medium text-gray-700"
                            >
                                New password
                            </label>
                            <div className="relative">
                                <Lock className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    maxLength={128}
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    placeholder="Create a password"
                                    className="h-11 w-full rounded-xl border border-gray-200 bg-white pr-12 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-[#c8102e] focus:ring-2 focus:ring-[#c8102e]/20 focus:outline-none"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowPassword(!showPassword)
                                    }
                                    className="absolute top-1/2 right-3.5 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    {showPassword ? (
                                        <EyeOff className="size-4" />
                                    ) : (
                                        <Eye className="size-4" />
                                    )}
                                </button>
                            </div>
                            <PasswordRequirements
                                requirements={passwordRequirements}
                            />
                            {data.password.length > 0 && (
                                <div className="space-y-1">
                                    <div className="flex gap-1">
                                        {[1, 2, 3].map((i) => (
                                            <div
                                                key={i}
                                                className={`h-1.5 flex-1 rounded-full transition-all ${i <= strength ? strengthColors[strength] : 'bg-gray-200'}`}
                                            />
                                        ))}
                                    </div>
                                    <p className="text-xs text-gray-500">
                                        Strength:{' '}
                                        <span
                                            className={`font-medium ${strength === 3 ? 'text-green-600' : strength === 2 ? 'text-amber-500' : 'text-red-500'}`}
                                        >
                                            {strengthLabels[strength]}
                                        </span>
                                    </p>
                                </div>
                            )}
                            <InputError message={errors.password} />
                        </div>

                        <div className="space-y-1.5">
                            <label
                                htmlFor="password_confirmation"
                                className="text-sm font-medium text-gray-700"
                            >
                                Confirm password
                            </label>
                            <div className="relative">
                                <Lock className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    maxLength={128}
                                    id="password_confirmation"
                                    type={showConfirm ? 'text' : 'password'}
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Repeat password"
                                    className="h-11 w-full rounded-xl border border-gray-200 bg-white pr-12 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-[#c8102e] focus:ring-2 focus:ring-[#c8102e]/20 focus:outline-none"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowConfirm(!showConfirm)}
                                    className="absolute top-1/2 right-3.5 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    {showConfirm ? (
                                        <EyeOff className="size-4" />
                                    ) : (
                                        <Eye className="size-4" />
                                    )}
                                </button>
                            </div>
                            <InputError
                                message={
                                    passwordMismatchMessage(
                                        data.password,
                                        data.password_confirmation,
                                    ) ?? errors.password_confirmation
                                }
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#c8102e] text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-60"
                        >
                            {processing ? (
                                <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            ) : (
                                <>
                                    <span>Reset Password</span>
                                    <ArrowRight className="size-4" />
                                </>
                            )}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
