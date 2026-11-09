<?php

test('SEC-016: the app entry loads admin pages and their layout only on demand', function () {
    $entry = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.tsx');

    expect($entry)->not->toContain("from '@/layouts/admin-layout'")
        ->and($entry)->toContain("'!./pages/admin/**'")
        ->and($entry)->toContain("await import('@/lib/admin-pages')");
});
