<?php

declare(strict_types=1);

/*
 * DISC-47 (owner request 2026-11-14): checkout fields show a thinner focus
 * ring, and the district/city dropdown list is not see-through in the
 * Glassmorphism theme. Source-level checks (no JavaScript test runner).
 */

test('every checkout field uses the thin focus ring', function () {
    $page = (string) file_get_contents(resource_path('js/pages/checkout/Checkout.tsx'));

    expect(substr_count($page, 'focus-visible:ring-1'))->toBe(10)
        ->and(substr_count($page, 'data-open:ring-1'))->toBe(2);
});

test('the dropdown list uses the theme popover colour and blur, like the dropdown menu', function () {
    $select = (string) file_get_contents(resource_path('js/components/ui/select.tsx'));

    expect($select)->toContain('bg-popover p-1')
        ->toContain('backdrop-blur-[var(--ui-blur)]');
});
