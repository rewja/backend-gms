<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Atur origin mana saja yang boleh akses API Laravel.
    | Kamu bisa pakai daftar origin spesifik + pola regex untuk fleksibilitas.
    |
    */

    // Semua endpoint API Laravel + Sanctum CSRF cookie
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Izinkan semua HTTP methods (GET, POST, PUT, DELETE, OPTIONS, dll.)
    'allowed_methods' => ['*'],

    // Daftar asal (frontend/backend) spesifik yang diizinkan
    'allowed_origins' => [
        // Localhost dev
        'http://localhost:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://localhost:8000',
        'http://localhost:8004',
        'http://0.0.0.0:5173',
        'http://0.0.0.0:5174',
        'http://0.0.0.0:8000',

        // IP 172.15.3.141 (server kamu)
        'http://172.15.3.141:5173',
        'http://172.15.3.141:8000',
        'http://172.15.3.141:8004',
        'http://172.15.3.141:8084',
        'http://172.15.3.141:5001',

        // IP Home (PC/Laptop lain di jaringan)
        'http://172.15.3.41:5173',
        'http://172.15.3.41:8000',

        // Jaringan WiFi / lokal lain (contoh HP)
        'http://192.168.18.26:5173',
        'http://192.168.18.26:8000',
    ],

    // Pola asal yang diizinkan (lebih fleksibel dari daftar di atas)
    'allowed_origins_patterns' => [
        '/^http:\/\/172\.15\.3\.\d{1,3}(:\d+)?$/', // semua IP 172.15.3.xxx dengan port bebas
        '/^http:\/\/192\.168\.\d{1,3}\.\d{1,3}(:\d+)?$/', // semua jaringan 192.168.x.x
        '/^http:\/\/localhost(:\d+)?$/',           // semua port localhost
        '/^http:\/\/127\.0\.0\.1(:\d+)?$/',        // semua port 127.0.0.1
        '/^http:\/\/0\.0\.0\.0(:\d+)?$/',          // semua port 0.0.0.0
    ],

    // Header yang diizinkan
    'allowed_headers' => ['*'],

    // Header yang bisa diekspos ke browser
    'exposed_headers' => [],

    // Durasi preflight (OPTIONS) cache (detik)
    'max_age' => 0,

    // Perlu credentials (cookie, Authorization header, dsb.)
    'supports_credentials' => true,

];
