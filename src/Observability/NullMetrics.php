<?php

declare(strict_types=1);

namespace Luminor\Observability;

/**
 * Null metrics implementation.
 *
 * Does nothing - useful for disabling metrics in certain environments.
 */
final class NullMetrics implements MetricsInterface
{
    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        // Do nothing
    }

    public function decrement(string $metric, int $value = 1, array $tags = []): void
    {
        // Do nothing
    }

    public function gauge(string $metric, float $value, array $tags = []): void
    {
        // Do nothing
    }

    public function histogram(string $metric, float $value, array $tags = []): void
    {
        // Do nothing
    }

    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        // Do nothing
    }

    public function timer(string $metric, array $tags = []): callable
    {
        return function (): void {
            // Do nothing
        };
    }

    public function getMetrics(): array
    {
        return [];
    }

    public function reset(): void
    {
        // Do nothing
    }
}
