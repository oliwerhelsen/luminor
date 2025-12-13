<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use DateTimeImmutable;
use JsonException;
use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Container\ContainerInterface;
use Luminor\DDD\Queue\FailedJobProviderInterface;

/**
 * Command to list failed jobs.
 */
final class QueueFailedCommand extends AbstractCommand
{
    private ?ContainerInterface $container = null;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('queue:failed')
            ->setDescription('List all failed queue jobs');
    }

    /**
     * @inheritDoc
     */
    protected function handle(Input $input, Output $output): int
    {
        if ($this->container === null) {
            $output->error('Container not set. This command requires dependency injection.');

            return 1;
        }

        if (! $this->container->has(FailedJobProviderInterface::class)) {
            $output->error('Failed job provider is not configured.');
            $output->writeln('Add a FailedJobProviderInterface binding to your container.');

            return 1;
        }

        /** @var FailedJobProviderInterface $provider */
        $provider = $this->container->get(FailedJobProviderInterface::class);
        $failedJobs = $provider->all();

        if (empty($failedJobs)) {
            $output->comment('No failed jobs found.');

            return 0;
        }

        $output->info('Failed Jobs:');
        $output->newLine();

        // Calculate column widths
        $idWidth = max(4, ...array_map(fn ($job) => strlen((string) $job['id']), $failedJobs));
        $queueWidth = max(5, ...array_map(fn ($job) => strlen($job['queue'] ?? 'default'), $failedJobs));
        $connectionWidth = max(10, ...array_map(fn ($job) => strlen($job['connection'] ?? 'default'), $failedJobs));

        // Header
        $header = sprintf(
            "  %-{$idWidth}s  %-{$connectionWidth}s  %-{$queueWidth}s  %-20s  %s",
            'ID',
            'Connection',
            'Queue',
            'Failed At',
            'Job Name',
        );
        $output->writeln($header);
        $output->writeln('  ' . str_repeat('-', strlen($header) - 2));

        foreach ($failedJobs as $job) {
            $jobName = $this->extractJobName($job['payload'] ?? '');
            $failedAt = isset($job['failed_at'])
                ? (new DateTimeImmutable($job['failed_at']))->format('Y-m-d H:i:s')
                : 'Unknown';

            $output->writeln(sprintf(
                "  %-{$idWidth}s  %-{$connectionWidth}s  %-{$queueWidth}s  %-20s  %s",
                $job['id'],
                $job['connection'] ?? 'default',
                $job['queue'] ?? 'default',
                $failedAt,
                $jobName,
            ));
        }

        $output->newLine();
        $output->comment(sprintf('Total: %d failed job(s)', count($failedJobs)));

        return 0;
    }

    /**
     * Extract job name from payload.
     */
    private function extractJobName(string $payload): string
    {
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            return $data['name'] ?? 'Unknown';
        } catch (JsonException) {
            return 'Unknown';
        }
    }
}
