<?php

return [
    'enabled' => env('LIPILA_ENABLED', false),
    'base_url' => env('LIPILA_BASE_URL', 'https://api.lipila.dev'),
    'secret_key' => env('LIPILA_SECRET_KEY', ''),
    'webhook_secret' => env('LIPILA_WEBHOOK_SECRET', ''),
    'callback_url' => env('LIPILA_CALLBACK_URL', 'https://admin.newworldcargo.com/api/v1/payments/lipila/webhook'),
    'return_url' => env('LIPILA_RETURN_URL', 'https://app.newworldcargo.com/shipments'),
    'checkout_hosts' => ['checkout.primenetpay.com'],
];
