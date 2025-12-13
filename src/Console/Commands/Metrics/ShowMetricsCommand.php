<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands\Metrics;

use Luminor\DDD\Console\Command;
use Luminor\DDD\Observability\InMemoryMetrics;
use Luminor\DDD\Observability\MetricsInterface;

/**
 * Display application metrics.
 */
final class ShowMetricsCommand extends Command
{
    protected string $signature = 'metrics:show';

    protected string $description = 'Display application metrics';

    public function __construct(
        private readonly MetricsInterface $metrics
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Application Metrics');
        $this->line('==================');
        $this->line('');

        if ($this->metrics instanceof InMemoryMetrics) {
            $stats = $this->metrics->getStats();

            if (empty($stats)) {
                $this->warn('No metrics collected.');
                return self::SUCCESS;
            }

            foreach ($stats as $name => $data) {
                $this->line(sprintf('Metric: %s', $name));
                $this->line(sprintf('  Type: %s', $data['type']));

                if ($data['type'] === 'histogram') {
                    $this->line(sprintf('  Count: %d', $data['count']));
                    $this->line(sprintf('  Min: %.2f', $data['min']));
                    $this->line(sprintf('  Max: %.2f', $data['max']));
                    $this->line(sprintf('  Avg: %.2f', $data['avg']));
                    $this->line(sprintf('  P50: %.2f', $data['p50']));
                    $this->line(sprintf('  P95: %.2f', $data['p95']));
                    $this->line(sprintf('  P99: %.2f', $data['p99']));
                } else {
                    $this->line(sprintf('  Value: %s', $data['value']));
                }

                $this->line('');
            }
        } else {
            $metrics = $this->metrics->getMetrics();

            if (empty($metrics)) {
                $this->warn('No metrics collected.');
                return self::SUCCESS;
            }

            foreach ($metrics as $name => $data) {
                $this->line(sprintf('%s: %s', $name, json_encode($data)));
            }
        }

        return self::SUCCESS;
    }
}
