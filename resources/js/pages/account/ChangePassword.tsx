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

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { passwordMismatchMessage, passwordsMatch } from '@/lib/password-match';

/** DISC-32: App\Support\PasswordPolicy::checks(), the real server rule. */
type PasswordChecks = {
    min_length: number;
    mixed_case: boolean;
    numbers: boolean;
    symbols: boolean;
    uncompromised: boolean;
};

/**
 * The browser-checkable rules from the server's policy, using the same
 * patterns as Laravel's Password rule so the two always agree. The breach
 * check (`uncompromised`) can only run on the server and is listed separately.
 */
function passwordChecklist(
    password: string,
    checks: PasswordChecks,
): { label: string; check: boolean }[] {
    return [
        {
            label: `${checks.min_length}+ Characters`,
            check: [...password].length >= checks.min_length,
        },
        ...(checks.mixed_case
            ? [
                  {
                      label: 'Upper & Lower Case',
                      check: /(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u.test(
                          password,
                      ),
                  },
              ]
            : []),
        ...(checks.numbers
            ? [{ label: 'Numbers', check: /\p{N}/u.test(password) }]
            : []),
        ...(checks.symbols
            ? [
                  {
                      label: 'Symbols',
                      check: /\p{Z}|\p{S}|\p{P}/u.test(password),
                  },
              ]
            : []),
    ];
}

export default function ChangePassword({
    passwordChecks,
}: {
    passwordChecks: PasswordChecks;
}): React.JSX.Element {
    const [showPassword, setShowPassword] = useState(false);

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const confirmationError = passwordMismatchMessage(
        form.data.password,
        form.data.password_confirmation,
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (
            !passwordsMatch(form.data.password, form.data.password_confirmation)
        ) {
            document.getElementById('password_confirmation')?.focus();

            return;
        }

        form.put(route('account.password.update'), {
            onSuccess: () => form.reset(),
        });
    };

    const checklist = passwordChecklist(form.data.password, passwordChecks);
    const totalChecks = checklist.length;
    const strength = checklist.filter((item) => item.check).length;
    // "Strong" only when every real rule is met; partial progress is scaled
    // onto the four labels whatever the number of rules.
    const strengthLevel =
        strength === totalChecks
            ? 3
            : Math.min(
                  2,
                  Math.max(0, Math.ceil((strength / totalChecks) * 4) - 1),
              );
    const strengthPercent = Math.round((strength / totalChecks) * 100);
    const strengthLabels = ['Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = [
        'bg-red-400',
        'bg-amber-400',
        'bg-blue-400',
        'bg-green-400',
    ];

    return (
        <>
            <Head title="Change Password" />

            <div className="space-y-5">
                {/* Header */}
                <div>
                    <h1 className="mb-0.5 font-display text-2xl font-bold text-[var(--color-text-primary)]">
                        Security Settings
                    </h1>
                    <p className="text-sm text-[var(--color-text-secondary)]">
                        Manage your password and protect your account from
                        unauthorized access.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
                    {/* Left: Form */}
                    <div className="lg:col-span-8">
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-5 rounded-2xl border border-[var(--color-border)] bg-white p-5 shadow-sm md:p-6"
                        >
                            <div className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="current_password">
                                        Current Password
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            maxLength={128}
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
                                            className="h-10 rounded-xl pl-10"
                                            required
                                        />
                                        <Lock
                                            size={16}
                                            className="absolute top-1/2 left-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowPassword(!showPassword)
                                            }
                                            className="absolute top-1/2 right-3.5 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]"
                                        >
                                            {showPassword ? (
                                                <EyeOff size={16} />
                                            ) : (
                                                <Eye size={16} />
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

                                <div className="space-y-3">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="password">
                                            New Password
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                maxLength={128}
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
                                                className="h-10 rounded-xl pl-10"
                                                required
                                            />
                                            <Lock
                                                size={16}
                                                className="absolute top-1/2 left-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
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
                                                <span className="text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                                    Strength:{' '}
                                                    {
                                                        strengthLabels[
                                                            strengthLevel
                                                        ]
                                                    }
                                                </span>
                                                <span className="text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                                    {strengthPercent}%
                                                </span>
                                            </div>
                                            <div className="flex h-1.5 w-full gap-1 overflow-hidden rounded-full bg-[var(--color-surface-alt)]">
                                                {[...Array(totalChecks)].map(
                                                    (_, i) => (
                                                        <div
                                                            key={i}
                                                            className={`h-full flex-1 transition-all duration-500 ${
                                                                i < strength
                                                                    ? strengthColors[
                                                                          strengthLevel
                                                                      ]
                                                                    : 'bg-gray-200'
                                                            }`}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                            <ul className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                                                {checklist.map((item) => (
                                                    <li
                                                        key={item.label}
                                                        className={`flex items-center gap-2 text-[10px] font-bold tracking-tight uppercase ${item.check ? 'text-green-600' : 'text-gray-600'}`}
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
                                                {passwordChecks.uncompromised && (
                                                    <li className="col-span-2 flex items-center gap-2 text-[10px] font-bold tracking-tight text-gray-600 uppercase">
                                                        <ShieldCheck
                                                            size={10}
                                                        />
                                                        Not a known leaked
                                                        password · checked when
                                                        you save
                                                    </li>
                                                )}
                                            </ul>
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="password_confirmation">
                                        Confirm New Password
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            maxLength={128}
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
                                            className="h-10 rounded-xl pl-10"
                                            required
                                        />
                                        <Lock
                                            size={16}
                                            className="absolute top-1/2 left-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
                                        />
                                    </div>
                                    {(confirmationError ??
                                        form.errors.password_confirmation) && (
                                        <p className="text-xs font-bold text-[var(--color-error)]">
                                            {confirmationError ??
                                                form.errors
                                                    .password_confirmation}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end border-t border-[var(--color-border)] pt-4">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="h-10 rounded-xl px-8 font-bold shadow-[var(--color-primary)]/20 shadow-md"
                                >
                                    Update Password
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Right: Tips */}
                    <div className="space-y-5 lg:col-span-4">
                        <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 shadow-sm">
                            <h3 className="mb-4 flex items-center gap-2 text-sm font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                <ShieldCheck
                                    size={18}
                                    className="text-[var(--color-primary)]"
                                />
                                Security Tips
                            </h3>
                            <div className="space-y-4">
                                <div className="flex gap-3">
                                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                        <AlertTriangle size={16} />
                                    </div>
                                    <p className="text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                        Avoid using common words or personal
                                        information like your birthday or pet's
                                        name.
                                    </p>
                                </div>
                                <div className="flex gap-3">
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
        </>
    );
}
