<?php

declare(strict_types=1);

/**
 * Logging Configuration
 *
 * Configure the logging channels and drivers for your application.
 * The default channel will be used when no specific channel is requested.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified here should match one of the
    | channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Each channel
    | can be configured with its own driver and options. Available drivers:
    | "file", "stdout", "null", "array"
    |
    */

    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('logs/app.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => 7,
            'date_format' => 'Y-m-d',
        ],

        'stdout' => [
            'driver' => 'stdout',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stderr' => [
            'driver' => 'stdout',
            'level' => 'error',
        ],

        'null' => [
            'driver' => 'null',
        ],

        /*
        |--------------------------------------------------------------------------
        | Stack Channel
        |--------------------------------------------------------------------------
        |
        | The stack channel allows you to log to multiple channels simultaneously.
        | Use log()->stack(['file', 'stdout'])->info('message') to log to both.
        |
        */

        // Example: Uncomment to log to both file and stdout
        // 'stack' => [
        //     'driver' => 'stack',
        //     'channels' => ['file', 'stdout'],
        // ],
    ],
];
