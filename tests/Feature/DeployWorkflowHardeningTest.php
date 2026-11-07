<?php

declare(strict_types=1);

/**
 * @return array<string, string>
 */
function workflowFiles(): array
{
    return collect(glob(base_path('.github/workflows/*.yml')))
        ->mapWithKeys(fn (string $path): array => [basename($path) => (string) file_get_contents($path)])
        ->all();
}

/*
 * INFRA-004: a version name can be moved to other code by whoever controls
 * that tool. A commit hash can't, and these tools run next to the deploy key.
 */
test('every workflow pins outside tools to an exact commit', function () {
    foreach (workflowFiles() as $name => $workflow) {
        preg_match_all('/^\s*(?:- )?uses:\s*(\S+)/m', $workflow, $matches);

        expect($matches[1])->not->toBeEmpty("{$name} has no steps to check");

        foreach ($matches[1] as $action) {
            expect($action)->toMatch('/@[0-9a-f]{40}$/', "{$name}: {$action} is not pinned");
        }
    }
});

test('every workflow has read-only repository access', function () {
    foreach (workflowFiles() as $name => $workflow) {
        expect($workflow)->toMatch('/^permissions:\n  contents: read$/m', "{$name} has no permissions block");
    }
});

/*
 * INFRA-005: these checks must warn. They run after the files are synced, so
 * a hard stop would leave a deploy half finished.
 */
test('the deploy warns about missing mail settings without stopping', function () {
    $workflow = workflowFiles()['deploy.yml'];
    $check = str($workflow)->after('for key in MAIL_MAILER')->before('# SEC-022: remove internal files')->toString();

    expect($check)->toContain('::warning::')->not->toContain('exit 1')
        ->and($workflow)->toContain('for key in MAIL_MAILER MAIL_HOST MAIL_USERNAME MAIL_PASSWORD;');
});

test('the env template names the settings a rebuilt server needs', function (string $key) {
    expect((string) file_get_contents(base_path('.env.example')))->toMatch("/^{$key}=/m");
})->with(['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI', 'FACEBOOK_CLIENT_ID', 'FACEBOOK_CLIENT_SECRET', 'FACEBOOK_REDIRECT_URI']);
