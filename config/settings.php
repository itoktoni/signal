<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Settings Table
    |--------------------------------------------------------------------------
    |
    | Database table used to store settings in.
    |
    */
    'table' => 'settings',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | If enabled, all settings are cached after accessing them.
    |
    */
    'cache' => true,

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | The driver to use to store and retrieve settings from. You are free
    | to add more drivers in the `drivers` configuration below.
    |
    */
    'driver' => env('SETTINGS_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the driver information for each repository that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with this package. You are free to add more.
    |
    | Each driver you add must implement the database, file, memory, redis, memcache.
    |
    */
    'drivers' => [
        'database' => [
            'driver' => 'database',
            'table' => config('settings.table'),
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('app/settings.json'),
        ],
        'memory' => [
            'driver' => 'memory',
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CONNECTION', 'default'),
        ],
        'memcache' => [
            'driver' => 'memcache',
            'host' => env('MEMCACHE_HOST', '127.0.0.1'),
            'port' => env('MEMCACHE_PORT', 11211),
        ],
    ],

];
