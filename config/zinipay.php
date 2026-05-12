<?php

declare(strict_types=1);

return [
    'api_key' => env('ZINIPAY_API_KEY', ''),
    'base_url' => env('ZINIPAY_BASE_URL', 'https://api.zinipay.com'),

    // HTTP timeout in seconds for gateway requests
    'timeout' => (int) env('ZINIPAY_TIMEOUT', 30),
];
