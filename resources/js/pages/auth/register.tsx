import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Eye,
    EyeOff,
    Gift,
    Lock,
    Mail,
    Phone,
    ShieldCheck,
    User,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordRequirements from '@/components/password-requirements';
import { SiteLogo } from '@/components/shared/SiteLogo';
import { passwordMismatchMessage, passwordsMatch } from '@/lib/password-match';
import type { SharedPageProps } from '@/types';

export default function Register({
    referralCode,
    referralCodeValid,
}: {
    referralCode?: string | null;
    referralCodeValid?: boolean | null;
}): React.JSX.Element {
    const { settings, password_requirements: passwordRequirements } =
        usePage<SharedPageProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    const confirmationError = passwordMismatchMessage(
        data.password,
        data.password_confirmation,
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!passwordsMatch(data.password, data.password_confirmation)) {
            document.getElementById('password_confirmation')?.focus();

            return;
        }

        post(route('register'));
    };

    return (
        <div className="flex min-h-dvh bg-white">
            <Head title="Create Account — ShoppyDrive" />

            {/* ── Left brand panel ── */}
            <div className="relative hidden w-[42%] overflow-hidden bg-brand lg:flex lg:flex-col lg:justify-between">
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.06]"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle, rgba(255,255,255,0.9) 1px, transparent 1px)',
                        backgroundSize: '32px 32px',
                    }}
                />
                <div className="pointer-events-none absolute top-10 -right-10 h-72 w-72 rounded-full bg-white opacity-[0.06] blur-3xl" />
                <div className="pointer-events-none absolute bottom-20 -left-10 h-56 w-56 rounded-full bg-white opacity-[0.05] blur-3xl" />

                {/* Logo */}
                <div className="relative z-10 p-7 xl:p-10">
                    <Link href="/" className="inline-flex items-center">
                        <SiteLogo
                            plate="always"
                            tone="onDark"
                            className="h-11"
                        />
                    </Link>
                </div>

                {/* Content */}
                <div className="relative z-10 space-y-5 p-7 pb-8 xl:space-y-8 xl:p-10 xl:pb-12">
                    <div className="space-y-2 xl:space-y-3">
                        <h2 className="text-2xl leading-tight font-bold text-brand-foreground xl:text-3xl">
                            Join millions of
                            <br />
                            <span className="text-brand-foreground/70">
                                happy shoppers
                            </span>
                        </h2>
                        <p className="text-sm leading-6 text-brand-foreground/70 xl:leading-7">
                            Create your free account and start enjoying
                            exclusive deals, fast delivery, and premium
                            products.
                        </p>
                    </div>

                    <div className="space-y-3 xl:space-y-4">
                        {[
                            {
                                n: '01',
                                title: 'Create your account',
                                desc: 'Takes less than 2 minutes',
                            },
                            {
                                n: '02',
                                title: 'Browse 1,000+ products',
                                desc: 'Fashion, electronics, beauty & more',
                            },
                            {
                                n: '03',
                                title: 'Fast nationwide delivery',
                                desc: 'Track your order in real-time',
                            },
                        ].map(({ n, title, desc }) => (
                            <div key={n} className="flex items-start gap-4">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/20 text-sm font-bold text-brand-foreground">
                                    {n}
                                </span>
                                <div>
                                    <p className="text-sm font-semibold text-brand-foreground">
                                        {title}
                                    </p>
                                    <p className="text-xs text-brand-foreground/60">
                                        {desc}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-3">
                        <ShieldCheck className="size-4 text-brand-foreground" />
                        <span className="text-xs font-medium text-brand-foreground/90">
                            100% secure with SSL encryption
                        </span>
                    </div>
                </div>
            </div>

            {/* ── Right form panel ── */}
            <div className="flex min-h-dvh flex-1 flex-col items-center justify-center overflow-y-auto bg-gray-50 px-4 py-3 sm:px-6 sm:py-5 lg:px-8">
                <div className="mb-3 lg:hidden">
                    <Link href="/" className="inline-flex items-center">
                        <SiteLogo className="h-11" />
                    </Link>
                </div>

                <div className="w-full max-w-[560px]">
                    <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="mb-3 space-y-0.5">
                            <h1 className="text-xl font-bold text-gray-900 sm:text-2xl">
                                Create your account
                            </h1>
                            <p className="text-sm text-gray-600">
                                Free forever. No credit card required.
                            </p>
                        </div>

                        {settings.referral_enabled &&
                            referralCode &&
                            referralCodeValid && (
                                <div className="mb-3 flex items-start gap-2 rounded-lg border border-brand/20 bg-brand/5 p-3">
                                    <Gift className="mt-0.5 size-4 shrink-0 text-brand" />
                                    <p className="text-xs text-gray-700">
                                        You were referred! You&apos;ll get{' '}
                                        <span className="font-semibold text-brand">
                                            {settings.referral_discount_type ===
                                            'fixed'
                                                ? `${settings.currency_symbol}${settings.referral_discount_value}`
                                                : `${settings.referral_discount_value}%`}{' '}
                                            off
                                        </span>{' '}
                                        your first order.
                                    </p>
                                </div>
                            )}

                        {settings.referral_enabled &&
                            referralCode &&
                            referralCodeValid === false && (
                                <div className="mb-3 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3">
                                    <Gift className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                    <p className="text-xs text-gray-700">
                                        <span className="font-semibold text-amber-700">
                                            Invalid referral link.
                                        </span>{' '}
                                        This referral link isn&apos;t valid —
                                        you can still create an account without
                                        one.
                                    </p>
                                </div>
                            )}

                        {/* Social */}
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
                                        className="flex h-9 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 transition hover:bg-gray-50"
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
                                        className="flex h-9 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 transition hover:bg-gray-50"
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

                        <div className="mb-3 flex items-center gap-3">
                            <div className="h-px flex-1 bg-gray-200" />
                            <span className="text-xs text-gray-600">
                                or register with email
                            </span>
                            <div className="h-px flex-1 bg-gray-200" />
                        </div>

                        <form
                            onSubmit={handleSubmit}
                            className="grid grid-flow-row-dense grid-cols-2 gap-x-3 gap-y-2.5"
                        >
                            {/* Name */}
                            <div className="space-y-1">
                                <label
                                    htmlFor="name"
                                    className="text-xs font-medium text-gray-700"
                                >
                                    Full name
                                </label>
                                <div className="relative">
                                    <User className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        maxLength={100}
                                        id="name"
                                        type="text"
                                        autoComplete="name"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="Your full name"
                                        className="h-10 w-full rounded-lg border border-gray-200 bg-white pr-3 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none"
                                        required
                                    />
                                </div>
                                <InputError message={errors.name} />
                            </div>

                            {/* Email */}
                            <div className="col-span-2 space-y-1">
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
                                        className="h-10 w-full rounded-lg border border-gray-200 bg-white pr-3 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none"
                                        required
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            {/* Phone */}
                            <div className="space-y-1">
                                <label
                                    htmlFor="phone"
                                    className="text-xs font-medium whitespace-nowrap text-gray-700"
                                >
                                    Phone{' '}
                                    <span className="text-gray-600">
                                        (optional)
                                    </span>
                                </label>
                                <div className="relative">
                                    <Phone className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        maxLength={20}
                                        id="phone"
                                        type="tel"
                                        autoComplete="tel"
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData('phone', e.target.value)
                                        }
                                        placeholder="+880 1XXX"
                                        className="h-10 w-full rounded-lg border border-gray-200 bg-white pr-3 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {/* Password */}
                            <div className="space-y-1">
                                <label
                                    htmlFor="password"
                                    className="text-xs font-medium text-gray-700"
                                >
                                    Password
                                </label>
                                <div className="relative">
                                    <Lock className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        maxLength={128}
                                        id="password"
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        autoComplete="new-password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        placeholder="Create a password"
                                        className="h-10 w-full rounded-lg border border-gray-200 bg-white pr-10 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword(!showPassword)
                                        }
                                        className="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600"
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

                            {/* Confirm */}
                            <div className="space-y-1">
                                <label
                                    htmlFor="password_confirmation"
                                    className="text-xs font-medium whitespace-nowrap text-gray-700"
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
                                        className="h-10 w-full rounded-lg border border-gray-200 bg-white pr-10 pl-10 text-sm text-gray-900 transition placeholder:text-gray-400 focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowConfirm(!showConfirm)
                                        }
                                        className="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600"
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
                                        confirmationError ??
                                        errors.password_confirmation
                                    }
                                />
                            </div>

                            {/* Full width under both password fields, so each hint fits on one line. */}
                            <div className="col-span-2">
                                <PasswordRequirements
                                    requirements={passwordRequirements}
                                    nowrap
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="col-span-2 flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-brand text-sm font-semibold text-brand-foreground transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? (
                                    <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                ) : (
                                    <>
                                        <span>Create Account</span>
                                        <ArrowRight className="size-4" />
                                    </>
                                )}
                            </button>
                        </form>
                    </div>

                    <p className="mt-3 text-center text-sm text-gray-600">
                        Already have an account?{' '}
                        <Link
                            href={route('login')}
                            className="font-semibold text-brand hover:underline"
                        >
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
