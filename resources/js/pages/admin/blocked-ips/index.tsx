import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { Ban, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { AdminFade, AdminReveal } from '@/components/admin/admin-motion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

export default function BlockedIpsIndex({ blockedIps }: Props) {
    const [formOpen, setFormOpen] = useState(false);

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
            reason: [maxLength(255)],
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

    const handleUnblock = (blockedIp: BlockedIp) => {
        if (!confirm(`Unblock ${blockedIp.ip_address}?`)) {
            return;
        }

        router.delete(route('admin.blocked-ips.destroy', blockedIp.id));
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
                        <p className="text-sm text-slate-500">
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
                                    className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
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
                            <Card className="border-[#c8102e]/20 shadow-sm">
                                <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-4">
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
                                <CardContent className="pt-4">
                                    <form
                                        onSubmit={handleSubmit}
                                        className="space-y-3"
                                    >
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                IP Address *
                                            </label>
                                            <input
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
                                            {errors.ip_address && (
                                                <p className="mt-0.5 text-xs text-red-500">
                                                    {errors.ip_address}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                Reason
                                                <span className="ml-1 font-normal text-slate-400">
                                                    — optional, visible to
                                                    admins only
                                                </span>
                                            </label>
                                            <input
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
                                                className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
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
                        <p className="py-12 text-center text-sm text-slate-400">
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
                                            <p className="text-xs text-slate-500">
                                                {blockedIp.reason}
                                            </p>
                                        )}
                                        <p className="text-[11px] text-slate-400">
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
                                        onClick={() => handleUnblock(blockedIp)}
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
        </>
    );
}
