<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline script to apply the visitor's saved storefront theme before paint, so there's no flash of the default theme. Purely client-side — no backend involved. --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                try {
                    var theme = localStorage.getItem('site_theme');
                    if (theme && theme !== 'default') {
                        document.documentElement.setAttribute('data-theme', theme);
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=DM+Mono:wght@400;500&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

        @routes(request()->is('admin', 'admin/*') || auth('admin')->check() ? 'admin' : null, Vite::cspNonce())
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $seo['title'] ?? config('app.name') }}</title>
        </x-inertia::head>

        {{-- FE-006: rendered here (not only via Inertia's client-side <Head>) so the first response — what curl, Facebook's scraper and search crawlers see — already carries real tags, independent of SSR. --}}
        @php($seoTags = \App\Support\PageSeo::tags($seo ?? []))
        <meta name="description" content="{{ $seoTags['description'] }}">
        @if($seoTags['canonical'])
            <link rel="canonical" href="{{ $seoTags['canonical'] }}">
            <meta property="og:url" content="{{ $seoTags['canonical'] }}">
        @endif
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:type" content="{{ $seoTags['type'] }}">
        <meta property="og:title" content="{{ $seoTags['title'] }}">
        <meta property="og:description" content="{{ $seoTags['description'] }}">
        <meta property="og:image" content="{{ $seoTags['image'] }}">
        @if($seoTags['jsonLd'])
            <script type="application/ld+json">{!! json_encode($seoTags['jsonLd'], JSON_UNESCAPED_SLASHES) !!}</script>
        @endif

        {{-- Custom Header: the admin pastes any vendor's code verbatim (Admin
        › Marketing & Tracking), not just Facebook's, so a future vendor or a
        change to an existing one's code needs no deploy. Rendered unescaped,
        exactly as given. Storefront only — not the admin back office. --}}
        @php($customHeaderCode = \App\Support\LayoutCache::settings()['custom_header_code'] ?? null)
        @if($customHeaderCode && !request()->is('admin', 'admin/*'))
            {!! \App\Support\CspNonce::addToScripts($customHeaderCode) !!}
        @endif
    </head>
    <body class="antialiased">
        <x-inertia::app />

        {{-- Custom Footer: same as Custom Header above, rendered just before
        </body> — the standard "footer scripts" placement. --}}
        @php($customFooterCode = \App\Support\LayoutCache::settings()['custom_footer_code'] ?? null)
        @if($customFooterCode && !request()->is('admin', 'admin/*'))
            {!! \App\Support\CspNonce::addToScripts($customFooterCode) !!}
        @endif
    </body>
</html>
