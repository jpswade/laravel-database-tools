<?php

return [
    'database' => [
        'driver' => env('DB_CONNECTION_SOURCE', env('DB_CONNECTION', 'mysql')),
        'host' => env('DB_HOST_SOURCE', env('DB_HOST', '127.0.0.1')),
        'port' => env('DB_PORT_SOURCE', env('DB_PORT', '3306')),
        'database' => env('DB_DATABASE_SOURCE', env('DB_DATABASE', 'laravel')),
        'username' => env('DB_USERNAME_SOURCE', env('DB_USERNAME', 'root')),
        'password' => env('DB_PASSWORD_SOURCE', env('DB_PASSWORD')),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => false,
        'engine' => null,
    ],

    'filesystem' => [
        'driver' => 's3',
        'key' => env('DB_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('DB_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('DB_AWS_DEFAULT_REGION', env('AWS_DEFAULT_REGION', 'eu-west-1')),
        'bucket' => env('DB_AWS_BUCKET', env('AWS_BUCKET')),
        'path' => env('DB_AWS_BUCKET_PATH'),
    ],

    'import' => [
        'method' => 'command',
        'increase_max_allowed_packet' => env('DBTOOLS_INCREASE_MAX_ALLOWED_PACKET', true),
    ],

    'get' => ['method' => 'command'],
];
