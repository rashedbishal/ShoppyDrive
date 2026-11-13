import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, Lock, Shield } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SiteLogo } from '@/components/shared/SiteLogo';

export default function ConfirmPassword(): React.JSX.Element {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <Head title="Confirm Password — ShoppyDrive" />

            <div className="w-full max-w-[420px]">
                <div className="mb-8">
                    <Link href="/" className="inline-flex items-center">
                        <SiteLogo className="h-11" />
                    </Link>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                    <div className="mb-6 flex size-12 items-center justify-center rounded-2xl bg-red-50">
                        <Shield className="size-6 text-[#c8102e]" />
                    </div>

                    <div className="mb-5 space-y-1.5">
                        <h1 className="text-2xl font-bold text-gray-900">
                            Secure area
                        </h1>
                        <p className="text-sm leading-6 text-gray-600">
                            Please confirm your password to access this section.
                        </p>
                    </div>

                    <div className="mb-5 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                        For your security, we verify your identity before
                        sensitive actions.
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('password.confirm'), {
                                onFinish: () => reset('password'),
                            });
                        }}
                        className="space-y-4"
                    >
                        <div className="space-y-1.5">
                            <label
                                htmlFor="password"
                                className="text-sm font-medium text-gray-700"
                            >
                                Current password
                            </label>
                            <div className="relative">
                                <Lock className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    maxLength={128}
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    placeholder="••••••••"
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
                            <InputError message={errors.password} />
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
                                    <span>Confirm & Continue</span>
                                    <ArrowRight className="size-4" />
                                </>
                            )}
                        </button>
                    </form>

                    <Link
                        href={route('password.request')}
                        className="mt-4 block text-center text-sm text-gray-600 transition hover:text-gray-800"
                    >
                        Forgot your password?
                    </Link>
                </div>
            </div>
        </div>
    );
}
