import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, Lock, Mail, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SiteLogo } from '@/components/shared/SiteLogo';

export default function AdminLogin(): React.JSX.Element {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.login.submit'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="flex min-h-screen bg-gray-950">
            <Head title="Admin Login — ShoppyDrive" />

            {/* Left decorative panel */}
            <div className="relative hidden w-[44%] overflow-hidden bg-gradient-to-br from-gray-900 via-gray-950 to-black lg:flex lg:flex-col lg:justify-between">
                <div className="pointer-events-none absolute top-20 -left-10 h-72 w-72 rounded-full bg-[#c8102e] opacity-[0.07] blur-3xl" />
                <div className="pointer-events-none absolute right-0 bottom-20 h-56 w-56 rounded-full bg-[#c8102e] opacity-[0.05] blur-3xl" />
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.04]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px)',
                        backgroundSize: '40px 40px',
                    }}
                />

                <div className="relative z-10 p-10">
                    <div className="flex flex-col items-start gap-1.5">
                        <SiteLogo
                            plate="always"
                            tone="onDark"
                            className="h-11"
                        />
                        <p className="text-xs text-gray-500">Admin Panel</p>
                    </div>
                </div>

                <div className="relative z-10 space-y-6 p-10 pb-16">
                    <div className="space-y-3">
                        <div className="inline-flex items-center gap-2 rounded-full border border-[#c8102e]/20 bg-[#c8102e]/10 px-3 py-1.5 text-xs font-semibold text-[#c8102e]">
                            <ShieldCheck className="size-3.5" />
                            Restricted Access
                        </div>
                        <h2 className="text-3xl leading-tight font-bold text-white">
                            Admin Dashboard
                        </h2>
                        <p className="max-w-sm text-sm leading-7 text-gray-500">
                            Manage products, orders, customers, and store
                            settings from one place.
                        </p>
                    </div>

                    {[
                        'Manage orders & inventory',
                        'View sales reports',
                        'Manage customers & reviews',
                        'Configure store settings',
                    ].map((item) => (
                        <div key={item} className="flex items-center gap-3">
                            <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-[#c8102e]/15">
                                <span className="size-1.5 rounded-full bg-[#c8102e]" />
                            </span>
                            <span className="text-sm text-gray-400">
                                {item}
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Right form panel */}
            <div className="flex flex-1 flex-col items-center justify-center bg-gray-950 px-4 py-12 sm:px-8">
                <div className="w-full max-w-[400px]">
                    {/* Mobile logo */}
                    <div className="mb-8 flex flex-col items-start gap-1.5 lg:hidden">
                        <SiteLogo
                            plate="always"
                            tone="onDark"
                            className="h-10"
                        />
                        <p className="text-xs text-gray-600">Admin Panel</p>
                    </div>

                    <div className="rounded-2xl border border-gray-800 bg-gray-900 p-8">
                        <div className="mb-7 space-y-1">
                            <h1 className="text-xl font-bold text-white">
                                Admin Sign In
                            </h1>
                            <p className="text-sm text-gray-500">
                                Authorised personnel only.
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="email"
                                    className="text-sm font-medium text-gray-300"
                                >
                                    Email address
                                </label>
                                <div className="relative">
                                    <Mail className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-600" />
                                    <input
                                        id="email"
                                        type="email"
                                        autoComplete="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        placeholder="admin@example.com"
                                        className="h-11 w-full rounded-xl border border-gray-700 bg-gray-800 pr-4 pl-10 text-sm text-white transition placeholder:text-gray-600 focus:border-[#c8102e] focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                        required
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            <div className="space-y-1.5">
                                <label
                                    htmlFor="password"
                                    className="text-sm font-medium text-gray-300"
                                >
                                    Password
                                </label>
                                <div className="relative">
                                    <Lock className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-600" />
                                    <input
                                        id="password"
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        autoComplete="current-password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        placeholder="••••••••"
                                        className="h-11 w-full rounded-xl border border-gray-700 bg-gray-800 pr-12 pl-10 text-sm text-white transition placeholder:text-gray-600 focus:border-[#c8102e] focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword(!showPassword)
                                        }
                                        className="absolute top-1/2 right-3.5 -translate-y-1/2 text-gray-600 hover:text-gray-400"
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

                            <label className="flex cursor-pointer items-center gap-2.5">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) =>
                                        setData('remember', e.target.checked)
                                    }
                                    className="size-4 rounded border-gray-700 accent-[#c8102e]"
                                />
                                <span className="text-sm text-gray-400">
                                    Keep me signed in
                                </span>
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#c8102e] text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-60"
                            >
                                {processing ? (
                                    <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                ) : (
                                    <>
                                        <span>Sign In to Admin</span>
                                        <ArrowRight className="size-4" />
                                    </>
                                )}
                            </button>
                        </form>
                    </div>

                    <p className="mt-5 text-center text-xs text-gray-700">
                        Not an admin?{' '}
                        <a
                            href="/"
                            className="text-gray-500 transition hover:text-gray-300"
                        >
                            Go to storefront →
                        </a>
                    </p>
                </div>
            </div>
        </div>
    );
}
