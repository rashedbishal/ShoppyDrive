import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { PermissionMatrix } from '@/components/admin/PermissionMatrix';
import PasswordRequirements from '@/components/password-requirements';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { RouteModal } from '@/components/ui/route-modal';
import {
    email as emailRule,
    maxLength,
    required,
    validate,
} from '@/lib/validate';
import type { SharedPageProps } from '@/types';

type SectionGroup = {
    group: string;
    sections: { key: string; label: string; abilities: string[] }[];
};

type Props = {
    sectionCatalog: SectionGroup[];
};

export default function AdminCreate({ sectionCatalog }: Props) {
    const { password_requirements: passwordRequirements } =
        usePage<SharedPageProps>().props;
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            permissions: {} as Record<string, string[]>,
        });

    const inputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        // The password's actual strength rule is environment-dependent
        // (App\Support\PasswordPolicy) and includes a server-only breach
        // check — only `required()` is checked client-side; everything
        // else is left to the server's real error instead of a client
        // guess that could pass a password the server then rejects.
        const clientErrors: Record<string, string> = validate(data, {
            name: [required(), maxLength(100)],
            email: [required(), maxLength(150), emailRule()],
            password: [required()],
        });

        if (
            data.password &&
            data.password_confirmation &&
            data.password !== data.password_confirmation
        ) {
            clientErrors.password_confirmation = 'The passwords do not match.';
        }

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.admins.store'));
    };

    return (
        <>
            <Head title="New Admin — Admin" />
            <RouteModal
                title="New Admin Account"
                returnTo={route('admin.admins.index')}
                className="sm:max-w-2xl"
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Account Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        Full Name
                                    </label>
                                    <input
                                        maxLength={100}
                                        className={inputClass}
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="Admin name"
                                    />
                                    <CharCount
                                        value={data.name}
                                        max={100}
                                        className="mt-1 block text-right"
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
                                        maxLength={150}
                                        type="email"
                                        className={inputClass}
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        placeholder="admin@example.com"
                                    />
                                    <CharCount
                                        value={data.email}
                                        max={150}
                                        className="mt-1 block text-right"
                                    />
                                    {errors.email && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        Password
                                    </label>
                                    <input
                                        maxLength={255}
                                        type="password"
                                        className={inputClass}
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        placeholder="Create a password"
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
                                        Confirm Password
                                    </label>
                                    <input
                                        maxLength={255}
                                        type="password"
                                        className={inputClass}
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Repeat password"
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

                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Permissions
                            </CardTitle>
                            <p className="text-xs text-slate-600">
                                Pick which sections this admin can see. Each one
                                defaults to view-only — check Create, Update or
                                Delete to grant more.
                            </p>
                        </CardHeader>
                        <CardContent className="pt-3">
                            <PermissionMatrix
                                catalog={sectionCatalog}
                                value={data.permissions}
                                onChange={(value) =>
                                    setData('permissions', value)
                                }
                            />
                        </CardContent>
                    </Card>

                    <div className="flex gap-2 pt-2">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Creating…' : 'Create Admin'}
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
