import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Plus, Shield, Trash2, User } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_mother: boolean;
    created_at: string;
};

type CurrentAdmin = {
    id: number;
    name: string;
    email: string;
    is_mother: boolean;
};

type Props = {
    admins: AdminUser[];
};

export default function AdminsIndex({ admins }: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string; error?: string } | undefined;
    const currentAdmin = props.auth.admin as CurrentAdmin;

    const handleDelete = (admin: AdminUser) => {
        if (!confirm(`Are you sure you want to delete "${admin.name}"? They will be immediately logged out.`)) {
            return;
        }
        router.delete(route('admin.admins.destroy', admin.id));
    };

    const canEdit = (admin: AdminUser) => {
        if (admin.is_mother && currentAdmin.id !== admin.id) return false;
        return true;
    };

    const canDelete = (admin: AdminUser) => {
        if (admin.id === currentAdmin.id) return false;
        if (admin.is_mother) return false;
        return true;
    };

    return (
        <>
            <Head title="Admin Accounts — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Admin Accounts</h1>
                        <p className="text-sm text-slate-500">{admins.length} total</p>
                    </div>
                    <Link href={route('admin.admins.create')}>
                        <Button className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                            <Plus className="size-4" />
                            New Admin
                        </Button>
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}
                {flash?.error && (
                    <div className="rounded-lg bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700">{flash.error}</div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="divide-y divide-slate-100">
                        {admins.map((admin) => (
                            <div key={admin.id} className="flex items-center gap-4 px-4 py-3">
                                <div className={`flex size-9 shrink-0 items-center justify-center rounded-full ${admin.is_mother ? 'bg-amber-100' : 'bg-slate-100'}`}>
                                    {admin.is_mother
                                        ? <Shield className="size-4 text-amber-600" />
                                        : <User className="size-4 text-slate-500" />
                                    }
                                </div>

                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-semibold text-slate-800">{admin.name}</span>
                                        {admin.is_mother && (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">
                                                Mother Admin
                                            </span>
                                        )}
                                        {admin.id === currentAdmin.id && (
                                            <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-600">
                                                You
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-slate-400">{admin.email}</p>
                                </div>

                                <p className="hidden shrink-0 text-xs text-slate-400 sm:block">
                                    {new Date(admin.created_at).toLocaleDateString('en-BD', { dateStyle: 'medium' })}
                                </p>

                                <div className="flex shrink-0 items-center gap-1">
                                    {canEdit(admin) ? (
                                        <Link href={route('admin.admins.edit', admin.id)}>
                                            <button
                                                type="button"
                                                title="Edit"
                                                className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                            >
                                                <Edit className="size-4" />
                                            </button>
                                        </Link>
                                    ) : (
                                        <button
                                            type="button"
                                            disabled
                                            title="Cannot edit mother admin"
                                            className="rounded-lg p-1.5 text-slate-200 cursor-not-allowed"
                                        >
                                            <Edit className="size-4" />
                                        </button>
                                    )}

                                    {canDelete(admin) ? (
                                        <button
                                            type="button"
                                            onClick={() => handleDelete(admin)}
                                            title="Delete"
                                            className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            disabled
                                            title={admin.is_mother ? 'Cannot delete mother admin' : 'Cannot delete your own account'}
                                            className="rounded-lg p-1.5 text-slate-200 cursor-not-allowed"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                    <strong>Mother Admin</strong> is the root account and cannot be deleted or modified by other admins.
                    Deleted admin accounts are immediately logged out.
                </div>
            </div>
        </>
    );
}
