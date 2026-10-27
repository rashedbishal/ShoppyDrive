<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This file was missing entirely, which left Laravel's built-in
    | HandleCors middleware permanently inert: with no "paths" to match,
    | it never ran for any request (web or api), so no Access-Control-*
    | headers were ever added. That's the most restrictive possible
    | outcome for browsers (nothing works cross-origin), not an exposure.
    |
    | No separate frontend/mobile-web origin is documented anywhere in this
    | codebase (.env, CLAUDE.md, routes) — this is a single-origin Inertia
    | monolith, and the only consumer of the api/* routes found in the
    | frontend (SearchBar.tsx -> /api/search/suggestions) calls it
    | same-origin, which browsers never subject to CORS at all.
    |
    | Rather than guess a production origin, "allowed_origins" defaults to
    | none (set via CORS_ALLOWED_ORIGINS, comma-separated, if a real
    | external browser consumer — e.g. a mobile app's webview, a separate
    | marketing site — is ever identified). Scope is limited to api/*: the
    | web/admin routes authenticate with session cookies + CSRF and must
    | never receive a permissive CORS treatment. The api/* routes never
    | rely on cookies for auth (webhooks are signature/HMAC-verified,
    | sms/receive is bearer-token verified, v1 endpoints are public reads),
    | so "supports_credentials" is correctly false — there is no session to
    | leak via a misconfigured Access-Control-Allow-Origin here either way.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
