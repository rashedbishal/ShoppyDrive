import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Shield } from 'lucide-react';
import { PermissionMatrix } from '@/components/admin/PermissionMatrix';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RouteModal } from '@/components/ui/route-modal';
import {
    email as emailRule,
    maxLength,
    minLength,
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
    // Mirrors AdminController's own rule: only the mother admin can grant
    // permissions, and never the mother's own (she always has full access).
    const canManagePermissions = currentAdmin.is_mother && !adminUser.is_mother;

    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            name: adminUser.name,
            email: adminUser.email,
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
        const clientErrors: Record<string, string> = validate(data, {
            name: [required(), maxLength(255)],
            email: [required(), maxLength(255), emailRule()],
            password: data.password ? [minLength(8)] : [],
        });

        if (data.password && data.password !== data.password_confirmation) {
            clientErrors.password_confirmation = 'The passwords do not match.';
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
                                    placeholder="Minimum 8 characters"
                                />
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
                        </CardContent>
                    </Card>

                    {canManagePermissions && (
                        <Card className="mt-4 shadow-sm">
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
