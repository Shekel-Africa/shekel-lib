<?php

return [
    'admin' => [
        'driver' => 'pgsql',
        'url' => env('ADMIN_DATABASE_URL'),
        'host' => env('ADMIN_DB_HOST', '127.0.0.1'),
        'port' => env('ADMIN_DB_PORT', '5432'),
        'database' => env('ADMIN_DB_DATABASE', 'forge'),
        'username' => env('ADMIN_DB_USERNAME', 'forge'),
        'password' => env('ADMIN_DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ],

    'tenant' => []
];