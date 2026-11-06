<?php

/*
 * SEC-016, PERF-002: visitors get no admin route names. Without this, every
 * page published all 200+ admin URLs and carried them twice, once in the HTML
 * and again in the JavaScript snapshot from `ziggy:generate`.
 *
 * `except` covers both of those. Admin pages ask for the `admin` group in
 * app.blade.php, and a group replaces `except`, so they get every route.
 */
return [
    'except' => ['admin.*', '_boost.*', 'boost.*', 'storage.*'],

    'groups' => [
        'admin' => ['*'],
    ],
];
