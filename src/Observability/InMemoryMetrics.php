<?php

declare(strict_types=1);

namespace Luminor\DDD\Observability;

/**
 * In-memory metrics implementation.
 *
 * Useful for development and testing. For production, use a dedicated
 * metrics backend like Prometheus, StatsD, or CloudWatch.
 */
final class InMemoryMetrics implements MetricsInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $metrics = [];

    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        $key = $this->buildKey($metric, $tags);

        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'type' => 'counter',
                'value' => 0,
                'tags' => $tags,
            ];
        }

        $this->metrics[$key]['value'] += $value;
    }

    public function decrement(string $metric, int $value = 1, array $tags = []): void
    {
        $this->increment($metric, -$value, $tags);
    }

    public function gauge(string $metric, float $value, array $tags = []): void
    {
        $key = $this->buildKey($metric, $tags);

        $this->metrics[$key] = [
            'type' => 'gauge',
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true),
        ];
    }

    public function histogram(string $metric, float $value, array $tags = []): void
    {
        $key = $this->buildKey($metric, $tags);

        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'type' => 'histogram',
                'values' => [],
                'tags' => $tags,
            ];
        }

        $this->metrics[$key]['values'][] = $value;
    }

    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $this->histogram($metric, $milliseconds, $tags);
    }

    public function timer(string $metric, array $tags = []): callable
    {
        $start = microtime(true);

        return function () use ($metric, $tags, $start): void {
            $duration = (microtime(true) - $start) * 1000;
            $this->timing($metric, $duration, $tags);
        };
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function reset(): void
    {
        $this->metrics = [];
    }

    /**
     * Build a unique key for a metric with tags.
     */
    private function buildKey(string $metric, array $tags): string
    {
        if (empty($tags)) {
            return $metric;
        }

        ksort($tags);
        $tagString = http_build_query($tags);

        return $metric . '{' . $tagString . '}';
    }

    /**
     * Get metric statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $stats = [];

        foreach ($this->metrics as $key => $data) {
            $metricName = explode('{', $key)[0];

            if ($data['type'] === 'histogram') {
                $values = $data['values'];
                sort($values);

                $stats[$metricName] = [
                    'type' => 'histogram',
                    'count' => count($values),
                    'min' => min($values),
                    'max' => max($values),
                    'avg' => array_sum($values) / count($values),
                    'p50' => $this->percentile($values, 50),
                    'p95' => $this->percentile($values, 95),
                    'p99' => $this->percentile($values, 99),
                ];
            } else {
                $stats[$metricName] = [
                    'type' => $data['type'],
                    'value' => $data['value'],
                ];
            }
        }

        return $stats;
    }

    /**
     * Calculate percentile.
     *
     * @param array<int, float> $values
     */
    private function percentile(array $values, float $percentile): float
    {
        $index = (int) ceil((count($values) * $percentile) / 100) - 1;
        return $values[$index] ?? 0.0;
    }
}
