import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, Lock, Mail, Phone, ShieldCheck, User } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import type { SharedPageProps } from '@/types';

export default function Register(): React.JSX.Element {
    const { settings } = usePage<SharedPageProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <div className="flex min-h-screen bg-white">
            <Head title="Create Account — ShoppyDrive" />

            {/* ── Left brand panel ── */}
            <div className="relative hidden w-[44%] overflow-hidden lg:flex lg:flex-col lg:justify-between bg-[#c8102e]">
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.06]"
                    style={{
                        backgroundImage: 'radial-gradient(circle, rgba(255,255,255,0.9) 1px, transparent 1px)',
                        backgroundSize: '32px 32px',
                    }}
                />
                <div className="pointer-events-none absolute -right-10 top-10 h-72 w-72 rounded-full bg-white opacity-[0.06] blur-3xl" />
                <div className="pointer-events-none absolute bottom-20 -left-10 h-56 w-56 rounded-full bg-white opacity-[0.05] blur-3xl" />

                {/* Logo */}
                <div className="relative z-10 p-10">
                    <Link href="/" className="inline-flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-white/20">
                            <AppLogoIcon className="size-5 fill-current text-white" />
                        </span>
                        <span className="text-xl font-bold text-white">ShoppyDrive</span>
                    </Link>
                </div>

                {/* Content */}
                <div className="relative z-10 space-y-8 p-10 pb-16">
                    <div className="space-y-3">
                        <h2 className="text-3xl font-bold leading-tight text-white">
                            Join millions of<br />
                            <span className="text-white/70">happy shoppers</span>
                        </h2>
                        <p className="text-sm leading-7 text-white/70">
                            Create your free account and start enjoying exclusive deals, fast delivery, and premium products.
                        </p>
                    </div>

                    <div className="space-y-4">
                        {[
                            { n: '01', title: 'Create your account', desc: 'Takes less than 2 minutes' },
                            { n: '02', title: 'Browse 1,000+ products', desc: 'Fashion, electronics, beauty & more' },
                            { n: '03', title: 'Fast nationwide delivery', desc: 'Track your order in real-time' },
                        ].map(({ n, title, desc }) => (
                            <div key={n} className="flex items-start gap-4">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/20 text-sm font-bold text-white">{n}</span>
                                <div>
                                    <p className="text-sm font-semibold text-white">{title}</p>
                                    <p className="text-xs text-white/60">{desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-3">
                        <ShieldCheck className="size-4 text-white" />
                        <span className="text-xs font-medium text-white/90">100% secure with SSL encryption</span>
                    </div>
                </div>
            </div>

            {/* ── Right form panel ── */}
            <div className="flex flex-1 flex-col items-center justify-center overflow-y-auto bg-gray-50 px-4 py-12 sm:px-8">
                <div className="mb-6 lg:hidden">
                    <Link href="/" className="inline-flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-[#c8102e]">
                            <AppLogoIcon className="size-5 fill-current text-white" />
                        </span>
                        <span className="text-xl font-bold text-gray-900">ShoppyDrive</span>
                    </Link>
                </div>

                <div className="w-full max-w-[440px]">
                    <div className="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                        <div className="mb-6 space-y-1">
                            <h1 className="text-2xl font-bold text-gray-900">Create your account</h1>
                            <p className="text-sm text-gray-500">Free forever. No credit card required.</p>
                        </div>

                        {/* Social */}
                        {(settings.google_login_enabled || settings.facebook_login_enabled) && (
                            <div className={`mb-5 grid gap-3 ${settings.google_login_enabled && settings.facebook_login_enabled ? 'grid-cols-2' : 'grid-cols-1'}`}>
                                {settings.google_login_enabled && (
                                    <a href={route('social.redirect', { provider: 'google' })} className="flex h-10 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                                        <svg className="size-4" viewBox="0 0 24 24">
                                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                        </svg>
                                        Google
                                    </a>
                                )}
                                {settings.facebook_login_enabled && (
                                    <a href={route('social.redirect', { provider: 'facebook' })} className="flex h-10 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                                        <svg className="size-4" fill="#1877F2" viewBox="0 0 24 24">
                                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                        </svg>
                                        Facebook
                                    </a>
                                )}
                            </div>
                        )}

                        <div className="mb-5 flex items-center gap-3">
                            <div className="h-px flex-1 bg-gray-200" />
                            <span className="text-xs text-gray-400">or register with email</span>
                            <div className="h-px flex-1 bg-gray-200" />
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {/* Name */}
                            <div className="space-y-1.5">
                                <label htmlFor="name" className="text-sm font-medium text-gray-700">Full name</label>
                                <div className="relative">
                                    <User className="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                                    <input id="name" type="text" autoComplete="name" value={data.name} onChange={e => setData('name', e.target.value)} placeholder="Your full name" className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-900 placeholder:text-gray-400 transition focus:border-[#c8102e] focus:outline-none focus:ring-2 focus:ring-[#c8102e]/20" required />
                                </div>
                                <InputError message={errors.name} />
                            </div>

                            {/* Email */}
                            <div className="space-y-1.5">
                                <label htmlFor="email" className="text-sm font-medium text-gray-700">Email address</label>
                                <div className="relative">
                                    <Mail className="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                                    <input id="email" type="email" autoComplete="email" value={data.email} onChange={e => setData('email', e.target.value)} placeholder="you@example.com" className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-900 placeholder:text-gray-400 transition focus:border-[#c8102e] focus:outline-none focus:ring-2 focus:ring-[#c8102e]/20" required />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            {/* Phone */}
                            <div className="space-y-1.5">
                                <label htmlFor="phone" className="text-sm font-medium text-gray-700">
                                    Phone <span className="text-gray-400">(optional)</span>
                                </label>
                                <div className="relative">
                                    <Phone className="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                                    <input id="phone" type="tel" autoComplete="tel" value={data.phone} onChange={e => setData('phone', e.target.value)} placeholder="+880 1XXX XXXXXX" className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-900 placeholder:text-gray-400 transition focus:border-[#c8102e] focus:outline-none focus:ring-2 focus:ring-[#c8102e]/20" />
                                </div>
                            </div>

                            {/* Password */}
                            <div className="space-y-1.5">
                                <label htmlFor="password" className="text-sm font-medium text-gray-700">Password</label>
                                <div className="relative">
                                    <Lock className="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                                    <input id="password" type={showPassword ? 'text' : 'password'} autoComplete="new-password" value={data.password} onChange={e => setData('password', e.target.value)} placeholder="Minimum 8 characters" className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-12 text-sm text-gray-900 placeholder:text-gray-400 transition focus:border-[#c8102e] focus:outline-none focus:ring-2 focus:ring-[#c8102e]/20" required />
                                    <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            {/* Confirm */}
                            <div className="space-y-1.5">
                                <label htmlFor="password_confirmation" className="text-sm font-medium text-gray-700">Confirm password</label>
                                <div className="relative">
                                    <Lock className="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                                    <input id="password_confirmation" type={showConfirm ? 'text' : 'password'} autoComplete="new-password" value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} placeholder="Repeat password" className="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-12 text-sm text-gray-900 placeholder:text-gray-400 transition focus:border-[#c8102e] focus:outline-none focus:ring-2 focus:ring-[#c8102e]/20" required />
                                    <button type="button" onClick={() => setShowConfirm(!showConfirm)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        {showConfirm ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <button type="submit" disabled={processing} className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#c8102e] text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:cursor-not-allowed disabled:opacity-60">
                                {processing
                                    ? <span className="size-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                    : <><span>Create Account</span><ArrowRight className="size-4" /></>
                                }
                            </button>
                        </form>
                    </div>

                    <p className="mt-5 text-center text-sm text-gray-500">
                        Already have an account?{' '}
                        <Link href={route('login')} className="font-semibold text-[#c8102e] hover:underline">Sign in</Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
