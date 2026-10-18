<?php

declare(strict_types=1);

return [
    // Api Key / Secret Key are CMS-managed (admin Settings page), not env —
    // this file only holds the fixed, non-secret base URL.
    'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),

    // HTTP timeout in seconds for courier API requests
    'timeout' => (int) env('STEADFAST_TIMEOUT', 30),
];
