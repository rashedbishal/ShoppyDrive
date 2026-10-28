import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, History, Plus, Shield, Trash2, User } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_mother: boolean;
    can_view_audit_trail: boolean;
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
    const [deleteTarget, setDeleteTarget] = useState<AdminUser | null>(null);
    const [deleting, setDeleting] = useState(false);
    const { props } = usePage<any>();
    const currentAdmin = props.auth.admin as CurrentAdmin;

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.admins.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    // Mirrors AdminController's server-side authorization — only the
    // mother admin manages other admins' accounts; everyone can still
    // manage their own.
    const canEdit = (admin: AdminUser) => {
        if (admin.id === currentAdmin.id) {
            return true;
        }

        if (admin.is_mother) {
            return false;
        }

        return currentAdmin.is_mother;
    };

    const canDelete = (admin: AdminUser) => {
        if (admin.id === currentAdmin.id) {
            return false;
        }

        if (admin.is_mother) {
            return false;
        }

        return currentAdmin.is_mother;
    };

    return (
        <>
            <Head title="Admin Accounts — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Admin Accounts
                        </h1>
                        <p className="text-sm text-slate-500">
                            {admins.length} total
                        </p>
                    </div>
                    {currentAdmin.is_mother && (
                        <Link href={route('admin.admins.create')}>
                            <Button className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                                <Plus className="size-4" />
                                New Admin
                            </Button>
                        </Link>
                    )}
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="divide-y divide-slate-100">
                        {admins.map((admin) => (
                            <div
                                key={admin.id}
                                className="flex items-center gap-4 px-4 py-3"
                            >
                                <div
                                    className={`flex size-9 shrink-0 items-center justify-center rounded-full ${admin.is_mother ? 'bg-amber-100' : 'bg-slate-100'}`}
                                >
                                    {admin.is_mother ? (
                                        <Shield className="size-4 text-amber-600" />
                                    ) : (
                                        <User className="size-4 text-slate-500" />
                                    )}
                                </div>

                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-semibold text-slate-800">
                                            {admin.name}
                                        </span>
                                        {admin.is_mother && (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold tracking-wide text-amber-700 uppercase">
                                                Mother Admin
                                            </span>
                                        )}
                                        {admin.id === currentAdmin.id && (
                                            <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-600">
                                                You
                                            </span>
                                        )}
                                        {!admin.is_mother &&
                                            admin.can_view_audit_trail && (
                                                <span
                                                    title="Can view Audit Trail"
                                                    className="flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-600"
                                                >
                                                    <History className="size-2.5" />
                                                    Audit Trail
                                                </span>
                                            )}
                                    </div>
                                    <p className="text-xs text-slate-400">
                                        {admin.email}
                                    </p>
                                </div>

                                <p className="hidden shrink-0 text-xs text-slate-400 sm:block">
                                    {new Date(
                                        admin.created_at,
                                    ).toLocaleDateString('en-BD', {
                                        dateStyle: 'medium',
                                    })}
                                </p>

                                <div className="flex shrink-0 items-center gap-1">
                                    {canEdit(admin) ? (
                                        <Link
                                            href={route(
                                                'admin.admins.edit',
                                                admin.id,
                                            )}
                                        >
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
                                            className="cursor-not-allowed rounded-lg p-1.5 text-slate-200"
                                        >
                                            <Edit className="size-4" />
                                        </button>
                                    )}

                                    {canDelete(admin) ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget(admin)
                                            }
                                            title="Delete"
                                            className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            disabled
                                            title={
                                                admin.is_mother
                                                    ? 'Cannot delete mother admin'
                                                    : 'Cannot delete your own account'
                                            }
                                            className="cursor-not-allowed rounded-lg p-1.5 text-slate-200"
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
                    <strong>Mother Admin</strong> is the root account and cannot
                    be deleted or modified by other admins. Deleted admin
                    accounts are immediately logged out.
                </div>
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Delete admin account?"
                description={
                    deleteTarget
                        ? `This will permanently delete "${deleteTarget.name}"'s account. They will be immediately logged out. This cannot be undone.`
                        : undefined
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </>
    );
}
