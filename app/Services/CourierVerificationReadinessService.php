<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CourierVerificationRefusedException;
use App\Models\Admin;
use App\Models\CourierVerificationReadiness;
use App\Support\MotherPasswordGate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use FilesystemIterator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * PAY-RETURN-001: the operational readiness attestation every courier
 * verification depends on. It is an AUDITED ATTESTATION, not a proof that
 * every old process has stopped: the app can check what is on disk (the
 * release fingerprint), when the last deploy rebuilt the caches (the
 * deployment marker), the one web process serving the request (OPcache
 * probe) and the scheduler (completion marker); everything else is the
 * mother admin's recorded confirmation. Every check is mandatory — any
 * missing, failed or contradictory input refuses the attestation.
 */
class CourierVerificationReadinessService
{
    public const SCHEDULER_MARKER_KEY = 'courier:pathao-sync:last-completed';

    /** A deploy's cache rebuild must fall inside its run window (+ this many seconds). */
    public const MARKER_WINDOW_SECONDS = 300;

    /**
     * Never part of the release fingerprint: secrets, runtime data and build
     * output that is not in git (vendor/, public/build/, generated route files).
     *
     * @var list<string>
     */
    public const EXCLUDED_PATHS = [
        '.git', '.github', '.env', 'node_modules', 'vendor', 'storage', 'bootstrap/cache',
        'public/build', 'public/storage', 'public/hot',
        'resources/js/ziggy.js', 'resources/js/actions', 'resources/js/routes',
    ];

    /** Directories whose cached bytecode the OPcache probe inspects. */
    private const PROBED_DIRECTORIES = ['app', 'bootstrap', 'config', 'routes'];

    /** @var array{fingerprint: string, count: int}|null */
    private ?array $liveFingerprint = null;

    public static function isExcluded(string $relativePath): bool
    {
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if ($relativePath === $excluded || str_starts_with($relativePath, $excluded.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * sha256 over the sorted list of "path\0sha256(bytes)\n".
     *
     * @param  array<string, string>  $hashes  relative path => sha256 of the file's bytes
     */
    public static function combine(array $hashes): string
    {
        ksort($hashes, SORT_STRING);
        $context = hash_init('sha256');

        foreach ($hashes as $path => $hash) {
            hash_update($context, $path."\0".$hash."\n");
        }

        return hash_final($context);
    }

    /**
     * @return array<string, string> relative path => sha256
     */
    public function fingerprintFiles(?string $root = null): array
    {
        $root = rtrim($root ?? base_path(), '/');
        $relative = fn (string $path): string => ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
        $hashes = [];

        // Excluded directories are pruned, never walked.
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $file): bool => ! self::isExcluded($relative($file->getPathname())),
        ));

        foreach ($iterator as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }

            $hashes[$relative($file->getPathname())] = hash_file('sha256', $file->getPathname());
        }

