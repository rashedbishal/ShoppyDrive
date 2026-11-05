<?php

declare(strict_types=1);

use App\Models\CourierVerificationReadiness;
use App\Services\CourierVerificationReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\actingAs;

/**
 * PAY-RETURN-001: the readiness attestation is mandatory-check-only — any
 * missing, failing or contradictory input refuses it (and is recorded).
 */
function readinessScenario(array $probe = [], array $scheduler = []): array
{
    $now = CarbonImmutable::now();
    $completed = $now->subMinutes(55);
    fakeCourierReadiness(array_merge(['marker' => ['config' => $completed->subSeconds(30)->getTimestamp(), 'route' => $completed->subSeconds(20)->getTimestamp()]], $probe));
    Cache::forever(CourierVerificationReadinessService::SCHEDULER_MARKER_KEY, array_merge([
        'fingerprint' => str_repeat('a', 64),
        'started_at' => $now->subMinutes(5)->toIso8601String(),
        'finished_at' => $now->subMinutes(4)->toIso8601String(),
    ], $scheduler));

    return [
        'password' => 'password',
        'deploy_run_id' => '39128251932',
        'deploy_sha' => str_repeat('b', 40),
        'deploy_run_started_at' => $now->subHour()->toDateTimeString(),
        'deploy_run_completed_at' => $completed->toDateTimeString(),
        'local_fingerprint' => str_repeat('a', 64),
        'web_restarted_at' => $now->subMinutes(10)->toDateTimeString(),
        'process_list' => '  PID STARTED CMD',
        'confirm_latest_deploy' => '1',
        'confirm_web_restarted' => '1',
        'confirm_no_old_processes' => '1',
    ];
}

test('a complete, consistent attestation is accepted and becomes current', function () {
    $input = readinessScenario();

    actingAs($mother = createMotherAdmin(), 'admin')->post(route('admin.courierReadiness.store'), $input)->assertSessionHas('success');

    $row = CourierVerificationReadiness::sole();
    expect($row->status)->toBe(CourierVerificationReadiness::STATUS_ACCEPTED)
        ->and($row->admin_id)->toBe($mother->id)
        ->and($row->deploy_sha)->toBe(str_repeat('b', 40))
        ->and(app(CourierVerificationReadinessService::class)->current()?->id)->toBe($row->id);
});

test('any failing or contradictory check refuses the attestation and records why', function (Closure $mutate, string $reason) {
    $input = readinessScenario();
    [$input, $probe, $scheduler] = $mutate($input);
    if ($probe !== null || $scheduler !== null) {
        readinessScenario($probe ?? [], $scheduler ?? []);
    }

    actingAs(createMotherAdmin(), 'admin')->post(route('admin.courierReadiness.store'), $input)->assertSessionHas('error');

    $row = CourierVerificationReadiness::sole();
    expect($row->status)->toBe(CourierVerificationReadiness::STATUS_REFUSED)
        ->and(implode(' ', $row->refusal_reasons))->toContain($reason)
        ->and(app(CourierVerificationReadinessService::class)->current())->toBeNull();
})->with([
    'local fingerprint differs' => [fn ($i) => [array_merge($i, ['local_fingerprint' => str_repeat('f', 64)]), null, null], 'does not match'],
    'SHA too short' => [fn ($i) => [array_merge($i, ['deploy_sha' => 'abc1234']), null, null], '40-character'],
    'run completed before it started' => [fn ($i) => [array_merge($i, ['deploy_run_completed_at' => now()->subHours(2)->toDateTimeString()]), null, null], 'contradictory'],
    'marker missing' => [fn ($i) => [$i, ['marker' => ['config' => null, 'route' => 1]], null], 'deployment marker is missing'],
    'marker outside the run window' => [fn ($i) => [$i, ['marker' => ['config' => now()->subDays(2)->getTimestamp(), 'route' => now()->subDays(2)->getTimestamp()]], null], 'run window'],
    'restart before the deploy completed' => [fn ($i) => [array_merge($i, ['web_restarted_at' => now()->subMinutes(58)->toDateTimeString()]), null, null], 'after the deploy completed'],
    'restart too recent' => [fn ($i) => [array_merge($i, ['web_restarted_at' => now()->subSeconds(5)->toDateTimeString()]), null, ['started_at' => now()->toIso8601String()]], 'max_execution_time'],
    'opcache unavailable' => [fn ($i) => [$i, ['opcache' => ['available' => false, 'reason' => 'restricted']], null], 'OPcache probe unavailable'],
    'opcache started before the restart' => [fn ($i) => [$i, ['opcache' => ['available' => true, 'enabled' => true, 'start_time' => now()->subDay()->getTimestamp(), 'last_restart_time' => 0, 'stale_scripts' => []]], null], 'not (re)started'],
    'opcache holds stale bytecode' => [fn ($i) => [$i, ['opcache' => ['available' => true, 'enabled' => true, 'start_time' => now()->getTimestamp(), 'last_restart_time' => 0, 'stale_scripts' => ['app/Services/CourierOrderStatusMapper.php']]], null], 'stale bytecode'],
    'scheduler run of another release' => [fn ($i) => [$i, [], ['fingerprint' => str_repeat('d', 64)]], 'No completed pathao:sync-statuses run'],
    'scheduler run before the restart' => [fn ($i) => [$i, [], ['started_at' => now()->subMinutes(30)->toIso8601String()]], 'started before the web restart'],
    'unlimited max_execution_time' => [fn ($i) => [$i, ['max_execution_time' => 0], null], 'unlimited'],
]);

