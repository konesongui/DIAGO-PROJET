<?php

return [
    'environment' => env('FNE_ENVIRONMENT', 'test'),
    'test_url' => env('FNE_TEST_URL', 'http://54.247.95.108/ws/external/invoices/sign'),
    'production_url' => env('FNE_PRODUCTION_URL'),
    'api_key' => env('FNE_API_KEY'),
    'point_of_sale' => env('FNE_POINT_OF_SALE', 'DIAGOMA'),
    'establishment' => env('FNE_ESTABLISHMENT', 'INTENDANT'),
    'timeout' => (int) env('FNE_TIMEOUT', 30),
];
