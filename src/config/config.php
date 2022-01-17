<?php

return [
    'database' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST_SOURCE', ''),
        'port' => env('DB_PORT_SOURCE', '3306'),
        'database' => env('DB_DATABASE_SOURCE', ''),
        'username' => env('DB_USERNAME_SOURCE', ''),
        'password' => env('DB_PASSWORD_SOURCE', ''),
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
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
        'bucket' => env('DB_AWS_BUCKET'),
    ],

    'import' => ['method' => 'command']
];
