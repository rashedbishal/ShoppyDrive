<?php

declare(strict_types=1);

return [
    // Credentials and store_id are CMS-managed (admin Settings page), not env —
    // this file only holds the fixed, non-secret base URLs per environment.
    'sandbox_base_url' => env('PATHAO_SANDBOX_BASE_URL', 'https://courier-api-sandbox.pathao.com'),
    'production_base_url' => env('PATHAO_PRODUCTION_BASE_URL', 'https://api-hermes.pathao.com'),

    // HTTP timeout in seconds for courier API requests
    'timeout' => (int) env('PATHAO_TIMEOUT', 30),

    // Pathao's own delivery/item type codes (see their API docs)
    'delivery_type_normal' => 48,
    'delivery_type_on_demand' => 12,
    'item_type_document' => 1,
    'item_type_parcel' => 2,

    'min_item_weight' => 0.5,
    'max_item_weight' => 10.0,
];
