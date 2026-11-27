<?php

declare(strict_types=1);

/*
 * F1 (JavaScript performance): libraries that every storefront page used to
 * download before it could render now load as their own chunks. These guard
 * the structure that makes that work; one stray import would silently put a
 * whole library back into every page's first download.
 */

function storefrontSource(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/'.$path);
}

/** @return list<string> storefront .ts/.tsx files (admin and the listed exceptions excluded), relative to resources/js */
function storefrontFiles(array $except = []): array
{
    $root = dirname(__DIR__, 2).'/resources/js/';
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        $path = str_replace($root, '', $file->getPathname());

        if (! preg_match('/\.tsx?$/', $path) || preg_match('#(^|/)admin(/|-)#', $path) || in_array($path, $except, true)) {
            continue;
        }

        $files[] = $path;
    }

    return $files;
}

test('F1-02: storefront code uses the lightweight `m` component, never the full `motion` one', function () {
    // Order and landing-page files were deliberately left as they were (protected areas); they only affect their own chunks.
    $allowed = ['pages/order/OrderConfirmation.tsx', 'pages/order/TrackOrder.tsx', 'components/landing/HeroCarousel.tsx'];
    $offenders = [];

    foreach (storefrontFiles($allowed) as $path) {
        if (preg_match('/import\s*\{([^}]*)\}\s*from\s*[\'"]framer-motion[\'"]/s', storefrontSource($path), $match)
            && in_array('motion', array_map(trim(...), explode(',', $match[1])), true)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('F1-02: animation features are provided once, lazily, for the whole app', function () {
    $app = storefrontSource('app.tsx');

    expect($app)->toContain('<LazyMotion features={loadMotionFeatures}>')
        ->toContain("import('@/lib/motion-features')")
        ->not->toContain('<LazyMotion strict')
        ->and(storefrontSource('lib/motion-features.ts'))->toContain("export { domMax as default } from 'framer-motion';");
});

test('F1-04: sonner, the Toaster and the account layout are not in the shared entry', function () {
    $app = storefrontSource('app.tsx');

    expect($app)->not->toContain("from 'sonner'")
        ->not->toContain("from '@/components/ui/sonner'")
        ->not->toContain('import AccountLayout from')
        ->toContain("import('@/components/ui/sonner')")
        ->toContain("await import('@/components/layout/AccountLayout')")
        ->toContain('<FlashToastListener />');
});

test('F1-04: storefront toasts go through the queue that waits for the lazy Toaster', function () {
    $direct = array_values(array_filter(
        storefrontFiles(['components/ui/sonner.tsx', 'lib/toast.ts', 'pages/checkout/Checkout.tsx']),
        fn (string $path): bool => (bool) preg_match('/from\s*[\'"]sonner[\'"]/', storefrontSource($path)),
    ));

    expect($direct)->toBe([])
        ->and(storefrontSource('lib/toast.ts'))->toContain('export function markToasterMounted(): void')
        ->and(storefrontSource('components/ui/sonner.tsx'))->toContain('markToasterMounted();')
        ->not->toContain('useFlashToast();');
});

test('F1-05: the category and product pages use the lazily loaded select', function (string $page) {
    $source = storefrontSource($page);

    expect($source)->toContain("import { LazySelect } from '@/components/ui/lazy-select';")
        ->not->toContain("from '@/components/ui/select'")
        ->not->toMatch('/<Select\b/');
})->with(['pages/catalog/ProductListing.tsx', 'pages/catalog/ProductDetail.tsx']);

test('F1-05: a click or opening key on the placeholder is resumed by the real select, never as Enter', function () {
    $source = storefrontSource('components/ui/lazy-select.tsx');

    expect($source)->toContain('function ResumeIntent(')
        ->toContain("const OPEN_KEYS = new Set([' ', 'ArrowDown', 'ArrowUp']);")
        ->toContain("key: ' ',")
        ->not->toContain("key: 'Enter',")
        ->toContain('.catch(() => ({ default: SelectPlaceholder }))');
});

test('F1-08: the sub-category row only animates while it is on screen', function () {
    $source = storefrontSource('components/shared/SubcategoryShowcase.tsx');

    expect($source)->toContain('new IntersectionObserver(')
        ->toContain('if (!shouldLoop || !isOnScreen) {')
        ->toContain('}, [shouldLoop, prefersReducedMotion, isOnScreen]);');
});
