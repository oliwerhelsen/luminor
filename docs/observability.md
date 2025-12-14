---
title: Observability & Metrics
layout: default
nav_order: 13
description: "Monitor and measure your application's performance"
permalink: /observability/
---

# Observability & Metrics

Monitor your application's health and performance with built-in metrics and observability features.

{: .highlight }
New in v2.0: Comprehensive metrics collection and application performance monitoring.

## Table of Contents

- [Introduction](#introduction)
- [Metrics](#metrics)
- [Performance Monitoring](#performance-monitoring)
- [CLI Commands](#cli-commands)
- [Production Setup](#production-setup)

---

## Introduction

Observability helps you understand what's happening inside your application by collecting metrics, traces, and logs.

**Key Benefits:**

- Identify performance bottlenecks
- Track business KPIs
- Alert on anomalies
- Understand user behavior
- Optimize resource usage

---

## Metrics

### Configuration

Configure metrics in `config/observability.php`:

```php
<?php

return [
    'metrics' => [
        'driver' => env('METRICS_DRIVER', 'memory'),
        'enabled' => env('METRICS_ENABLED', true),
        'prefix' => env('METRICS_PREFIX', 'luminor'),
    ],
];
```

### Available Drivers

- `memory` - In-memory storage (development/testing)
- `null` - Disables metrics (minimal overhead)

### Using Metrics

Inject `MetricsInterface` into your classes:

```php
<?php

use Luminor\Observability\MetricsInterface;

final class OrderService
{
    public function __construct(
        private readonly MetricsInterface $metrics
    ) {}

    public function processOrder(Order $order): void
    {
        // Increment counter
        $this->metrics->increment('orders.processed');

        // Record gauge
        $this->metrics->gauge('orders.pending', $this->getPendingCount());

        // Record timing
        $stopTimer = $this->metrics->timer('orders.process_time');

        try {
            $this->doProcessing($order);
            $this->metrics->increment('orders.success');
        } catch (\Exception $e) {
            $this->metrics->increment('orders.failed');
            throw $e;
        } finally {
            $stopTimer(); // Records timing
        }
    }
}
```

### Metric Types

#### Counters

Track cumulative values that only increase:

```php
// Simple increment
$metrics->increment('http.requests');

// Increment by value
$metrics->increment('bytes.sent', 1024);

// With tags
$metrics->increment('http.requests', 1, [
    'method' => 'POST',
    'status' => '200'
]);

// Decrement
$metrics->decrement('queue.pending');
```

**Use for:**

- Request counts
- Error counts
- Items processed

#### Gauges

Track values that go up and down:

```php
// Current value
$metrics->gauge('memory.usage', memory_get_usage());

// Queue depth
$metrics->gauge('queue.depth', $queueSize);

// With tags
$metrics->gauge('cpu.usage', $cpuPercent, [
    'core' => '0'
]);
```

**Use for:**

- Memory usage
- Queue depth
- Connection pool size
- Active users

#### Histograms

Track distribution of values:

```php
// Response size
$metrics->histogram('http.response.size', strlen($body));

// Order value
$metrics->histogram('orders.value', $order->getTotal());
```

**Use for:**

- Request/response sizes
- Value distributions
- Batch sizes

#### Timers

Measure duration:

```php
// Method 1: Manual timing
$start = microtime(true);
$this->doWork();
$duration = (microtime(true) - $start) * 1000;
$metrics->timing('work.duration', $duration);

// Method 2: Timer closure
$stopTimer = $metrics->timer('work.duration');
$this->doWork();
$stopTimer();

// Method 3: Timer with tags
$stopTimer = $metrics->timer('db.query', [
    'table' => 'users',
    'operation' => 'select'
]);
$this->executeQuery();
$stopTimer();
```

**Use for:**

- Request duration
- Database query time
- External API calls
- Background job duration

---

## Performance Monitoring

### Slow Query Detection

Track database queries that exceed a threshold:

```php
<?php

use Luminor\Observability\MetricsInterface;

final class DatabaseConnection
{
    public function query(string $sql): array
    {
        $stopTimer = $this->metrics->timer('db.query');
        $start = microtime(true);

        $result = $this->execute($sql);

        $duration = (microtime(true) - $start) * 1000;
        $stopTimer();

        // Track slow queries
        $threshold = config('observability.apm.slow_query_threshold', 1000);
        if ($duration > $threshold) {
            $this->metrics->increment('db.slow_queries');
            $this->logger->warning('Slow query detected', [
                'duration' => $duration,
                'sql' => $sql,
            ]);
        }

        return $result;
    }
}
```

### Request Tracking

Monitor HTTP request performance:

```php
<?php

use Luminor\Http\Middleware\MetricsMiddleware;

final class MetricsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly MetricsInterface $metrics
    ) {}

    public function process(Request $request, Response $response): Response
    {
        $stopTimer = $this->metrics->timer('http.request', [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
        ]);

        try {
            $response = $this->next($request, $response);

            $this->metrics->increment('http.requests', 1, [
                'method' => $request->getMethod(),
                'status' => $response->getStatusCode(),
            ]);

            return $response;
        } finally {
            $stopTimer();
        }
    }
}
```

### Command Performance

Track CLI command execution:

```php
<?php

use Luminor\Console\Command;

final class ProcessOrdersCommand extends Command
{
    public function handle(): int
    {
        $stopTimer = $this->metrics->timer('commands.process_orders');
        $processed = 0;

        try {
            foreach ($this->getOrders() as $order) {
                $this->processOrder($order);
                $processed++;
            }

            $this->metrics->gauge('commands.orders_processed', $processed);

            return self::SUCCESS;
        } finally {
            $stopTimer();
        }
    }
}
```

---

## CLI Commands

### View Metrics

```bash
php luminor metrics:show
```

Output:

```
Application Metrics
==================

Metric: http.requests
  Type: counter
  Value: 1543

Metric: http.request
  Type: histogram
  Count: 1543
  Min: 12.34
  Max: 2341.56
  Avg: 145.23
  P50: 132.45
  P95: 456.78
  P99: 892.12

Metric: memory.usage
  Type: gauge
  Value: 52428800
```

---

## Production Setup

### StatsD Integration

For production, integrate with StatsD:

```php
<?php

use Luminor\Observability\MetricsInterface;

final class StatsDMetrics implements MetricsInterface
{
    private $socket;

    public function __construct(
        private readonly string $host,
        private readonly int $port
    ) {
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        $this->send("{$metric}:{$value}|c");
    }

    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $this->send("{$metric}:{$milliseconds}|ms");
    }

    private function send(string $data): void
    {
        socket_sendto($this->socket, $data, strlen($data), 0, $this->host, $this->port);
    }
}
```

### Prometheus Integration

For Prometheus:

```php
<?php

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

final class PrometheusMetrics implements MetricsInterface
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        Redis::setDefaultOptions(['host' => '127.0.0.1']);
        $this->registry = new CollectorRegistry(new Redis());
    }

    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'luminor',
            $metric,
            'help text',
            array_keys($tags)
        );

        $counter->incBy($value, array_values($tags));
    }
}
```

### CloudWatch Integration

For AWS CloudWatch:

```php
<?php

use Aws\CloudWatch\CloudWatchClient;

final class CloudWatchMetrics implements MetricsInterface
{
    private CloudWatchClient $client;

    public function __construct()
    {
        $this->client = new CloudWatchClient([
            'region' => 'us-east-1',
            'version' => 'latest',
        ]);
    }

    public function gauge(string $metric, float $value, array $tags = []): void
    {
        $this->client->putMetricData([
            'Namespace' => 'Luminor',
            'MetricData' => [[
                'MetricName' => $metric,
                'Value' => $value,
                'Unit' => 'None',
                'Dimensions' => $this->buildDimensions($tags),
            ]],
        ]);
    }

    private function buildDimensions(array $tags): array
    {
        return array_map(
            fn($key, $value) => ['Name' => $key, 'Value' => $value],
            array_keys($tags),
            array_values($tags)
        );
    }
}
```

---

## Best Practices

### Metric Naming

Use consistent naming conventions:

```
<namespace>.<component>.<metric>.<unit>

Examples:
- luminor.http.requests.total
- luminor.db.queries.duration
- luminor.queue.jobs.processed
- luminor.cache.hits.total
```

### Tags

Use tags for dimensions:

```php
$metrics->increment('orders.created', 1, [
    'region' => 'us-west',
    'product' => 'premium',
    'channel' => 'web'
]);
```

**Warning:** High cardinality tags (e.g., user IDs) can cause performance issues.

### Sampling

For high-traffic metrics, use sampling:

```php
if (random_int(1, 100) <= 10) { // 10% sample rate
    $metrics->histogram('response.size', strlen($body));
}
```

### Cleanup

Reset metrics in tests:

```php
protected function tearDown(): void
{
    $this->metrics->reset();
    parent::tearDown();
}
```

---

## Next Steps

- [Logging](logging)
- [Testing](testing)
- [Production Readiness](best-practices#production-readiness)
