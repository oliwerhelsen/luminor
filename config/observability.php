<?php

declare(strict_types=1);

/**
 * Observability Configuration
 *
 * Configure metrics, tracing, and monitoring for your application.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Metrics Driver
    |--------------------------------------------------------------------------
    |
    | The metrics driver determines how application metrics are collected.
    |
    | Supported: "memory", "null"
    |
    | - memory: Stores metrics in memory (development/testing)
    | - null: Disables metrics collection
    |
    */
    'metrics' => [
        'driver' => env('METRICS_DRIVER', 'memory'),

        // Enable/disable metrics collection
        'enabled' => env('METRICS_ENABLED', true),

        // Prefix for all metrics
        'prefix' => env('METRICS_PREFIX', 'luminor'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Track application performance and errors.
    |
    */
    'apm' => [
        'enabled' => env('APM_ENABLED', false),

        // Track slow queries (in milliseconds)
        'slow_query_threshold' => env('APM_SLOW_QUERY_THRESHOLD', 1000),

        // Track slow requests (in milliseconds)
        'slow_request_threshold' => env('APM_SLOW_REQUEST_THRESHOLD', 2000),
    ],
];
