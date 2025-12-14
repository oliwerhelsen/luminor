---
title: Queues
layout: default
parent: Features
nav_order: 5
description: "Background job processing with multiple queue drivers"
---

# Queues

Luminor's queue system allows you to defer time-consuming tasks to be processed in the background, improving response times for your users.

## Configuration

Configure your queue connections in `config/queue.php`:

```php
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_QUEUE_DB', 0),
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
        ],

        'valkey' => [
            'driver' => 'valkey',
            'host' => env('VALKEY_HOST', '127.0.0.1'),
            'port' => env('VALKEY_PORT', 6379),
            'password' => env('VALKEY_PASSWORD'),
            'database' => 0,
            'queue' => 'default',
        ],
    ],

    'failed' => [
        'driver' => 'database',
        'table' => 'failed_jobs',
    ],
];
```

## Creating Jobs

### Using the Generator

```bash
vendor/bin/luminor make:job ProcessPayment
vendor/bin/luminor make:job SendReport --sync
```

### Manual Creation

Create a job class that extends `Job` and implements `ShouldQueue`:

```php
<?php

namespace App\Jobs;

use Luminor\Queue\Job;
use Luminor\Queue\ShouldQueue;

final class ProcessPayment extends Job implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 120;

    public function __construct(
        private readonly int $orderId,
        private readonly float $amount,
    ) {}

    public function handle(): void
    {
        // Process the payment...
        $order = Order::find($this->orderId);
        $paymentGateway->charge($order, $this->amount);
    }

    public function failed(\Throwable $exception): void
    {
        // Handle failure - notify admin, log error, etc.
        logger()->error('Payment failed', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

## Dispatching Jobs

### Using the Helper Function

```php
// Dispatch to the default queue
dispatch(new ProcessPayment($orderId, $amount));

// Dispatch to a specific queue
dispatch(new ProcessPayment($orderId, $amount), 'payments');

// Execute immediately (bypass queue)
dispatch_sync(new ProcessPayment($orderId, $amount));
```

### Using the Queue Manager

```php
use Luminor\Queue\QueueManager;

$queue = app(QueueManager::class);

// Push to default connection
$queue->push(new ProcessPayment($orderId, $amount));

// Push to specific connection
$queue->connection('redis')->push(new ProcessPayment($orderId, $amount));

// Delay execution
$queue->later(60, new ProcessPayment($orderId, $amount)); // 60 seconds delay
```

## Job Properties

Configure job behavior with properties:

```php
class ProcessPayment extends Job implements ShouldQueue
{
    // Maximum number of attempts
    public int $tries = 3;

    // Seconds to wait before retrying (or array for exponential backoff)
    public int|array $backoff = 60;
    // Example: [30, 60, 120] - wait 30s, then 60s, then 120s

    // Maximum execution time in seconds
    public int $timeout = 120;

    // Queue name for this job
    public string $queue = 'payments';

    // Unique job identifier (prevents duplicates)
    public ?string $unique = null;
}
```

## Running the Queue Worker

### Basic Usage

```bash
# Process jobs from the default queue
vendor/bin/luminor queue:work

# Process jobs from a specific connection
vendor/bin/luminor queue:work --connection=redis

# Process jobs from a specific queue
vendor/bin/luminor queue:work --queue=payments

# Process a single job
vendor/bin/luminor queue:work --once

# Stop when queue is empty
vendor/bin/luminor queue:work --stop-when-empty
```

### Worker Options

| Option              | Description                   | Default |
| ------------------- | ----------------------------- | ------- |
| `--connection`      | Queue connection to use       | default |
| `--queue`           | Queue name to process         | default |
| `--sleep`           | Seconds to sleep when no jobs | 3       |
| `--tries`           | Override job max attempts     | 3       |
| `--timeout`         | Seconds before timeout        | 60      |
| `--memory`          | Memory limit in MB            | 128     |
| `--once`            | Process single job and exit   | -       |
| `--stop-when-empty` | Stop when queue empty         | -       |

## Failed Jobs

### Listing Failed Jobs

```bash
vendor/bin/luminor queue:failed
```

### Retrying Failed Jobs

```bash
# Retry a specific job
vendor/bin/luminor queue:retry abc-123

# Retry all failed jobs
vendor/bin/luminor queue:retry --all
```

### Clearing Failed Jobs

```bash
# Clear all failed jobs
vendor/bin/luminor queue:flush

# Clear jobs older than 24 hours
vendor/bin/luminor queue:flush --hours=24
```

## Database Queue Setup

Create the jobs table migration:

```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX jobs_queue_index (queue)
);

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Queue Drivers

### Sync Driver

Executes jobs immediately (useful for development/testing):

```php
'sync' => [
    'driver' => 'sync',
],
```

### Database Driver

Stores jobs in a database table:

```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,
],
```

### Redis Driver

Uses Redis for high-performance queuing:

```php
'redis' => [
    'driver' => 'redis',
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0,
    'queue' => 'default',
    'retry_after' => 90,
    'block_for' => 5, // BLPOP timeout
],
```

Requires `predis/predis` package or the `phpredis` extension.

### Valkey Driver

Uses Valkey (Redis-compatible) with additional features:

```php
'valkey' => [
    'driver' => 'valkey',
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0,
    'queue' => 'default',
    'tls' => false,      // Enable TLS
    'cluster' => false,  // Enable cluster mode
],
```

## Supervisor Configuration

For production, use Supervisor to keep workers running:

```ini
[program:luminor-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/vendor/bin/luminor queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
stopwaitsecs=3600
```

## Testing with Queues

Use the sync driver for testing to execute jobs immediately:

```php
// In phpunit.xml or test setup
$_ENV['QUEUE_CONNECTION'] = 'sync';
```

Or dispatch synchronously:

```php
dispatch_sync(new ProcessPayment($orderId, $amount));
```
