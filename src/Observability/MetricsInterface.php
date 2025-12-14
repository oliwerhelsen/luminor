<?php

declare(strict_types=1);

namespace Luminor\Observability;

/**
 * Interface for metrics collection.
 */
interface MetricsInterface
{
    /**
     * Increment a counter metric.
     */
    public function increment(string $metric, int $value = 1, array $tags = []): void;

    /**
     * Decrement a counter metric.
     */
    public function decrement(string $metric, int $value = 1, array $tags = []): void;

    /**
     * Record a gauge value.
     */
    public function gauge(string $metric, float $value, array $tags = []): void;

    /**
     * Record a histogram value.
     */
    public function histogram(string $metric, float $value, array $tags = []): void;

    /**
     * Record a timing value in milliseconds.
     */
    public function timing(string $metric, float $milliseconds, array $tags = []): void;

    /**
     * Start a timer and return a closure to stop it.
     */
    public function timer(string $metric, array $tags = []): callable;

    /**
     * Get all recorded metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array;

    /**
     * Reset all metrics.
     */
    public function reset(): void;
}
