<?php

return [
    'enabled' => filter_var(env('CINETPAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'site_id' => env('CINETPAY_SITE_ID'),
    'api_key' => env('CINETPAY_API_KEY'),
    'secret_key' => env('CINETPAY_SECRET_KEY'),
    'mode' => env('CINETPAY_MODE', 'test'),
    'api_url' => env('CINETPAY_API_URL', 'https://api.cinetpay.com/v2/payment'),
    'currency' => env('CINETPAY_CURRENCY', 'XOF'),
    'channels' => env('CINETPAY_CHANNELS', 'ALL'),
    'notify_url' => env('CINETPAY_NOTIFY_URL'),
    'return_url' => env('CINETPAY_RETURN_URL'),
];