        return $hashes;
    }

    /**
     * @return array{fingerprint: string, count: int}
     */
    public function liveFingerprint(): array
    {
        if ($this->liveFingerprint === null) {
            $files = $this->fingerprintFiles();
            $this->liveFingerprint = ['fingerprint' => self::combine($files), 'count' => count($files)];
        }

        return $this->liveFingerprint;
    }

    /**
     * mtimes of the caches every deploy's post-deploy script rebuilds
     * (config:cache, route:cache). Null when the cache file does not exist.
     *
     * @return array{config: int|null, route: int|null}
     */
    public function deploymentMarker(): array
    {
        $mtime = fn (string $path): ?int => is_file($path) ? (int) filemtime($path) : null;

        return [
            'config' => $mtime(app()->getCachedConfigPath()),
            'route' => $mtime(app()->getCachedRoutesPath()),
        ];
    }

    /**
     * OPcache state of THIS (web) process only.
     *
     * @return array<string, mixed>
     */
    public function opcacheProbe(): array
    {
        $enabledSetting = PHP_SAPI === 'cli' ? 'opcache.enable_cli' : 'opcache.enable';
        $enabled = filter_var(ini_get($enabledSetting), FILTER_VALIDATE_BOOL);

        if (! function_exists('opcache_get_status')) {
            return $enabled
                ? ['available' => false, 'reason' => 'opcache_get_status() is not available.']
                : ['available' => true, 'enabled' => false];
        }

        try {
            $status = @opcache_get_status(true);
        } catch (Throwable $e) {
            return ['available' => false, 'reason' => 'opcache_get_status() failed: '.$e->getMessage()];
        }

        if ($status === false) {
            return $enabled
                ? ['available' => false, 'reason' => 'opcache_get_status() returned no data (restricted or disabled for this process).']
                : ['available' => true, 'enabled' => false];
        }

        $stale = [];
        $root = rtrim(base_path(), '/').'/';

        foreach ($status['scripts'] ?? [] as $path => $script) {
            $relative = str_starts_with((string) $path, $root) ? substr((string) $path, strlen($root)) : null;
            $probed = $relative !== null && in_array(explode('/', $relative)[0], self::PROBED_DIRECTORIES, true);

            if ($probed && is_file($path) && (int) ($script['timestamp'] ?? 0) < (int) filemtime($path)) {
                $stale[] = $relative;
            }
        }

        return [
            'available' => true,
            'enabled' => (bool) ($status['opcache_enabled'] ?? true),
            'start_time' => $status['opcache_statistics']['start_time'] ?? null,
            'last_restart_time' => $status['opcache_statistics']['last_restart_time'] ?? null,
            'validate_timestamps' => ini_get('opcache.validate_timestamps'),
            'revalidate_freq' => ini_get('opcache.revalidate_freq'),
            'stale_scripts' => $stale,
        ];
    }

    /**
     * @return array{fingerprint: string, started_at: string, finished_at: string}|null
     */
    public function schedulerMarker(): ?array
    {
        $marker = Cache::get(self::SCHEDULER_MARKER_KEY);

        return is_array($marker) ? $marker : null;
    }

    public function recordSchedulerCompletion(string $fingerprint, CarbonInterface $startedAt): void
    {
        Cache::forever(self::SCHEDULER_MARKER_KEY, [
            'fingerprint' => $fingerprint,
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    public function maxExecutionTime(): int
    {
        return (int) ini_get('max_execution_time');
    }

    /**
     * The accepted readiness row that still matches the live release: same
     * fingerprint and same deployment marker. Null when there is none — and
     * then nothing courier-related is refundable or verifiable.
     */
    public function current(): ?CourierVerificationReadiness
    {
        $latest = CourierVerificationReadiness::where('status', CourierVerificationReadiness::STATUS_ACCEPTED)->latest('id')->first();

        if ($latest === null) {
            return null;
        }

        $marker = $this->deploymentMarker();

        return $latest->fingerprint === $this->liveFingerprint()['fingerprint']
            && $marker['config'] !== null && $marker['route'] !== null
            && $latest->config_cache_mtime === $marker['config']
            && $latest->route_cache_mtime === $marker['route']
            ? $latest
            : null;
    }

    /**
     * Mother admin only, password required. Always writes a row — accepted,
     * or refused with every failed check — unless the password step fails.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws CourierVerificationRefusedException
     */
    public function attest(Admin $admin, Request $request, array $input): CourierVerificationReadiness
    {
        if (! $admin->is_mother) {
            abort(403, 'Only the mother admin can attest courier verification readiness.');
        }

        if (MotherPasswordGate::isLocked($admin)) {
            throw new CourierVerificationRefusedException(MotherPasswordGate::lockedMessage($admin), 'password');
        }

        if (! MotherPasswordGate::verify($admin, (string) ($input['password'] ?? ''))) {
            throw new CourierVerificationRefusedException('The password is incorrect.', 'password');
        }

        [$reasons, $record] = $this->evaluate($input);

        return CourierVerificationReadiness::create([
            ...$record,
            'status' => $reasons === [] ? CourierVerificationReadiness::STATUS_ACCEPTED : CourierVerificationReadiness::STATUS_REFUSED,
            'refusal_reasons' => $reasons === [] ? null : $reasons,
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    public function evaluate(array $input): array
    {
        $reasons = [];
        $now = CarbonImmutable::now();
        $live = $this->liveFingerprint();
        $marker = $this->deploymentMarker();
        $probe = $this->opcacheProbe();
        $scheduler = $this->schedulerMarker();
        $parse = function (mixed $value): ?CarbonImmutable {
            try {
                return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
            } catch (Throwable) {
                return null;
            }
        };

        $runId = trim((string) ($input['deploy_run_id'] ?? ''));
        $sha = strtolower(trim((string) ($input['deploy_sha'] ?? '')));
        $localFingerprint = strtolower(trim((string) ($input['local_fingerprint'] ?? '')));
        $runStarted = $parse($input['deploy_run_started_at'] ?? null);
        $runCompleted = $parse($input['deploy_run_completed_at'] ?? null);
        $webRestarted = $parse($input['web_restarted_at'] ?? null);
        $processList = trim((string) ($input['process_list'] ?? ''));
        $checklist = [
            'latest_successful_deploy_confirmed' => (bool) ($input['confirm_latest_deploy'] ?? false),
            'web_php_recycled' => (bool) ($input['confirm_web_restarted'] ?? false),
            'no_older_php_processes' => (bool) ($input['confirm_no_old_processes'] ?? false),
        ];

        // (a) deploy run identity (manual, as reported by `gh run view`)
        if ($runId === '' || preg_match('/^\d+$/', $runId) !== 1) {
            $reasons[] = 'Deploy run ID is missing or not numeric.';
        }
        if (preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            $reasons[] = 'Deployed commit SHA must be the full 40-character SHA.';
        }
        if ($runStarted === null || $runCompleted === null || $runCompleted->lt($runStarted)) {
            $reasons[] = 'Deploy run start/completion times are missing or contradictory.';
        }
        if (! $checklist['latest_successful_deploy_confirmed']) {
            $reasons[] = 'It was not confirmed that this is the latest successful deploy of main.';
        }

        // (b) fingerprint: the commit computed locally must equal what is on disk
        if (preg_match('/^[0-9a-f]{64}$/', $localFingerprint) !== 1) {
            $reasons[] = 'The locally computed release fingerprint is missing or malformed.';
        } elseif (! hash_equals($live['fingerprint'], $localFingerprint)) {
            $reasons[] = 'The locally computed fingerprint for that commit does not match the files on this server.';
        }

        // (c) deployment marker inside the run window
        if ($marker['config'] === null || $marker['route'] === null) {
            $reasons[] = 'The deployment marker is missing (config or route cache not built).';
        } elseif ($runStarted !== null && $runCompleted !== null) {
            foreach (['config' => 'Config', 'route' => 'Route'] as $key => $label) {
                if ($marker[$key] < $runStarted->getTimestamp() || $marker[$key] > $runCompleted->getTimestamp() + self::MARKER_WINDOW_SECONDS) {
                    $reasons[] = "{$label} cache was not rebuilt within the entered deploy run window.";
                }
            }
        }

        // (d)/(e) web restart after the deploy, and long enough ago
        if ($webRestarted === null) {
            $reasons[] = 'The web PHP restart time is missing.';
        } else {
            if ($runCompleted !== null && $webRestarted->lte($runCompleted)) {
                $reasons[] = 'The web PHP restart must be after the deploy completed.';
            }
            if ($webRestarted->gt($now)) {
                $reasons[] = 'The web PHP restart time is in the future.';
            }
            $limit = $this->maxExecutionTime();
            if ($limit <= 0) {
                $reasons[] = 'max_execution_time is unlimited, so an old request could still be running.';
            } elseif ($webRestarted->diffInSeconds($now, false) < $limit) {
                $reasons[] = "Wait at least {$limit} seconds (max_execution_time) after the web restart.";
            }
        }
        if (! $checklist['web_php_recycled']) {
            $reasons[] = 'It was not confirmed that web PHP processes were recycled after the deploy.';
        }
        if ($processList === '' || ! $checklist['no_older_php_processes']) {
            $reasons[] = 'The process list was not provided or it was not confirmed that no PHP process predates the restart.';
        }

        // (f) OPcache probe of this web process
        if (($probe['available'] ?? false) !== true) {
            $reasons[] = 'OPcache probe unavailable: '.($probe['reason'] ?? 'unknown reason').'.';
        } elseif (($probe['enabled'] ?? false) === true) {
            $cacheStart = max((int) ($probe['start_time'] ?? 0), (int) ($probe['last_restart_time'] ?? 0));
            if ($webRestarted === null || $cacheStart < $webRestarted->getTimestamp()) {
                $reasons[] = 'OPcache in this process was not (re)started after the web restart.';
            }
            if (($probe['stale_scripts'] ?? []) !== []) {
                $reasons[] = 'OPcache holds stale bytecode for: '.implode(', ', array_slice($probe['stale_scripts'], 0, 10)).'.';
            }
        }

        // (g) scheduler: a run of the current release completed after the restart
        $schedulerStarted = $parse($scheduler['started_at'] ?? null);
        if ($scheduler === null || ($scheduler['fingerprint'] ?? null) !== $live['fingerprint'] || $schedulerStarted === null || $parse($scheduler['finished_at'] ?? null) === null) {
            $reasons[] = 'No completed pathao:sync-statuses run of the current release is recorded.';
        } elseif ($webRestarted !== null && $schedulerStarted->lt($webRestarted)) {
            $reasons[] = 'The last completed pathao:sync-statuses run started before the web restart.';
        }

        return [$reasons, [
            'fingerprint' => $live['fingerprint'],
            'local_fingerprint' => $localFingerprint === '' ? null : $localFingerprint,
            'fingerprint_file_count' => $live['count'],
            'deploy_run_id' => $runId === '' ? null : mb_substr($runId, 0, 40),
            'deploy_sha' => $sha === '' ? null : mb_substr($sha, 0, 40),
            'deploy_run_started_at' => $runStarted,
            'deploy_run_completed_at' => $runCompleted,
            'config_cache_mtime' => $marker['config'],
            'route_cache_mtime' => $marker['route'],
            'web_restarted_at' => $webRestarted,
            'opcache_probe' => $probe,
            'scheduler_marker' => $scheduler,
            'process_list' => $processList === '' ? null : mb_substr($processList, 0, 20000),
            'checklist' => $checklist,
            'note' => isset($input['note']) ? mb_substr((string) $input['note'], 0, 2000) : null,
        ]];
    }
}
