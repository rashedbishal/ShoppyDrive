import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { Input } from '@/components/ui/input';

type Props = {
    live: {
        fingerprint: string;
        file_count: number;
        deployment_marker: { config: number | null; route: number | null };
        opcache: Record<string, unknown>;
        scheduler_marker: {
            fingerprint: string;
            started_at: string;
            finished_at: string;
        } | null;
        max_execution_time: number;
    };
    current: {
        id: number;
        created_at: string;
        admin_name: string | null;
        deploy_sha: string | null;
        deploy_run_id: string | null;
    } | null;
    recent: {
        id: number;
        status: 'accepted' | 'refused';
        refusal_reasons: string[] | null;
        deploy_run_id: string | null;
        deploy_sha: string | null;
        admin_name: string | null;
        created_at: string;
    }[];
};

const marker = (value: number | null) =>
    value === null ? 'missing' : new Date(value * 1000).toISOString();

/**
 * PAY-RETURN-001: the mother admin's operational readiness attestation for
 * the deployed release — an audited attestation, not a proof. Every check
 * is mandatory; the server refuses (and records) anything missing or
 * contradictory.
 */
export default function CourierReadiness({ live, current, recent }: Props) {
    const form = useForm({
        deploy_run_id: '',
        deploy_sha: '',
        deploy_run_started_at: '',
        deploy_run_completed_at: '',
        local_fingerprint: '',
        web_restarted_at: '',
        process_list: '',
        confirm_latest_deploy: false,
        confirm_web_restarted: false,
        confirm_no_old_processes: false,
        note: '',
        password: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const check = (
        field:
            | 'confirm_latest_deploy'
            | 'confirm_web_restarted'
            | 'confirm_no_old_processes',
        label: string,
    ) => (
        <label className="flex items-start gap-2 text-xs text-slate-700">
            <input
                type="checkbox"
                checked={form.data[field]}
                onChange={(e) => form.setData(field, e.target.checked)}
            />
            {label}
        </label>
    );

    return (
        <>
            <Head title="Courier verification readiness — Admin" />
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Courier verification readiness
                    </h1>
                    <p className="text-sm text-slate-500">
                        Required after every production deployment before any
                        courier consignment can be verified or a courier-handled
                        order refunded. This records your attestation; it does
                        not prove that every old process has stopped.
                    </p>
                </div>

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            This server, now
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1 pt-3 text-xs text-slate-600">
                        <p>
                            Release fingerprint:{' '}
                            <code className="break-all">
                                {live.fingerprint}
                            </code>{' '}
                            ({live.file_count} files)
                        </p>
                        <p>
                            Deployment marker — config cache:{' '}
                            {marker(live.deployment_marker.config)}, route
                            cache: {marker(live.deployment_marker.route)}
                        </p>
                        <p>
                            OPcache (this web process):{' '}
                            {JSON.stringify(live.opcache)}
                        </p>
                        <p>
                            Last pathao:sync-statuses run:{' '}
                            {live.scheduler_marker
                                ? `${live.scheduler_marker.started_at} → ${live.scheduler_marker.finished_at}${live.scheduler_marker.fingerprint === live.fingerprint ? '' : ' (different release!)'}`
                                : 'none recorded'}
                        </p>
                        <p>max_execution_time: {live.max_execution_time}s</p>
                        <p className="font-semibold">
                            {current
                                ? `Current readiness #${current.id} by ${current.admin_name} (${current.deploy_sha}).`
                                : 'No current readiness for this release.'}
                        </p>
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Attest readiness
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="pt-3">
                        {errors.password && (
                            <p className="mb-3 rounded-lg border border-red-200 bg-red-50 p-2 text-xs text-red-700">
                                {errors.password}
                            </p>
                        )}
                        <form
                            className="grid gap-3 md:grid-cols-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(
                                    route('admin.courierReadiness.store'),
                                    {
                                        preserveScroll: true,
                                        onFinish: () => form.reset('password'),
                                    },
                                );
                            }}
                        >
                            <Input
                                maxLength={40}
                                label="Deploy run ID (gh run view)"
                                value={form.data.deploy_run_id}
                                onChange={(e) =>
                                    form.setData(
                                        'deploy_run_id',
                                        e.target.value,
                                    )
                                }
                                error={errors.deploy_run_id}
                            />
                            <Input
                                maxLength={40}
                                label="Deployed commit SHA (40 characters)"
                                value={form.data.deploy_sha}
                                onChange={(e) =>
                                    form.setData('deploy_sha', e.target.value)
                                }
                                error={errors.deploy_sha}
                            />
                            <Input
                                type="datetime-local"
                                step="1"
                                label="Deploy run started (UTC)"
                                value={form.data.deploy_run_started_at}
                                onChange={(e) =>
                                    form.setData(
                                        'deploy_run_started_at',
                                        e.target.value,
                                    )
                                }
                                error={errors.deploy_run_started_at}
                            />
                            <Input
                                type="datetime-local"
                                step="1"
                                label="Deploy run completed (UTC)"
                                value={form.data.deploy_run_completed_at}
                                onChange={(e) =>
                                    form.setData(
                                        'deploy_run_completed_at',
                                        e.target.value,
                                    )
                                }
                                error={errors.deploy_run_completed_at}
                            />
                            <Input
                                maxLength={64}
                                label="Fingerprint computed locally (courier:release-fingerprint --sha=…)"
                                value={form.data.local_fingerprint}
                                onChange={(e) =>
                                    form.setData(
                                        'local_fingerprint',
                                        e.target.value,
                                    )
                                }
                                error={errors.local_fingerprint}
                            />
                            <Input
                                type="datetime-local"
                                step="1"
                                label="Web PHP restarted at (UTC)"
                                value={form.data.web_restarted_at}
                                onChange={(e) =>
                                    form.setData(
                                        'web_restarted_at',
                                        e.target.value,
                                    )
                                }
                                error={errors.web_restarted_at}
                            />
                            <label className="block text-xs font-semibold text-slate-700 md:col-span-2">
                                Output of{' '}
                                <code>
                                    ps -eo pid,lstart,cmd | grep -E
                                    'artisan|lsphp|php-fpm'
                                </code>
                                <textarea
                                    maxLength={20000}
                                    rows={5}
                                    className="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono text-xs"
                                    value={form.data.process_list}
                                    onChange={(e) =>
                                        form.setData(
                                            'process_list',
                                            e.target.value,
                                        )
                                    }
                                />
                                <CharCount
                                    value={form.data.process_list}
                                    max={20000}
                                    className="mt-1 block text-right"
                                />
                            </label>
                            <div className="space-y-2 md:col-span-2">
                                {check(
                                    'confirm_latest_deploy',
                                    'This is the latest successful deploy of main (checked with gh run list).',
                                )}
                                {check(
                                    'confirm_web_restarted',
                                    'Web PHP processes were recycled after that deploy.',
                                )}
                                {check(
                                    'confirm_no_old_processes',
                                    'The process list shows no PHP process started before the web restart.',
                                )}
                            </div>
                            <Input
                                maxLength={2000}
                                label="Note (optional)"
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
                                }
                                error={errors.note}
                            />
                            <Input
                                maxLength={255}
                                type="password"
                                label="Your password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                            />
                            <Button
                                type="submit"
                                className="md:col-span-2"
                                disabled={
                                    form.processing || !form.data.password
                                }
                            >
                                Submit attestation
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Recent attestations
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 pt-3 text-xs text-slate-600">
                        {recent.length === 0 && <p>None yet.</p>}
                        {recent.map((r) => (
                            <div key={r.id}>
                                <p className="font-semibold">
                                    #{r.id} {r.status} — {r.admin_name} —{' '}
                                    {r.created_at} — {r.deploy_sha}
                                </p>
                                {(r.refusal_reasons ?? []).map((reason) => (
                                    <p key={reason} className="text-amber-700">
                                        {reason}
                                    </p>
                                ))}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
