<?php

return [
    'client_redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
        'prefix' => env('TENANT_REDIS_SHARED_KEY', 'shekel_client')
    ],
];