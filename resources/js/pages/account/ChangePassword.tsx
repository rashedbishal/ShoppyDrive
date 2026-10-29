import { Head, useForm } from '@inertiajs/react';
import {
    Lock,
    ShieldCheck,
    Eye,
    EyeOff,
    CheckCircle2,
    AlertTriangle,
} from 'lucide-react';
import React, { useState } from 'react';

import AccountLayout from '@/components/layout/AccountLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ChangePassword(): React.JSX.Element {
    const [showPassword, setShowPassword] = useState(false);

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(route('user-password.update'), {
            onSuccess: () => form.reset(),
        });
    };

    const getPasswordStrength = (pass: string) => {
        if (!pass) {
            return 0;
        }

        let strength = 0;

        if (pass.length > 7) {
            strength++;
        }

        if (/[A-Z]/.test(pass)) {
            strength++;
        }

        if (/[0-9]/.test(pass)) {
            strength++;
        }

        if (/[^A-Za-z0-9]/.test(pass)) {
            strength++;
        }

        return strength;
    };

    const strength = getPasswordStrength(form.data.password);
    const strengthLabels = ['Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = [
        'bg-red-400',
        'bg-amber-400',
        'bg-blue-400',
        'bg-green-400',
    ];

    return (
        <AccountLayout>
            <Head title="Change Password" />

            <div className="space-y-8">
                {/* Header */}
                <div>
                    <h1 className="mb-1 font-serif text-3xl font-bold text-[var(--color-text-primary)]">
                        Security Settings
                    </h1>
                    <p className="text-[var(--color-text-secondary)]">
                        Manage your password and protect your account from
                        unauthorized access.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
                    {/* Left: Form */}
                    <div className="lg:col-span-8">
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-8 rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm md:p-10"
                        >
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="current_password">
                                        Current Password
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="current_password"
                                            type={
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            }
                                            value={form.data.current_password}
                                            onChange={(e) =>
                                                form.setData(
                                                    'current_password',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-12 rounded-xl pl-12"
                                            required
                                        />
                                        <Lock
                                            size={18}
                                            className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowPassword(!showPassword)
                                            }
                                            className="absolute top-1/2 right-4 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]"
                                        >
                                            {showPassword ? (
                                                <EyeOff size={18} />
                                            ) : (
                                                <Eye size={18} />
                                            )}
                                        </button>
                                    </div>
                                    {form.errors.current_password && (
                                        <p className="text-xs font-bold text-[var(--color-error)]">
                                            {form.errors.current_password}
                                        </p>
                                    )}
                                </div>

                                <div className="h-px w-full bg-[var(--color-border)]" />

                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="password">
                                            New Password
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                id="password"
                                                type={
                                                    showPassword
                                                        ? 'text'
                                                        : 'password'
                                                }
                                                value={form.data.password}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'password',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-12 rounded-xl pl-12"
                                                required
                                            />
                                            <Lock
                                                size={18}
                                                className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                            />
                                        </div>
                                        {form.errors.password && (
                                            <p className="text-xs font-bold text-[var(--color-error)]">
                                                {form.errors.password}
                                            </p>
                                        )}
                                    </div>

                                    {/* Strength Indicator */}
                                    {form.data.password && (
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="text-[10px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                                    Strength:{' '}
                                                    {
                                                        strengthLabels[
                                                            strength - 1
                                                        ]
                                                    }
                                                </span>
                                                <span className="text-[10px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                                    {strength * 25}%
                                                </span>
                                            </div>
                                            <div className="flex h-1.5 w-full gap-1 overflow-hidden rounded-full bg-[var(--color-surface-alt)]">
                                                {[...Array(4)].map((_, i) => (
                                                    <div
                                                        key={i}
                                                        className={`h-full flex-1 transition-all duration-500 ${
                                                            i < strength
                                                                ? strengthColors[
                                                                      strength -
                                                                          1
                                                                  ]
                                                                : 'bg-gray-200'
                                                        }`}
                                                    />
                                                ))}
                                            </div>
                                            <ul className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1">
                                                {[
                                                    {
                                                        label: '8+ Characters',
                                                        check:
                                                            form.data.password
                                                                .length > 7,
                                                    },
                                                    {
                                                        label: 'Upper Case',
                                                        check: /[A-Z]/.test(
                                                            form.data.password,
                                                        ),
                                                    },
                                                    {
                                                        label: 'Numbers',
                                                        check: /[0-9]/.test(
                                                            form.data.password,
                                                        ),
                                                    },
                                                    {
                                                        label: 'Symbols',
                                                        check: /[^A-Za-z0-9]/.test(
                                                            form.data.password,
                                                        ),
                                                    },
                                                ].map((item) => (
                                                    <li
                                                        key={item.label}
                                                        className={`flex items-center gap-2 text-[10px] font-bold tracking-tight uppercase ${item.check ? 'text-green-600' : 'text-gray-400'}`}
                                                    >
                                                        <CheckCircle2
                                                            size={10}
                                                            className={
                                                                item.check
                                                                    ? 'fill-current'
                                                                    : ''
                                                            }
                                                        />
                                                        {item.label}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm New Password
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="password_confirmation"
                                            type={
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            }
                                            value={
                                                form.data.password_confirmation
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'password_confirmation',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-12 rounded-xl pl-12"
                                            required
                                        />
                                        <Lock
                                            size={18}
                                            className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                        />
                                    </div>
                                    {form.errors.password_confirmation && (
                                        <p className="text-xs font-bold text-[var(--color-error)]">
                                            {form.errors.password_confirmation}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end border-t border-[var(--color-border)] pt-6">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    size="lg"
                                    className="h-14 rounded-xl px-12 font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                                >
                                    Update Password
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Right: Tips */}
                    <div className="space-y-8 lg:col-span-4">
                        <div className="rounded-3xl border border-[var(--color-border)] bg-[var(--color-surface)] p-8 shadow-sm">
                            <h3 className="mb-6 flex items-center gap-2 text-sm font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                <ShieldCheck
                                    size={18}
                                    className="text-[var(--color-primary)]"
                                />
                                Security Tips
                            </h3>
                            <div className="space-y-6">
                                <div className="flex gap-4">
                                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                        <AlertTriangle size={16} />
                                    </div>
                                    <p className="text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                        Avoid using common words or personal
                                        information like your birthday or pet's
                                        name.
                                    </p>
                                </div>
                                <div className="flex gap-4">
                                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                        <ShieldCheck size={16} />
                                    </div>
                                    <p className="text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                        Use a unique password for each online
                                        account to prevent multiple accounts
                                        from being compromised.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AccountLayout>
    );
}
