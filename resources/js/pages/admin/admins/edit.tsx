import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Shield } from 'lucide-react';
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
    can_view_audit_trail: boolean;
};

type CurrentAdmin = {
    id: number;
    name: string;
    email: string;
    is_mother: boolean;
};

type Props = {
    adminUser: AdminUser;
};

export default function AdminEdit({ adminUser }: Props) {
    const { props } = usePage<any>();
    const currentAdmin = props.auth.admin as CurrentAdmin;
    const canManagePermissions = currentAdmin.is_mother && !adminUser.is_mother;

    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            name: adminUser.name,
            email: adminUser.email,
            password: '',
            password_confirmation: '',
            can_view_audit_trail: adminUser.can_view_audit_trail,
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
                className="sm:max-w-lg"
            >
                {adminUser.is_mother && (
                    <span className="flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold tracking-wide text-amber-700 uppercase">
                        <Shield className="size-3" />
                        Mother Admin
                    </span>
                )}

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-4">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Account Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4">
                        <form onSubmit={handleSubmit} className="space-y-4">
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

                            {canManagePermissions && (
                                <div className="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                    <input
                                        type="checkbox"
                                        id="can_view_audit_trail"
                                        className="mt-0.5 size-4 rounded border-slate-300 text-[#c8102e] focus:ring-[#c8102e]"
                                        checked={data.can_view_audit_trail}
                                        onChange={(e) =>
                                            setData(
                                                'can_view_audit_trail',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <label
                                        htmlFor="can_view_audit_trail"
                                        className="text-sm text-slate-600"
                                    >
                                        <span className="font-medium text-slate-700">
                                            Grant Audit Trail access
                                        </span>
                                        <br />
                                        Lets this admin view every admin and
                                        order activity log. Only the mother
                                        admin can clear logs.
                                    </label>
                                </div>
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
                    </CardContent>
                </Card>
            </RouteModal>
        </>
    );
}
