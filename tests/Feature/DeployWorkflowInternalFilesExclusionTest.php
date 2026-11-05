<?php

declare(strict_types=1);

/*
 * SEC-022: internal docs, tests and AI-agent config must never reach the
 * production server, and the courier release fingerprint must skip exactly
 * the same paths, or the server's on-disk fingerprint can never match the
 * one computed from git (`courier:release-fingerprint --sha=...`).
 */

use App\Services\CourierVerificationReadinessService;

function deployWorkflow(): string
{
    return file_get_contents(base_path('.github/workflows/deploy.yml'));
}

test('the rsync step excludes every internal path, anchored to the repo root', function () {
    preg_match_all("/--exclude='\\/([^']+)'/", deployWorkflow(), $matches);

    expect($matches[1])->toEqualCanonicalizing(CourierVerificationReadinessService::UNDEPLOYED_PATHS);
});

test('the post-deploy step deletes copies of every internal path that earlier deploys left on the server', function () {
    preg_match('/^\s*rm -rf (docs .+)$/m', deployWorkflow(), $match);

    expect($match)->not->toBeEmpty()
        ->and(explode(' ', trim($match[1])))->toEqualCanonicalizing(CourierVerificationReadinessService::UNDEPLOYED_PATHS);
});

test('the release fingerprint skips every path the deploy does not ship', function () {
    foreach (CourierVerificationReadinessService::UNDEPLOYED_PATHS as $path) {
        expect(CourierVerificationReadinessService::isExcluded($path))->toBeTrue()
            ->and(CourierVerificationReadinessService::isExcluded($path.'/nested/file.md'))->toBeTrue();
    }

    expect(CourierVerificationReadinessService::isExcluded('app/Models/Order.php'))->toBeFalse()
        ->and(CourierVerificationReadinessService::isExcluded('docsearch.php'))->toBeFalse();
});

test('every internal path in the list exists in the repository, so a rename cannot silently stop excluding it', function () {
    foreach (CourierVerificationReadinessService::UNDEPLOYED_PATHS as $path) {
        expect(file_exists(base_path($path)))->toBeTrue("{$path} no longer exists; update the deploy exclusions");
    }
});