test('the attestation needs every manual confirmation and the process list', function () {
    $input = readinessScenario();
    unset($input['confirm_no_old_processes'], $input['process_list']);

    actingAs(createMotherAdmin(), 'admin')->post(route('admin.courierReadiness.store'), $input)
        ->assertSessionHasErrors(['confirm_no_old_processes', 'process_list']);
    expect(CourierVerificationReadiness::count())->toBe(0);
});

test('a wrong password writes no attestation', function () {
    actingAs(createMotherAdmin(), 'admin')->post(route('admin.courierReadiness.store'), array_merge(readinessScenario(), ['password' => 'wrong']))
        ->assertSessionHasErrors('password');
    expect(CourierVerificationReadiness::count())->toBe(0);
});

test('the release fingerprint covers deployed tracked files and nothing excluded', function () {
    $root = storage_path('framework/testing/fingerprint-'.uniqid());
    $write = function (string $path, string $content = 'x') use ($root): void {
        File::ensureDirectoryExists(dirname("{$root}/{$path}"));
        File::put("{$root}/{$path}", $content);
    };
    foreach (['app/A.php', 'resources/js/pages/x.tsx', 'composer.json', 'artisan', 'routes/web.php', 'public/robots.txt'] as $included) {
        $write($included);
    }
    foreach (['.env', 'vendor/v.php', 'storage/logs/l.log', 'bootstrap/cache/config.php', 'public/build/m.json', 'resources/js/ziggy.js', 'resources/js/actions/a.ts', 'node_modules/n.js', 'docs/n.md', 'tests/T.php', 'CLAUDE.md'] as $excluded) {
        $write($excluded);
    }

    $service = new CourierVerificationReadinessService;
    $files = $service->fingerprintFiles($root);
    expect(array_keys($files))->toEqualCanonicalizing(['app/A.php', 'resources/js/pages/x.tsx', 'composer.json', 'artisan', 'routes/web.php', 'public/robots.txt']);

    $before = CourierVerificationReadinessService::combine($files);
    $write('resources/js/pages/x.tsx', 'changed');
    expect(CourierVerificationReadinessService::combine($service->fingerprintFiles($root)))->not->toBe($before);

    $write('vendor/v.php', 'changed');
    $write('resources/js/ziggy.js', 'changed');
    $afterExcluded = CourierVerificationReadinessService::combine($service->fingerprintFiles($root));
    $write('resources/js/pages/x.tsx', 'x');
    expect(CourierVerificationReadinessService::combine($service->fingerprintFiles($root)))->toBe($before)
        ->and($afterExcluded)->not->toBe($before);

    File::deleteDirectory($root);
});
