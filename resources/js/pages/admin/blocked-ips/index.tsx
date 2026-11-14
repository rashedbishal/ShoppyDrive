import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { Ban, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { AdminFade, AdminReveal } from '@/components/admin/admin-motion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { maxLength, required, validate } from '@/lib/validate';

type BlockedIp = {
    id: number;
    ip_address: string;
    reason: string | null;
    created_at: string;
    blocked_by_admin: { id: number; name: string } | null;
};

type Props = { blockedIps: BlockedIp[] };

const inputClass =
    'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-brand';

export default function BlockedIpsIndex({ blockedIps }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [unblockTarget, setUnblockTarget] = useState<BlockedIp | null>(null);
    const [unblocking, setUnblocking] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm({
        ip_address: '',
        reason: '',
    });

    const openNew = () => {
        reset();
        setFormOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = validate(data, {
            ip_address: [required(), maxLength(45)],
            reason: [maxLength(200)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.blocked-ips.store'), {
            onSuccess: () => {
                reset();
                setFormOpen(false);
            },
        });
    };

    const confirmUnblock = () => {
        if (!unblockTarget) {
            return;
        }

        setUnblocking(true);
        router.delete(route('admin.blocked-ips.destroy', unblockTarget.id), {
            onFinish: () => {
                setUnblocking(false);
                setUnblockTarget(null);
            },
        });
    };

    return (
        <>
            <Head title="Blocked IPs — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Blocked IPs
                        </h1>
                        <p className="text-sm text-slate-600">
                            Customers ordering from a blocked IP address can't
                            place new orders and see an error on checkout.
                        </p>
                    </div>
                    <AnimatePresence initial={false}>
                        {!formOpen && (
                            <AdminFade key="add-button">
                                <Button
                                    size="sm"
                                    onClick={openNew}
                                    className="bg-brand text-brand-foreground hover:bg-brand-hover"
                                >
                                    <Plus className="mr-1.5 size-4" />
                                    Block an IP
                                </Button>
                            </AdminFade>
                        )}
                    </AnimatePresence>
                </div>

                <AnimatePresence initial={false}>
                    {formOpen && (
                        <AdminReveal key="form">
                            <Card className="border-brand/20 shadow-sm">
                                <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100">
                                    <CardTitle className="text-sm font-semibold text-slate-700">
                                        Block a New IP Address
                                    </CardTitle>
                                    <button
                                        type="button"
                                        onClick={() => setFormOpen(false)}
                                        className="text-slate-400 hover:text-slate-700"
                                    >
                                        <X className="size-4" />
                                    </button>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    <form
                                        onSubmit={handleSubmit}
                                        className="space-y-3"
                                    >
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                IP Address *
                                            </label>
                                            <input
                                                maxLength={45}
                                                className={`${inputClass} font-mono`}
                                                value={data.ip_address}
                                                onChange={(e) =>
                                                    setData(
                                                        'ip_address',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. 203.0.113.5"
                                                required
                                            />
                                            <CharCount
                                                value={data.ip_address}
                                                max={45}
                                                className="mt-1 block text-right"
                                            />
                                            {errors.ip_address && (
                                                <p className="mt-0.5 text-xs text-red-500">
                                                    {errors.ip_address}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                Reason
                                                <span className="ml-1 font-normal text-slate-600">
                                                    — optional, visible to
                                                    admins only
                                                </span>
                                            </label>
                                            <input
                                                maxLength={200}
                                                className={inputClass}
                                                value={data.reason}
                                                onChange={(e) =>
                                                    setData(
                                                        'reason',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. Repeated fake COD orders"
                                            />
                                            <CharCount
                                                value={data.reason}
                                                max={200}
                                                className="mt-1 block text-right"
                                            />

                                            {errors.reason && (
                                                <p className="mt-0.5 text-xs text-red-500">
                                                    {errors.reason}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex justify-end gap-2 pt-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setFormOpen(false)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={processing}
                                                className="bg-brand text-brand-foreground hover:bg-brand-hover"
                                            >
                                                {processing
                                                    ? 'Blocking…'
                                                    : 'Block IP'}
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        </AdminReveal>
                    )}
                </AnimatePresence>

                <Card className="overflow-hidden p-0 shadow-sm">
                    {blockedIps.length === 0 ? (
                        <p className="py-12 text-center text-sm text-slate-600">
                            No IP addresses are blocked.
                        </p>
                    ) : (
                        <div className="divide-y divide-slate-50">
                            {blockedIps.map((blockedIp) => (
                                <div
                                    key={blockedIp.id}
                                    className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50/60"
                                >
                                    <Ban className="size-4 shrink-0 text-red-500" />
                                    <div className="min-w-0 flex-1 space-y-0.5">
                                        <p className="font-mono text-sm font-semibold text-slate-800">
                                            {blockedIp.ip_address}
                                        </p>
                                        {blockedIp.reason && (
                                            <p className="text-xs break-words text-slate-600">
                                                {blockedIp.reason}
                                            </p>
                                        )}
                                        <p className="text-[11px] text-slate-600">
                                            Blocked{' '}
                                            {new Date(
                                                blockedIp.created_at,
                                            ).toLocaleString()}
                                            {blockedIp.blocked_by_admin &&
                                                ` by ${blockedIp.blocked_by_admin.name}`}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setUnblockTarget(blockedIp)
                                        }
                                        className="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
            <ConfirmDialog
                open={unblockTarget !== null}
                onOpenChange={(open) => !open && setUnblockTarget(null)}
                title="Unblock IP address?"
                description={
                    unblockTarget
                        ? `Allow ${unblockTarget.ip_address} to place orders again?`
                        : undefined
                }
                confirmLabel="Unblock"
                processingLabel="Unblocking…"
                onConfirm={confirmUnblock}
                processing={unblocking}
            />
        </>
    );
}
