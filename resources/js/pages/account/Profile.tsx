import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    User,
    Mail,
    Phone,
    Camera,
    Save,
    ShieldCheck,
    TriangleAlert,
} from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';

import AccountProfileController from '@/actions/App/Http/Controllers/AccountProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import AccountLayout from '@/components/layout/AccountLayout';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { getInitials } from '@/lib/utils';
import type { SharedPageProps, User as UserType } from '@/types';

type ProfileProps = {
    mustVerifyEmail: boolean;
    status?: string;
    pendingEmailChange?: { new_email: string; sent_to: string } | null;
    cartRemindersEnabled: boolean;
    /** Connected to Google/Facebook: the email can't be changed here. */
    emailLocked: boolean;
};

export default function Profile({
    mustVerifyEmail,
    status,
    pendingEmailChange,
    cartRemindersEnabled,
    emailLocked,
}: ProfileProps): React.JSX.Element {
    const { auth } = usePage<SharedPageProps>().props;
    const user = auth.user as UserType;
    const [savingCartReminders, setSavingCartReminders] = useState(false);
    const isUnverified = mustVerifyEmail && user.email_verified_at === null;

    // OPS-006: the email's unsubscribe link only turns these off; this
    // lets the customer see the setting and turn it back on too.
    const updateCartReminders = (enabled: boolean) => {
        router.patch(
            AccountProfileController.updateCartReminders.url(),
            { enabled },
            {
                preserveScroll: true,
                onStart: () => setSavingCartReminders(true),
                onFinish: () => setSavingCartReminders(false),
            },
        );
    };

    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        name: user.name,
        email: user.email,
        phone: user.phone || '',
        avatar: null as File | null,
        remove_avatar: false,
    });

    const { setData: setFormData } = form;

    // Keep the field on the saved address after a change is confirmed,
    // started or cancelled (the page reloads its props, not this form).
    useEffect(() => {
        setFormData('email', user.email);
    }, [user.email, pendingEmailChange?.new_email, setFormData]);

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (file) {
            form.setData('avatar', file);
            form.setData('remove_avatar', false);
            const reader = new FileReader();
            reader.onloadend = () => {
                setAvatarPreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const handleRemoveAvatar = () => {
        form.setData('avatar', null);
        form.setData('remove_avatar', true);
        setAvatarPreview(null);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Use post with _method=PUT for file uploads in Inertia
        form.post(route('account.profile.update'), {
            forceFormData: true,
            onSuccess: () => {
                setAvatarPreview(null);
            },
        });
    };

    return (
        <AccountLayout>
            <Head title="My Profile" />

            <div className="space-y-8">
                {/* Header */}
                <div>
                    <h1 className="mb-1 font-serif text-3xl font-bold text-[var(--color-text-primary)]">
                        Profile Information
                    </h1>
                    <p className="text-[var(--color-text-secondary)]">
                        Update your account details and manage how we
                        communicate with you.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
                    {/* Left: Form */}
                    <div className="lg:col-span-8">
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-8 rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm md:p-10"
                        >
                            {/* Avatar Upload */}
                            <div className="flex flex-col items-center gap-8 border-b border-[var(--color-border)] pb-8 md:flex-row">
                                <div className="group relative">
                                    <Avatar className="h-32 w-32 border-4 border-white shadow-xl">
                                        <AvatarImage
                                            src={
                                                avatarPreview ||
                                                (form.data.remove_avatar
                                                    ? undefined
                                                    : user.avatar) ||
                                                undefined
                                            }
                                        />
                                        <AvatarFallback className="bg-[var(--color-primary-light)] text-3xl font-bold text-[var(--color-primary)]">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            fileInputRef.current?.click()
                                        }
                                        aria-label="Change profile photo"
                                        className="absolute inset-0 flex flex-col items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-[2px] transition-opacity group-hover:opacity-100"
                                    >
                                        <Camera size={24} className="mb-1" />
                                        <span className="text-[10px] font-bold tracking-widest uppercase">
                                            Change Photo
                                        </span>
                                    </button>
                                    <input
                                        type="file"
                                        ref={fileInputRef}
                                        onChange={handleAvatarChange}
                                        className="hidden"
                                        accept="image/*"
                                    />
                                </div>
                                <div className="text-center md:text-left">
                                    <h3 className="mb-1 text-lg font-bold text-[var(--color-text-primary)]">
                                        Your Profile Photo
                                    </h3>
                                    <p className="mb-4 text-sm text-[var(--color-text-secondary)]">
                                        Click your photo to upload a new one.
                                    </p>
                                    <div className="flex justify-center gap-2 md:justify-start">
                                        {(avatarPreview ||
                                            (user.avatar &&
                                                !form.data.remove_avatar)) && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-10 rounded-xl font-bold text-[var(--color-error)]"
                                                onClick={handleRemoveAvatar}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Full Name</Label>
                                    <div className="relative">
                                        <Input
                                            maxLength={100}
                                            id="name"
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-12 rounded-xl pl-12"
                                            required
                                        />
                                        <User
                                            size={18}
                                            className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                        />
                                    </div>
                                    {form.errors.name && (
                                        <p className="text-xs font-bold text-[var(--color-error)]">
                                            {form.errors.name}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email Address</Label>
                                    <div className="relative">
                                        {emailLocked ? (
                                            <Input
                                                id="email"
                                                type="email"
                                                value={user.email}
                                                disabled
                                                readOnly
                                                className="h-12 rounded-xl pl-12"
                                            />
                                        ) : (
                                            <Input
                                                maxLength={150}
                                                id="email"
                                                type="email"
                                                value={form.data.email}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'email',
                                                        e.target.value,
                                                    )
                                                }
                                                autoComplete="username"
                                                className="h-12 rounded-xl pl-12"
                                                required
                                            />
                                        )}
                                        <Mail
                                            size={18}
                                            className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                        />
                                    </div>
                                    <InputError message={form.errors.email} />
                                    <p className="text-[10px] font-medium text-[var(--color-text-muted)] italic">
                                        {emailLocked
                                            ? 'Your email comes from your Google or Facebook sign-in, so it can’t be changed here.'
                                            : isUnverified
                                              ? 'Your email is not verified yet, so a change takes effect straight away.'
                                              : 'To change your email we send a 6-digit code to your current address.'}
                                    </p>
                                </div>
                            </div>

                            <div className="max-w-md space-y-2">
                                <Label htmlFor="phone">Phone Number</Label>
                                <div className="relative">
                                    <span className="absolute top-1/2 left-4 -translate-y-1/2 text-sm font-bold text-[var(--color-text-muted)]">
                                        +880
                                    </span>
                                    <Input
                                        maxLength={20}
                                        id="phone"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="1XXXXXXXXX"
                                        className="h-12 rounded-xl pl-16"
                                    />
                                    <Phone
                                        size={18}
                                        className="absolute top-1/2 right-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                    />
                                </div>
                                {form.errors.phone && (
                                    <p className="text-xs font-bold text-[var(--color-error)]">
                                        {form.errors.phone}
                                    </p>
                                )}
                                {form.errors.avatar && (
                                    <p className="text-xs font-bold text-[var(--color-error)]">
                                        {form.errors.avatar}
                                    </p>
                                )}
                            </div>

                            <div className="flex justify-end border-t border-[var(--color-border)] pt-6">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    size="lg"
                                    className="h-14 rounded-xl px-10 font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                                >
                                    <Save size={20} className="mr-2" />
                                    {form.processing
                                        ? 'Saving...'
                                        : 'Save Changes'}
                                </Button>
                            </div>
                        </form>

                        {/* SEC-009: an email change waits for the code sent to the current address. */}
                        {pendingEmailChange && (
                            <div className="mt-6 space-y-4 rounded-3xl border border-amber-200 bg-amber-50 p-6">
                                <p className="text-sm text-[var(--color-text-primary)]">
                                    To change your email to{' '}
                                    <strong>
                                        {pendingEmailChange.new_email}
                                    </strong>
                                    , enter the 6-digit code we sent to{' '}
                                    <strong>
                                        {pendingEmailChange.sent_to}
                                    </strong>
                                    . Your email stays the same until you
                                    confirm.
                                </p>

                                <Form
                                    {...AccountProfileController.confirmEmailChange.form()}
                                    options={{ preserveScroll: true }}
                                    resetOnError
                                    className="space-y-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="email-change-code">
                                                    Confirmation code
                                                </Label>
                                                <Input
                                                    id="email-change-code"
                                                    name="code"
                                                    inputMode="numeric"
                                                    autoComplete="one-time-code"
                                                    maxLength={6}
                                                    required
                                                    className="h-12 max-w-40 rounded-xl tracking-widest"
                                                    placeholder="123456"
                                                />
                                                <InputError
                                                    message={errors.code}
                                                />
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <Button disabled={processing}>
                                                    Confirm new email
                                                </Button>
                                                <Link
                                                    href={AccountProfileController.cancelEmailChange()}
                                                    as="button"
                                                    preserveScroll
                                                    className="text-sm text-[var(--color-text-secondary)] underline underline-offset-4"
                                                >
                                                    Cancel
                                                </Link>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </div>
                        )}

                        <div className="mt-6 space-y-4 rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm">
                            <div>
                                <h3 className="text-lg font-bold text-[var(--color-text-primary)]">
                                    Email preferences
                                </h3>
                                <p className="text-sm text-[var(--color-text-secondary)]">
                                    Choose which optional emails we send you.
                                    Order updates are always sent.
                                </p>
                            </div>
                            <div className="flex items-start justify-between gap-4">
                                <div className="grid gap-1">
                                    <Label id="cart-reminders-label">
                                        Cart reminder emails
                                    </Label>
                                    <p className="text-sm text-[var(--color-text-secondary)]">
                                        A reminder if you leave items at
                                        checkout, at most once a week.
                                    </p>
                                </div>
                                <Switch
                                    checked={cartRemindersEnabled}
                                    onCheckedChange={updateCartReminders}
                                    disabled={savingCartReminders}
                                    aria-labelledby="cart-reminders-label"
                                />
                            </div>
                        </div>

                        <div className="mt-6 rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm">
                            <DeleteUser />
                        </div>
                    </div>

                    {/* Right: Verification */}
                    <div className="lg:col-span-4">
                        <div className="rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm">
                            <h3 className="mb-6 flex items-center gap-2 text-sm font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                <ShieldCheck
                                    size={18}
                                    className="text-[var(--color-success)]"
                                />
                                Trust & Safety
                            </h3>
                            {isUnverified ? (
                                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="text-xs font-bold text-amber-800">
                                            Email not verified
                                        </span>
                                        <TriangleAlert
                                            size={16}
                                            className="text-amber-600"
                                        />
                                    </div>
                                    <p className="text-[10px] leading-relaxed text-amber-800">
                                        Verify your email to use your account
                                        fully.{' '}
                                        <Link
                                            href={route('verification.send')}
                                            method="post"
                                            as="button"
                                            className="font-bold underline"
                                        >
                                            Resend the verification email
                                        </Link>
                                    </p>
                                    {status === 'verification-link-sent' && (
                                        <p className="mt-2 text-[10px] font-bold text-green-700">
                                            A new verification message has been
                                            sent.
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <div className="rounded-2xl border border-green-100 bg-green-50 p-4">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="text-xs font-bold text-green-800">
                                            Email Verified
                                        </span>
                                        <ShieldCheck
                                            size={16}
                                            className="text-green-600"
                                        />
                                    </div>
                                    <p className="text-[10px] leading-relaxed text-green-700">
                                        Your email address is verified. This
                                        helps us ensure your account's security.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AccountLayout>
    );
}
