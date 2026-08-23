<?php

return [
    'prefix' => 'api/v1',

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CUSTOMER_PORTAL_API_ALLOWED_ORIGINS', ''))))),

    'max_per_page' => (int) env('CUSTOMER_PORTAL_API_MAX_PER_PAGE', 50),

    'api_rate_limit' => (int) env('CUSTOMER_PORTAL_API_RATE_LIMIT', 60),

    'request_id_header' => 'X-Request-ID',

    'csrf_cookie' => 'nwc_csrf',

    'csrf_header' => 'X-CSRF-Token',

    'cookie_secure' => (bool) env('CUSTOMER_PORTAL_COOKIE_SECURE', false),

    'cookie_same_site' => env('CUSTOMER_PORTAL_COOKIE_SAME_SITE', 'lax'),

    'public_tracking_per_minute' => (int) env('CUSTOMER_PORTAL_PUBLIC_TRACKING_RATE', 30),
];
