<?php
/** Shekel Shared Redis store */
return [
    'client_redis' => [
        'driver' => 'redis',
        'connection' => 'shekel',
        'lock_connection' => 'default',
        'prefix' => env('TENANT_REDIS_SHARED_KEY', 'shekel_client')
    ],
];