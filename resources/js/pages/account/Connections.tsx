import { Form, Head, usePage } from '@inertiajs/react';
import { Check, KeyRound } from 'lucide-react';
import ConnectionsController from '@/actions/App/Http/Controllers/Settings/ConnectionsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import AccountLayout from '@/components/layout/AccountLayout';
import PasswordInput from '@/components/password-input';
import PasswordRequirements from '@/components/password-requirements';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Connection = {
    id: number;
    provider: 'google' | 'facebook';
    connected_at: string;
};

type Props = {
    hasPassword: boolean;
    connections: Connection[];
};

const GoogleIcon = () => (
    <svg className="size-5" viewBox="0 0 24 24">
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
);

const FacebookIcon = () => (
    <svg className="size-5" fill="#1877F2" viewBox="0 0 24 24">
        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
    </svg>
);

const PROVIDERS: {
    key: 'google' | 'facebook';
    label: string;
    icon: () => React.JSX.Element;
}[] = [
    { key: 'google', label: 'Google', icon: GoogleIcon },
    { key: 'facebook', label: 'Facebook', icon: FacebookIcon },
];

export default function Connections({ hasPassword, connections }: Props) {
    const { props } = usePage<any>();
    const settings = props.settings as {
        google_login_enabled: boolean;
        facebook_login_enabled: boolean;
    };
    const errors = props.errors as { connections?: string } | undefined;
    const passwordRequirements = props.password_requirements as string[];

    const loginEnabled: Record<'google' | 'facebook', boolean> = {
        google: settings.google_login_enabled,
        facebook: settings.facebook_login_enabled,
    };

    return (
        <AccountLayout>
            <Head title="Connected accounts" />

            <div className="space-y-8">
                <div>
                    <h1 className="mb-1 font-serif text-3xl font-bold text-[var(--color-text-primary)]">
                        Connected Accounts
                    </h1>
                    <p className="text-[var(--color-text-secondary)]">
                        Manage how you sign in to your ShoppyDrive account.
                    </p>
                </div>

                <div className="space-y-6 rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm">
                    <Heading
                        variant="small"
                        title="Login methods"
                        description="Sign in with your email and password, or a connected Google or Facebook account."
                    />

                    {errors?.connections && (
                        <InputError message={errors.connections} />
                    )}

                    <div className="space-y-3">
                        <div className="flex items-center justify-between rounded-xl border border-border p-4">
                            <div className="flex items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                                    <KeyRound className="size-4 text-muted-foreground" />
                                </span>
                                <div>
                                    <p className="text-sm font-medium">
                                        Email &amp; password
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {hasPassword
                                            ? 'You can sign in with your email and password.'
                                            : 'Not set up yet — see below.'}
                                    </p>
                                </div>
                            </div>
                            {hasPassword && (
                                <Check className="size-4 shrink-0 text-emerald-600" />
                            )}
                        </div>

                        {PROVIDERS.filter(
                            ({ key }) =>
                                loginEnabled[key] ||
                                connections.some((c) => c.provider === key),
                        ).map(({ key, label, icon: Icon }) => {
                            const connection = connections.find(
                                (c) => c.provider === key,
                            );

                            return (
                                <div
                                    key={key}
                                    className="flex items-center justify-between rounded-xl border border-border p-4"
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                                            <Icon />
                                        </span>
                                        <div>
                                            <p className="text-sm font-medium">
                                                {label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {connection
                                                    ? `Connected ${new Date(connection.connected_at).toLocaleDateString()}`
                                                    : 'Not connected.'}
                                            </p>
                                        </div>
                                    </div>

                                    {connection ? (
                                        <Form
                                            {...ConnectionsController.destroy.form(
                                                connection.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Disconnect
                                                </Button>
                                            )}
                                        </Form>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={route('social.redirect', {
                                                    provider: key,
                                                })}
                                            >
                                                Connect
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {!hasPassword && (
                    <div className="space-y-6 rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm">
                        <Heading
                            variant="small"
                            title="Set a password"
                            description="You signed up using a social account. Set a password so you can also sign in with your email."
                        />

                        <Form
                            {...ConnectionsController.setPassword.form()}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ errors: formErrors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            New password
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            autoComplete="new-password"
                                            placeholder="New password"
                                        />
                                        <PasswordRequirements
                                            requirements={passwordRequirements}
                                        />
                                        <InputError
                                            message={formErrors.password}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirm password
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            autoComplete="new-password"
                                            placeholder="Confirm password"
                                        />
                                        <InputError
                                            message={
                                                formErrors.password_confirmation
                                            }
                                        />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        Set password
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                )}
            </div>
        </AccountLayout>
    );
}
