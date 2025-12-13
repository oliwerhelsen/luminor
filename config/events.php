<?php

declare(strict_types=1);

/**
 * Event Store Configuration
 *
 * Configure how domain events are stored and managed.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Event Store Driver
    |--------------------------------------------------------------------------
    |
    | The event store driver determines how domain events are persisted.
    |
    | Supported: "database", "memory"
    |
    | - database: Stores events in a relational database (production)
    | - memory: Stores events in memory (testing/development)
    |
    */
    'store' => [
        'driver' => env('EVENT_STORE_DRIVER', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot Configuration
    |--------------------------------------------------------------------------
    |
    | Snapshots improve performance by caching aggregate state at specific
    | versions, reducing the need to replay all events.
    |
    */
    'snapshots' => [
        'enabled' => env('EVENT_SNAPSHOTS_ENABLED', true),

        // Take a snapshot every N events
        'threshold' => env('EVENT_SNAPSHOTS_THRESHOLD', 10),

        // Snapshot storage driver
        'driver' => env('EVENT_SNAPSHOTS_DRIVER', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Projections
    |--------------------------------------------------------------------------
    |
    | Projections create read models from event streams.
    |
    */
    'projections' => [
        'enabled' => env('EVENT_PROJECTIONS_ENABLED', true),

        // Registered projection classes
        'projectors' => [
            // Add your projector classes here
        ],
    ],
];
