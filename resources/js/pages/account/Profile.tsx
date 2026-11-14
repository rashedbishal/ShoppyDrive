import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    User,
    Mail,
    Phone,
    Camera,
    Save,
    BadgeCheck,
    TriangleAlert,
} from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';

import AccountProfileController from '@/actions/App/Http/Controllers/AccountProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
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
        <>
            <Head title="My Profile" />

            <div className="space-y-5">
                {/* Header */}
                <div>
                    <h1 className="mb-0.5 font-display text-2xl font-bold text-[var(--color-text-primary)]">
                        Profile Information
                    </h1>
                    <p className="text-sm text-[var(--color-text-secondary)]">
                        Update your account details and manage how we
                        communicate with you.
                    </p>
                </div>

                <div>
                    <div className="space-y-4">
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-5 rounded-2xl border border-[var(--color-border)] bg-white p-5 shadow-sm md:p-6"
                        >
                            {/* Avatar Upload */}
                            <div className="flex flex-col items-center gap-5 border-b border-[var(--color-border)] pb-5 md:flex-row">
                                <div className="group relative">
                                    <Avatar className="h-20 w-20 border-2 border-white shadow-md">
                                        <AvatarImage
                                            src={
                                                avatarPreview ||
                                                (form.data.remove_avatar
                                                    ? undefined
                                                    : user.avatar) ||
                                                undefined
                                            }
                                        />
                                        <AvatarFallback className="bg-[var(--color-primary-light)] text-2xl font-bold text-[var(--color-primary)]">
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
                                        <Camera size={18} className="mb-0.5" />
                                        <span className="text-[8px] font-bold tracking-widest uppercase">
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
                                    <h3 className="mb-0.5 text-base font-bold text-[var(--color-text-primary)]">
                                        Your Profile Photo
                                    </h3>
                                    <p className="mb-2 text-sm text-[var(--color-text-secondary)]">
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
                                                className="h-8 rounded-lg px-3 font-bold text-[var(--color-error)]"
                                                onClick={handleRemoveAvatar}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-x-5 gap-y-2 md:grid-cols-2">
                                <div className="space-y-1">
                                    <Label htmlFor="name" className="h-5">
                                        Full Name
                                    </Label>
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
                                            className="h-10 rounded-xl pl-10"
                                            required
                                        />
                                        <User
                                            size={16}
                                            className="absolute top-1/2 left-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
                                        />
                                    </div>
                                    {form.errors.name && (
                                        <p className="text-xs font-bold text-[var(--color-error)]">
                                            {form.errors.name}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-1 md:relative">
                                    <div className="flex h-5 items-center justify-between gap-2">
                                        <Label htmlFor="email">
                                            Email Address
                                        </Label>
                                        {isUnverified ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                                <TriangleAlert size={12} />
                                                Not verified
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-bold text-green-700">
                                                <BadgeCheck size={12} />
                                                Verified
                                            </span>
                                        )}
                                    </div>
                                    <div className="relative">
                                        {emailLocked ? (
                                            <Input
                                                id="email"
                                                type="email"
                                                value={user.email}
                                                disabled
                                                readOnly
                                                className="h-10 rounded-xl pl-10"
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
                                                className="h-10 rounded-xl pl-10"
                                                required
                                            />
                                        )}
                                        <Mail
                                            size={16}
                                            className="absolute top-1/2 left-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
                                        />
                                    </div>
                                    <InputError message={form.errors.email} />
                                    {/* From md up this sits beside Phone Number, so it doesn't make the Full Name row taller. */}
                                    <div className="space-y-1 md:absolute md:inset-x-0 md:top-full md:mt-1">
                                        <p className="text-[10px] font-medium text-[var(--color-text-secondary)] italic">
                                            {emailLocked
                                                ? 'Your email comes from your Google or Facebook sign-in, so it can’t be changed here.'
                                                : isUnverified
                                                  ? 'Your email is not verified yet, so a change takes effect straight away.'
                                                  : 'To change your email we send a 6-digit code to your current address.'}
                                        </p>
                                        {isUnverified && (
                                            <p className="text-[10px] leading-relaxed text-amber-800">
                                                Verify your email to use your
                                                account fully.{' '}
                                                <Link
                                                    href={route(
                                                        'verification.send',
                                                    )}
                                                    method="post"
                                                    as="button"
                                                    className="font-bold underline"
                                                >
                                                    Resend the verification
                                                    email
                                                </Link>
                                            </p>
                                        )}
                                        {isUnverified &&
                                            status ===
                                                'verification-link-sent' && (
                                                <p className="text-[10px] font-bold text-green-700">
                                                    A new verification message
                                                    has been sent.
                                                </p>
                                            )}
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="phone" className="h-5">
                                        Phone Number
                                    </Label>
                                    <div className="relative">
                                        <span className="absolute top-1/2 left-3.5 -translate-y-1/2 text-sm font-bold text-[var(--color-text-secondary)]">
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
                                            className="h-10 rounded-xl pl-14"
                                        />
                                        <Phone
                                            size={16}
                                            className="absolute top-1/2 right-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
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
                            </div>

                            <div className="flex justify-end border-t border-[var(--color-border)] pt-4">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="h-10 rounded-xl px-6 font-bold shadow-[var(--color-primary)]/20 shadow-md"
                                >
                                    <Save size={16} className="mr-2" />
                                    {form.processing
                                        ? 'Saving...'
                                        : 'Save Changes'}
                                </Button>
                            </div>
                        </form>

                        {/* SEC-009: an email change waits for the code sent to the current address. */}
                        {pendingEmailChange && (
                            <div className="space-y-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 md:p-5">
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
                                                    className="h-10 max-w-40 rounded-xl tracking-widest"
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

                        <div className="space-y-3 rounded-2xl border border-[var(--color-border)] bg-white p-5 shadow-sm md:p-6">
                            <div>
                                <h3 className="text-base font-bold text-[var(--color-text-primary)]">
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

                        <div className="rounded-2xl border border-[var(--color-border)] bg-white p-5 shadow-sm md:p-6">
                            <DeleteUser />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
