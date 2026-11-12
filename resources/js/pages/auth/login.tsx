import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Eye,
    EyeOff,
    Lock,
    Mail,
    ShoppingBag,
    Star,
    Truck,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import type { SharedPageProps } from '@/types';

/**
 * BIZ-032: the delivery line reads the real Admin free-delivery threshold
 * instead of a hard-coded amount.
 */
function featuresFor(settings: SharedPageProps['settings']) {
    const threshold = Number(settings.free_delivery_threshold ?? 0);

    return [
        { icon: ShoppingBag, text: '1,000+ premium products' },
        {
            icon: Truck,
            text:
                threshold > 0
                    ? `Free delivery over ${settings.currency_symbol}${Math.round(threshold).toLocaleString('en-US')}`
                    : 'Delivery to all 64 districts',
        },
        { icon: Zap, text: 'Lightning fast checkout' },
        { icon: Star, text: 'Verified buyer reviews' },
    ];
}

export default function Login(): React.JSX.Element {
    const { settings } = usePage<SharedPageProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <div className="flex min-h-dvh bg-white">
            <Head title="Sign In — ShoppyDrive" />

            {/* ── Left brand panel ── */}
            <div className="relative hidden w-[46%] overflow-hidden bg-[#c8102e] lg:flex lg:flex-col lg:justify-between">
                {/* Subtle pattern */}
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.06]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,0.9) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.9) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />
                {/* Glow orbs */}
                <div className="pointer-events-none absolute -top-20 -right-20 h-80 w-80 rounded-full bg-white opacity-[0.07] blur-3xl" />
                <div className="pointer-events-none absolute -bottom-10 -left-10 h-72 w-72 rounded-full bg-white opacity-[0.05] blur-3xl" />

                {/* Logo */}
                <div className="relative z-10 p-10">
                    <Link href="/" className="inline-flex items-center gap-3">
                        {settings.logo ? (
                            <img
                                src={settings.logo}
                                alt={settings.site_name}
                                className="h-10 w-auto object-contain"
                            />
                        ) : (
                            <span className="flex size-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                                <AppLogoIcon className="size-5 fill-current text-white" />
                            </span>
                        )}
                        <span className="text-xl font-bold text-white">
                            {settings.site_name}
                        </span>
                    </Link>
                </div>

                {/* Content */}
                <div className="relative z-10 space-y-8 p-10 pb-16">
                    <div className="space-y-4">
                        <div className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white">
                            <Zap className="size-3.5" />
                            Bangladesh's #1 Shopping Destination
                        </div>
                        <h2 className="text-4xl leading-tight font-bold text-white">
                            Shop smarter.
                            <br />
                            <span className="text-white/70">Save bigger.</span>
                        </h2>
                        <p className="max-w-sm text-sm leading-7 text-white/70">
                            Join thousands of happy shoppers enjoying exclusive
                            deals, lightning-fast delivery, and a seamless
                            experience.
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        {featuresFor(settings).map(({ icon: Icon, text }) => (
                            <div
                                key={text}
                                className="flex items-center gap-2.5 rounded-xl border border-white/15 bg-white/10 px-4 py-3"
                            >
                                <Icon className="size-4 shrink-0 text-white" />
                                <span className="text-[13px] text-white/80">
                                    {text}
                                </span>
                            </div>
                        ))}
                    </div>

                    {/* Testimonial */}
                    <div className="rounded-2xl border border-white/15 bg-white/10 p-5">
                        <div className="flex items-center gap-1 text-yellow-300">
                            {[...Array(5)].map((_, i) => (
                                <Star
                                    key={i}
                                    className="size-3.5 fill-current"
                                />
                            ))}
                        </div>
                        <p className="mt-2 text-sm text-white/75 italic">
                            "Best shopping experience in Bangladesh. Fast
                            delivery and great support!"
                        </p>
                        <p className="mt-2 text-xs font-semibold text-white/50">
                            — Rafi A., Dhaka
                        </p>
                    </div>
                </div>
            </div>

            {/* ── Right form panel ── */}
            <div className="flex min-h-dvh flex-1 flex-col items-center justify-center bg-gray-50 px-4 py-3 sm:px-6 sm:py-5 lg:px-8">
                {/* Mobile logo */}
                <div className="mb-3 lg:hidden">
                    <Link href="/" className="inline-flex items-center gap-3">
                        {settings.logo ? (
                            <img
                                src={settings.logo}
                                alt={settings.site_name}
                                className="h-10 w-auto object-contain"
                            />
                        ) : (
                            <span className="flex size-10 items-center justify-center rounded-xl bg-[#c8102e]">
                                <AppLogoIcon className="size-5 fill-current text-white" />
                            </span>
                        )}
                        <span className="text-xl font-bold text-gray-900">
                            {settings.site_name}
                        </span>
                    </Link>
                </div>

                <div className="w-full max-w-[420px]">
                    {/* Card */}
                    <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="mb-4 space-y-0.5">
                            <h1 className="text-xl font-bold text-gray-900 sm:text-2xl">
                                Welcome back
                            </h1>
                            <p className="text-sm text-gray-500">
                                Sign in to your ShoppyDrive account
                            </p>
                        </div>

                        {/* Social buttons */}
                        {(settings.google_login_enabled ||
                            settings.facebook_login_enabled) && (
                            <div
                                className={`mb-3 grid gap-2 ${settings.google_login_enabled && settings.facebook_login_enabled ? 'grid-cols-2' : 'grid-cols-1'}`}
                            >
                                {settings.google_login_enabled && (
                                    <a
                                        href={route('social.redirect', {
                                            provider: 'google',
                                        })}
                                        className="flex h-9 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50"
                                    >
                                        <svg
                                            className="size-4"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                fill="#4285F4"
                                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                            />
                                            <path
                                                fill="#34A853"
                                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                            />
                                            <path
                                                fill="#FBBC05"
                                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                            />
                                            <path
                                                fill="#EA4335"
                                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                            />
                                        </svg>
                                        Google
                                    </a>
                                )}
                                {settings.facebook_login_enabled && (
                                    <a
                                        href={route('social.redirect', {
                                            provider: 'facebook',
                                        })}
                                        className="flex h-9 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50"
                                    >
                                        <svg
                                            className="size-4"
                                            fill="#1877F2"
                                            viewBox="0 0 24 24"
                                        >
                                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                        </svg>
                                        Facebook
                                    </a>
                                )}
                            </div>
                        )}

                        <div className="mb-4 flex items-center gap-3">
                            <div className="h-px flex-1 bg-gray-200" />
                            <span className="text-xs text-gray-400">
                                or sign in with email
                            </span>
                            <div className="h-px flex-1 bg-gray-200" />
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-3">
                            <div className="space-y-1">
                                <label
                                    htmlFor="email"
                                    className="text-xs font-medium text-gray-700"
                                >
                                    Email address
                                </label>
                                <div className="relative">
                                    <Mail className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        maxLength={150}
                                        id="email"
                                        type="email"
                                        autoComplete="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        placeholder="you@example.com"
                                        className="h-10 w-full rounded-lg border border-gray-200 bg-white pr-4 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-[#c8102e] focus:ring-2 focus:ring-[#c8102e]/20 focus:outline-none"
                                        required
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            <div className="space-y-1">
                                <div className="flex items-center justify-between">
                                    <label
                                        htmlFor="password"
                                        className="text-xs font-medium text-gray-700"
                                    >
                                        Password
                                    </label>
                                    <Link
                                        href={route('password.request')}
                                        className="text-xs font-medium text-[#c8102e] hover:underline"
                                    >
                                        Forgot password?
                                    </Link>
                                </div>
                                <div className="relative">
                                    <Lock className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        maxLength={128}
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
                                        className="h-10 w-full rounded-lg border border-gray-200 bg-white pr-12 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-[#c8102e] focus:ring-2 focus:ring-[#c8102e]/20 focus:outline-none"
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

                            <label className="flex cursor-pointer items-center gap-2.5">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) =>
                                        setData('remember', e.target.checked)
                                    }
                                    className="size-4 rounded border-gray-300 accent-[#c8102e]"
                                />
                                <span className="text-sm text-gray-600">
                                    Remember me for 30 days
                                </span>
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-[#c8102e] text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? (
                                    <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                ) : (
                                    <>
                                        <span>Sign In</span>
                                        <ArrowRight className="size-4" />
                                    </>
                                )}
                            </button>
                        </form>
                    </div>

                    <p className="mt-3 text-center text-sm text-gray-500">
                        New to ShoppyDrive?{' '}
                        <Link
                            href={route('register')}
                            className="font-semibold text-[#c8102e] hover:underline"
                        >
                            Create a free account
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
