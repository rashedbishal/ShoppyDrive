<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
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
        <script>
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

        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $seo['title'] ?? config('app.name', 'Laravel') }}</title>
        </x-inertia::head>

        {{-- FE-006: rendered here (not only via Inertia's client-side <Head>) so the first response — what curl, Facebook's scraper and search crawlers see — already carries real tags, independent of SSR. --}}
        @isset($seo)
            @if(! empty($seo['description']))
                <meta name="description" content="{{ $seo['description'] }}">
            @endif
            @if(! empty($seo['canonical']))
                <link rel="canonical" href="{{ $seo['canonical'] }}">
            @endif
            <meta property="og:type" content="product">
            <meta property="og:title" content="{{ $seo['title'] }}">
            @if(! empty($seo['description']))
                <meta property="og:description" content="{{ $seo['description'] }}">
            @endif
            @if(! empty($seo['canonical']))
                <meta property="og:url" content="{{ $seo['canonical'] }}">
            @endif
            @if(! empty($seo['image']))
                <meta property="og:image" content="{{ $seo['image'] }}">
            @endif
            @if(! empty($seo['jsonLd']))
                <script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_SLASHES) !!}</script>
            @endif
        @endisset
    </head>
    <body class="antialiased">
        <x-inertia::app />
    </body>
</html>
