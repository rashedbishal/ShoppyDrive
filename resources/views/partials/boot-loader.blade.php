{{--
    The page-loading animation while the browser itself is still loading the
    page (a first visit, a refresh, a typed URL), before the JavaScript that
    draws PageLoadingOverlay has started. Without it the visitor sees the
    bare page background, e.g. the Glassmorphism theme's gradient. Same logo,
    ring, colours, sizes and speed as LoadingLogoSpinner.tsx (Admin ›
    Settings › General), in plain HTML and CSS. PageLoadingOverlay fades it
    out once the app is on screen; if the JavaScript never runs, it fades out
    by itself after 12 seconds so the page underneath is still usable.
--}}
@php
    $bootSettings = \App\Support\LayoutCache::settings();
    $bootLook = \App\Support\LoadingScreenSettings::forFrontend($bootSettings);
    $bootLogo = ($bootSettings['loading_logo'] ?? null) ?: ($bootSettings['logo'] ?? null);
    $bootLogo = is_string($bootLogo) && $bootLogo !== '' ? $bootLogo : null;
    $bootRing = (float) $bootLook['loading_ring_size'];
    $bootStroke = (float) $bootLook['loading_ring_thickness'];
    $bootRadius = ($bootRing - $bootStroke) / 2;
    $bootSeconds = (float) $bootLook['loading_spin_seconds'];
@endphp
<style>
    #boot-loader {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: oklch(1 0 0);
        transition: opacity 0.25s ease, visibility 0.25s;
        animation: boot-loader-give-up 0.4s ease 12s forwards;
    }
    html.dark #boot-loader {
        background-color: oklch(0.145 0 0);
    }
    #boot-loader.is-done {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    #boot-loader .boot-loader-mark {
        --spinner-size: min(86vw, clamp({{ $bootRing * 0.6 }}px, {{ $bootRing * 100 / 1024 }}vw, {{ $bootRing }}px));
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: var(--spinner-size);
        height: var(--spinner-size);
    }
    #boot-loader svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        filter: drop-shadow(0 6px 18px rgba(238, 89, 35, 0.35));
        animation: boot-loader-turn {{ $bootSeconds * 1.8 }}s linear infinite;
    }
    #boot-loader .boot-loader-arc {
        animation: boot-loader-draw {{ $bootSeconds * 1.35 }}s ease-in-out infinite;
    }
    #boot-loader img {
        position: relative;
        object-fit: contain;
        width: calc(var(--spinner-size) * {{ $bootLook['loading_logo_size'] / $bootRing }});
        height: calc(var(--spinner-size) * {{ $bootLook['loading_logo_size'] / $bootRing }});
    }
    @keyframes boot-loader-turn {
        to {
            transform: rotate(360deg);
        }
    }
    @keyframes boot-loader-draw {
        0% {
            stroke-dasharray: 1 100;
            stroke-dashoffset: 0;
        }
        50% {
            stroke-dasharray: 70 100;
            stroke-dashoffset: -25;
        }
        100% {
            stroke-dasharray: 70 100;
            stroke-dashoffset: -99;
        }
    }
    @keyframes boot-loader-give-up {
        to {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
    }
</style>
<noscript><style>#boot-loader { display: none; }</style></noscript>
<div id="boot-loader" role="status" aria-label="Loading">
    <span class="boot-loader-mark">
        <svg viewBox="0 0 {{ $bootRing }} {{ $bootRing }}" aria-hidden="true">
            <defs>
                <linearGradient id="boot-loader-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#F58120" />
                    <stop offset="100%" stop-color="#EE5923" />
                </linearGradient>
            </defs>
            <circle cx="{{ $bootRing / 2 }}" cy="{{ $bootRing / 2 }}" r="{{ $bootRadius }}" fill="none" stroke="#EE5923" stroke-opacity="0.12" stroke-width="{{ $bootStroke }}" />
            <circle class="boot-loader-arc" cx="{{ $bootRing / 2 }}" cy="{{ $bootRing / 2 }}" r="{{ $bootRadius }}" fill="none" stroke="url(#boot-loader-gradient)" stroke-width="{{ $bootStroke }}" stroke-linecap="round" pathLength="100" stroke-dasharray="1 100" transform="rotate(-90 {{ $bootRing / 2 }} {{ $bootRing / 2 }})" />
        </svg>
        @if($bootLogo)
            <img src="{{ $bootLogo }}" alt="" decoding="async" fetchpriority="high">
        @else
            <img src="/apple-touch-icon.png" alt="" decoding="async" fetchpriority="high">
        @endif
    </span>
</div>
