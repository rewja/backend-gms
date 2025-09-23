<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // 'http://localhost:8004',
        'http://172.15.3.141:8004',
        'http://172.15.3.141:8084',
        // 'http:://localhost:5001',
        // 'http://172.15.3.141:5001',
        // 'http://localhost:5173',
        // 'http://localhost:8000',
        // 'http://172.15.3.141:8000',
        'http://172.15.3.141:5173'
    ],

    'allowed_origins_patterns' => [
        'http://172.15.3.*:*',
        'http://localhost:*'
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
