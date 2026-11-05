<?php

declare(strict_types=1);

/*
 * INFRA-001: deploys put the site in maintenance mode while files sync and
 * migrations run, then always take it out again, and server-to-server
 * endpoints stay reachable throughout.
 */

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Symfony\Component\Yaml\Yaml;

const MAINTENANCE_OPEN_ENDPOINTS = ['api/zinipay/webhook', 'api/steadfast/webhook', 'api/sms/receive'];

function deploySteps(): array
{
    $workflow = Yaml::parseFile(base_path('.github/workflows/deploy.yml'));

    return array_values(array_filter(
        $workflow['jobs']['deploy']['steps'],
        fn (array $step) => isset($step['name']),
    ));
}

function deployStepIndex(string $name): int
{
    $index = array_search($name, array_column(deploySteps(), 'name'), true);
    expect($index)->not->toBeFalse("Missing deploy step: {$name}");

    return $index;
}

beforeEach(function () {
    // The cache driver keeps the test from writing a real
    // storage/framework/down file that a running dev server would obey.
    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
});

afterEach(function () {
    $this->app->maintenanceMode()->deactivate();
});

test('the excluded endpoints are what artisan down records in the maintenance file', function () {
    // Laravel also keeps its own /up health route open.
    expect(app(PreventRequestsDuringMaintenance::class)->getExcludedPaths())
        ->toEqualCanonicalizing([...MAINTENANCE_OPEN_ENDPOINTS, '/up']);
});

test('during maintenance customers get 503 but webhooks and the SMS forwarder still reach the app', function () {
    $this->app->maintenanceMode()->activate(['except' => MAINTENANCE_OPEN_ENDPOINTS, 'status' => 503]);

    $this->get('/')->assertStatus(503);
    $this->get('/products')->assertStatus(503);

    $this->postJson('/api/sms/receive', [])->assertStatus(401);
    $this->postJson('/api/steadfast/webhook', [])->assertStatus(401);
    expect($this->postJson('/api/zinipay/webhook', [])->status())->not->toBe(503);
});

test('maintenance starts after the pre-deploy check and before files sync, with a pre-rendered page', function () {
    $steps = deploySteps();
    $enable = deployStepIndex('Enable maintenance mode');

    expect($enable)->toBeGreaterThan(deployStepIndex('Verify production runtime before deploying'))
        ->toBeLessThan(deployStepIndex('Sync files to server'))
        ->and($steps[$enable]['id'])->toBe('maintenance')
        ->and($steps[$enable]['run'])->toContain("php artisan down --render='errors::503'");
});

test('rsync never deletes the maintenance files mid-deploy', function () {
    $sync = deploySteps()[deployStepIndex('Sync files to server')]['run'];

    expect($sync)->toContain("--exclude='storage/framework/down'")
        ->toContain("--exclude='storage/framework/maintenance.php'");
});

test('the post-deploy script ends maintenance only after migrations and caches', function () {
    $script = deploySteps()[deployStepIndex('Run post-deploy tasks on server')]['run'];

    expect(strrpos($script, 'php artisan up'))->toBeGreaterThan(strrpos($script, 'php artisan migrate --force'))
        ->toBeGreaterThan(strrpos($script, 'php artisan event:cache'));
});

test('a failed deploy always brings the site back up, even if artisan cannot boot', function () {
    $recover = deploySteps()[deployStepIndex('Bring the site back up after a failed deploy')];

    expect($recover['if'])->toBe("failure() && steps.maintenance.outcome == 'success'")
        ->and($recover['run'])->toContain('php artisan up || rm -f storage/framework/down storage/framework/maintenance.php')
        ->and(deployStepIndex('Bring the site back up after a failed deploy'))
        ->toBeGreaterThan(deployStepIndex('Run post-deploy tasks on server'));
});
