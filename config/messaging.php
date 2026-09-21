<?php

return [
    'activation_ready' => env('MESSAGING_ACTIVATION_READY', false),
    'connection' => 'messaging',
    'base_url' => 'https://cpassmessaging.mtn.zm',
    'login_path' => '/api/v1/accounts/users/login',
    'send_path' => '/api/v1/sms/send',
    'purposes' => [
        'otp' => 'Verification codes (OTP)',
        'customer_notifications' => 'Customer shipment and service notifications',
        'staff_notifications' => 'Staff operational notifications',
        'bulk_customers' => 'Bulk customer service announcements',
        'bulk_staff' => 'Bulk staff announcements',
    ],
];
