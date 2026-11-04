<?php

declare(strict_types=1);

/*
 * Guards against a future edit silently dropping the private-storage
 * exclusion from the rsync deploy step. `deploy.yml` syncs with `--delete`,
 * so anything not excluded (and not present in the just-built local tree)
 * is deleted on the server — including in-flight newsletter attachments
 * under storage/app/private (FSA-13) that only ever exist on the server,
 * never in the repo being synced.
 */

use Symfony\Component\Yaml\Yaml;

test('the deploy workflow is valid YAML', function () {
    $parsed = Yaml::parseFile(base_path('.github/workflows/deploy.yml'));

    expect($parsed)->toBeArray();
});

test('the rsync deploy step excludes storage/app/private from deletion', function () {
    $contents = file_get_contents(base_path('.github/workflows/deploy.yml'));

    expect($contents)->toContain("--exclude='storage/app/private/*'");
});

test('the rsync deploy step still excludes the other pre-existing paths', function () {
    $contents = file_get_contents(base_path('.github/workflows/deploy.yml'));

    foreach ([
        '.git',
        '.github',
        'node_modules',
        '.env',
        'storage/app/public/*',
        'storage/framework/cache/data/*',
        'storage/framework/sessions/*',
        'storage/framework/views/*',
        'storage/logs/*',
        'bootstrap/cache/*.php',
    ] as $excluded) {
        expect($contents)->toContain("--exclude='{$excluded}'");
    }
});

test('the rsync deploy step still uses --delete, not a broad storage exclusion', function () {
    $contents = file_get_contents(base_path('.github/workflows/deploy.yml'));

    expect($contents)->toContain('--delete')
        ->not->toContain("--exclude='storage/*'")
        ->not->toContain("--exclude='storage'");
});
