import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Shield } from 'lucide-react';
import { PermissionMatrix } from '@/components/admin/PermissionMatrix';
import PasswordRequirements from '@/components/password-requirements';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RouteModal } from '@/components/ui/route-modal';
import {
    email as emailRule,
    maxLength,
    required,
    validate,
} from '@/lib/validate';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_mother: boolean;
    permissions: Record<string, string[]> | null;
};

type SectionGroup = {
    group: string;
    sections: { key: string; label: string; abilities: string[] }[];
};

type CurrentAdmin = {
    id: number;
    is_mother: boolean;
};

type Props = {
    adminUser: AdminUser;
    sectionCatalog: SectionGroup[];
};

export default function AdminEdit({ adminUser, sectionCatalog }: Props) {
    const { props } = usePage<any>();
    const currentAdmin = props.auth.admin as CurrentAdmin;
    const passwordRequirements = props.password_requirements as string[];
    // Mirrors AdminController's own rule: only the mother admin can grant
    // permissions, and never the mother's own (she always has full access).
    const canManagePermissions = currentAdmin.is_mother && !adminUser.is_mother;
    // SEC-010: changing your own password requires re-entering it, same as
    // the customer-facing settings page. Resetting a *different* admin's
    // password (the mother's privileged action) doesn't need it.
    const isSelfEdit = currentAdmin.id === adminUser.id;

    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            name: adminUser.name,
            email: adminUser.email,
            current_password: '',
            password: '',
            password_confirmation: '',
            permissions:
                adminUser.permissions ?? ({} as Record<string, string[]>),
        });

    const inputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        // The password's actual strength rule is environment-dependent
        // (App\Support\PasswordPolicy) and includes a server-only breach
        // check — nothing to check client-side beyond "was one typed",
        // left to the server's real error instead of a client guess that
        // could pass a password the server then rejects.
        const clientErrors: Record<string, string> = validate(data, {
            name: [required(), maxLength(255)],
            email: [required(), maxLength(255), emailRule()],
        });

        if (data.password && data.password !== data.password_confirmation) {
            clientErrors.password_confirmation = 'The passwords do not match.';
        }

        if (isSelfEdit && data.password && !data.current_password) {
            clientErrors.current_password =
                'Enter your current password to set a new one.';
        }

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        put(route('admin.admins.update', adminUser.id));
    };

    return (
        <>
            <Head title={`Edit ${adminUser.name} — Admin`} />
            <RouteModal
                title="Edit Admin"
                description={adminUser.email}
                returnTo={route('admin.admins.index')}
                className={
                    canManagePermissions ? 'sm:max-w-2xl' : 'sm:max-w-lg'
                }
            >
                {adminUser.is_mother && (
                    <span className="mb-4 flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold tracking-wide text-amber-700 uppercase">
                        <Shield className="size-3" />
                        Mother Admin
                    </span>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Account Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        Full Name
                                    </label>
                                    <input
                                        className={inputClass}
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                    />
                                    {errors.name && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        Email Address
                                    </label>
                                    <input
                                        type="email"
                                        className={inputClass}
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                    />
                                    {errors.email && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {isSelfEdit && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        Current Password
                                    </label>
                                    <input
                                        type="password"
                                        className={inputClass}
                                        value={data.current_password}
                                        autoComplete="current-password"
                                        onChange={(e) =>
                                            setData(
                                                'current_password',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Required to set a new password"
                                    />
                                    {errors.current_password && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.current_password}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        New Password{' '}
                                        <span className="font-normal text-slate-400">
                                            (leave blank to keep current)
                                        </span>
                                    </label>
                                    <input
                                        type="password"
                                        className={inputClass}
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        placeholder="Leave blank to keep current"
                                    />
                                    <div className="mt-1">
                                        <PasswordRequirements
                                            requirements={passwordRequirements}
                                        />
                                    </div>
                                    {errors.password && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        Confirm New Password
                                    </label>
                                    <input
                                        type="password"
                                        className={inputClass}
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Repeat new password"
                                    />
                                    {errors.password_confirmation && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.password_confirmation}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {canManagePermissions && (
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Permissions
                                </CardTitle>
                                <p className="text-xs text-slate-500">
                                    Pick which sections this admin can see. Each
                                    one defaults to view-only — check Create,
                                    Update or Delete to grant more.
                                </p>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <PermissionMatrix
                                    catalog={sectionCatalog}
                                    value={data.permissions}
                                    onChange={(value) =>
                                        setData('permissions', value)
                                    }
                                />
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex gap-2 pt-2">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        <Link href={route('admin.admins.index')}>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </Link>
                    </div>
                </form>
            </RouteModal>
        </>
    );
}
