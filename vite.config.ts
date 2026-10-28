import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],

    server: {
        host: '192.168.0.113',
        port: 5173,
        // Vite's default CORS allowlist only recognizes literal
        // localhost/127.0.0.1 origins. The app is served over the LAN IP
        // above (so it's reachable from other devices on the network), so
        // that origin needs to be trusted too - this only affects the dev
        // server; `vite build` for production doesn't read `server` at all.
        cors: {
            origin: [
                /^https?:\/\/localhost(:\d+)?$/,
                /^https?:\/\/127\.0\.0\.1(:\d+)?$/,
                /^https?:\/\/192\.168\.\d{1,3}\.\d{1,3}(:\d+)?$/,
            ],
        },
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
            // Native fs events (inotify) are unreliable for this project's
            // directory, so file edits can go undetected until the dev
            // server is restarted. Polling trades a little CPU for actually
            // noticing changes. A longer interval reduces how often rapid,
            // overlapping poll events trip Vite's module-invalidation race
            // ("Soft-invalidated module ... should not have existing
            // transform result").
            usePolling: true,
            interval: 800,
        },
    },
});
