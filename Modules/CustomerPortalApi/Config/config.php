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

    'bff_service_token' => env('CUSTOMER_PORTAL_BFF_SERVICE_TOKEN'),

    'bff_shared_secret' => env('CUSTOMER_PORTAL_BFF_SHARED_SECRET'),

    'bff_session_hours' => (int) env('CUSTOMER_PORTAL_BFF_SESSION_HOURS', 8),

    'mobile_session_hours' => (int) env('CUSTOMER_PORTAL_MOBILE_SESSION_HOURS', 720),

    'otp_email_enabled' => (bool) env('CUSTOMER_PORTAL_OTP_EMAIL_ENABLED', true),

    'otp_sms_webhook_url' => env('CUSTOMER_PORTAL_OTP_SMS_WEBHOOK_URL'),

    'otp_sms_webhook_token' => env('CUSTOMER_PORTAL_OTP_SMS_WEBHOOK_TOKEN'),

    'otp_sms_from' => env('CUSTOMER_PORTAL_OTP_SMS_FROM', 'New WorldCargo'),

    'payment_provider' => env('CUSTOMER_PORTAL_PAYMENT_PROVIDER'),

    'payment_webhook_url' => env('CUSTOMER_PORTAL_PAYMENT_WEBHOOK_URL'),

    'payment_webhook_token' => env('CUSTOMER_PORTAL_PAYMENT_WEBHOOK_TOKEN'),

    'booking_pricing' => [
        'currency' => env('CUSTOMER_PORTAL_BOOKING_CURRENCY', 'USD'),
        'quote_minutes' => (int) env('CUSTOMER_PORTAL_BOOKING_QUOTE_MINUTES', 15),
        'require_signed_quote' => (bool) env('CUSTOMER_PORTAL_REQUIRE_SIGNED_BOOKING_QUOTE', true),
        'services' => [
            'local' => [
                'base_fee' => env('CUSTOMER_PORTAL_LOCAL_BASE_FEE'),
                'per_km' => env('CUSTOMER_PORTAL_LOCAL_PER_KM'),
                'per_kg' => env('CUSTOMER_PORTAL_LOCAL_PER_KG', 0),
                'fragile_fee' => env('CUSTOMER_PORTAL_LOCAL_FRAGILE_FEE', 0),
                'minutes_per_km' => env('CUSTOMER_PORTAL_LOCAL_MINUTES_PER_KM', 4),
                'vehicle_fees' => [
                    'scooter' => env('CUSTOMER_PORTAL_LOCAL_SCOOTER_FEE', 0),
                    'small_van' => env('CUSTOMER_PORTAL_LOCAL_SMALL_VAN_FEE', 0),
                    'cargo_van' => env('CUSTOMER_PORTAL_LOCAL_CARGO_VAN_FEE', 0),
                ],
                'schedule_fees' => [
                    'as_soon_as_possible' => env('CUSTOMER_PORTAL_LOCAL_ASAP_FEE', 0),
                    'later_today' => env('CUSTOMER_PORTAL_LOCAL_LATER_FEE', 0),
                    'scheduled' => env('CUSTOMER_PORTAL_LOCAL_SCHEDULED_FEE', 0),
                ],
            ],
            'intercity' => [
                'base_fee' => env('CUSTOMER_PORTAL_INTERCITY_BASE_FEE'),
                'per_km' => env('CUSTOMER_PORTAL_INTERCITY_PER_KM', 0),
                'per_kg' => env('CUSTOMER_PORTAL_INTERCITY_PER_KG', 0),
                'fragile_fee' => env('CUSTOMER_PORTAL_INTERCITY_FRAGILE_FEE', 0),
                'minutes_per_km' => env('CUSTOMER_PORTAL_INTERCITY_MINUTES_PER_KM', 1.2),
                'fulfilment_fees' => [
                    'collection' => env('CUSTOMER_PORTAL_INTERCITY_COLLECTION_FEE', 0),
                    'door_delivery' => env('CUSTOMER_PORTAL_INTERCITY_DOOR_FEE', 0),
                ],
            ],
            'import' => [
                'base_fee' => env('CUSTOMER_PORTAL_IMPORT_BASE_FEE'),
                'per_km' => env('CUSTOMER_PORTAL_IMPORT_PER_KM', 0),
                'per_kg' => env('CUSTOMER_PORTAL_IMPORT_PER_KG', 0),
                'fragile_fee' => env('CUSTOMER_PORTAL_IMPORT_FRAGILE_FEE', 0),
                'minutes_per_km' => env('CUSTOMER_PORTAL_IMPORT_MINUTES_PER_KM', 1),
                'transport_fees' => [
                    'air' => env('CUSTOMER_PORTAL_IMPORT_AIR_FEE', 0),
                    'sea' => env('CUSTOMER_PORTAL_IMPORT_SEA_FEE', 0),
                ],
            ],
            'custom' => [],
        ],
    ],
];
