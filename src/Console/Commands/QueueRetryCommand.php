<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;
use Lumina\DDD\Container\ContainerInterface;
use Lumina\DDD\Queue\FailedJobProviderInterface;
use Lumina\DDD\Queue\QueueManager;

/**
 * Command to retry failed jobs.
 */
final class QueueRetryCommand extends AbstractCommand
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
        $this->setName('queue:retry')
            ->setDescription('Retry a failed job by ID or retry all failed jobs')
            ->addArgument('id', [
                'description' => 'The ID of the failed job to retry',
            ])
            ->addOption('all', [
                'description' => 'Retry all failed jobs',
            ]);
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

        $id = $input->getArgument('id');
        $all = $input->hasOption('all') && $input->getOption('all') !== false;

        if (!$this->container->has(FailedJobProviderInterface::class)) {
            $output->error('Failed job provider is not configured.');
            $output->writeln('Add a FailedJobProviderInterface binding to your container.');
            return 1;
        }

        /** @var FailedJobProviderInterface $provider */
        $provider = $this->container->get(FailedJobProviderInterface::class);
        
        /** @var QueueManager $manager */
        $manager = $this->container->get(QueueManager::class);

        if ($all) {
            return $this->retryAll($provider, $manager, $output);
        }

        if ($id === null) {
            $output->error('Please provide a job ID or use --all to retry all failed jobs.');
            return 1;
        }

        return $this->retryOne((string) $id, $provider, $manager, $output);
    }

    /**
     * Retry a single failed job.
     */
    private function retryOne(
        string $id,
        FailedJobProviderInterface $provider,
        QueueManager $manager,
        Output $output,
    ): int {
        $failedJob = $provider->find($id);

        if ($failedJob === null) {
            $output->error("Failed job [{$id}] not found.");
            return 1;
        }

        try {
            $payload = json_decode($failedJob['payload'], true, 512, JSON_THROW_ON_ERROR);
            $job = unserialize($payload['job']);
            
            $queue = $manager->connection($failedJob['connection'] ?? null);
            $queue->push($job, $failedJob['queue'] ?? 'default');
            
            $provider->forget($id);

            $output->success("Job [{$id}] has been pushed back onto the queue.");
            return 0;
        } catch (\Throwable $e) {
            $output->error("Failed to retry job [{$id}]: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Retry all failed jobs.
     */
    private function retryAll(
        FailedJobProviderInterface $provider,
        QueueManager $manager,
        Output $output,
    ): int {
        $failedJobs = $provider->all();

        if (empty($failedJobs)) {
            $output->comment('No failed jobs to retry.');
            return 0;
        }

        $count = 0;
        $errors = 0;

        foreach ($failedJobs as $failedJob) {
            try {
                $payload = json_decode($failedJob['payload'], true, 512, JSON_THROW_ON_ERROR);
                $job = unserialize($payload['job']);
                
                $queue = $manager->connection($failedJob['connection'] ?? null);
                $queue->push($job, $failedJob['queue'] ?? 'default');
                
                $provider->forget($failedJob['id']);
                $count++;
            } catch (\Throwable $e) {
                $output->error("Failed to retry job [{$failedJob['id']}]: {$e->getMessage()}");
                $errors++;
            }
        }

        $output->success("{$count} job(s) have been pushed back onto the queue.");
        
        if ($errors > 0) {
            $output->comment("{$errors} job(s) failed to retry.");
            return 1;
        }

        return 0;
    }
}
