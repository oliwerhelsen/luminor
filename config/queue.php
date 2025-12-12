<?php

declare(strict_types=1);

/**
 * Queue Configuration
 *
 * Configure the queue connections and drivers for your application.
 * The default connection will be used when no specific connection is requested.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection
    |--------------------------------------------------------------------------
    |
    | This option defines the default queue connection that gets used when
    | dispatching jobs. The connection specified here should match one of
    | the connections defined in the "connections" configuration array.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for each queue backend
    | used by your application. Available drivers: "sync", "database",
    | "redis", "valkey"
    |
    */

    'connections' => [
        /*
        |--------------------------------------------------------------------------
        | Sync Driver
        |--------------------------------------------------------------------------
        |
        | The sync driver executes jobs immediately in the current request.
        | Useful for local development and testing.
        |
        */
        'sync' => [
            'driver' => 'sync',
        ],

        /*
        |--------------------------------------------------------------------------
        | Database Driver
        |--------------------------------------------------------------------------
        |
        | The database driver stores jobs in a database table. This is the
        | default driver that requires no external dependencies.
        |
        | Required table: Run the queue:table command to create the migration.
        |
        */
        'database' => [
            'driver' => 'database',
            'table' => env('QUEUE_TABLE', 'jobs'),
            'queue' => env('QUEUE_NAME', 'default'),
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),
            // 'dsn' => env('DATABASE_URL'),
            // 'username' => env('DB_USERNAME'),
            // 'password' => env('DB_PASSWORD'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Redis Driver
        |--------------------------------------------------------------------------
        |
        | The Redis driver provides high-performance queue operations using
        | Redis lists. Requires predis/predis package or phpredis extension.
        |
        */
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_QUEUE_DB', 0),
            'prefix' => env('QUEUE_PREFIX', 'lumina_queue:'),
            'queue' => env('QUEUE_NAME', 'default'),
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),
        ],

        /*
        |--------------------------------------------------------------------------
        | Valkey Driver
        |--------------------------------------------------------------------------
        |
        | Valkey is a Redis-compatible in-memory data store. This driver
        | uses the same protocol as Redis but with Valkey-specific defaults.
        |
        */
        'valkey' => [
            'driver' => 'valkey',
            'host' => env('VALKEY_HOST', '127.0.0.1'),
            'port' => (int) env('VALKEY_PORT', 6379),
            'password' => env('VALKEY_PASSWORD'),
            'database' => (int) env('VALKEY_QUEUE_DB', 0),
            'prefix' => env('QUEUE_PREFIX', 'lumina_queue:'),
            'queue' => env('QUEUE_NAME', 'default'),
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),
            // 'tls' => true, // Enable TLS
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging.
    | You may change them to customize how failed jobs are stored.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
        'table' => env('QUEUE_FAILED_TABLE', 'failed_jobs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Options
    |--------------------------------------------------------------------------
    |
    | Default options for queue workers. These can be overridden when
    | starting the worker via command line options.
    |
    */

    'worker' => [
        'sleep' => 3,           // Seconds to sleep when no jobs available
        'timeout' => 60,        // Default job timeout in seconds
        'memory' => 128,        // Memory limit in MB
        'max_tries' => 3,       // Default max tries for jobs
    ],
];
