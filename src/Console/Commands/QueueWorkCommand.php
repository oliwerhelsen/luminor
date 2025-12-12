<?php

declare(strict_types=1);

namespace Lumina\DDD\Console\Commands;

use Lumina\DDD\Console\Input;
use Lumina\DDD\Console\Output;
use Lumina\DDD\Container\ContainerInterface;
use Lumina\DDD\Queue\QueueManager;
use Lumina\DDD\Queue\Worker;

/**
 * Command to process queued jobs.
 */
final class QueueWorkCommand extends AbstractCommand
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
        $this->setName('queue:work')
            ->setDescription('Process jobs from the queue')
            ->addOption('connection', [
                'shortcut' => 'c',
                'description' => 'The name of the queue connection to use',
            ])
            ->addOption('queue', [
                'shortcut' => 'Q',
                'description' => 'The queue(s) to process',
                'default' => 'default',
            ])
            ->addOption('sleep', [
                'shortcut' => 's',
                'description' => 'Number of seconds to sleep when no job is available',
                'default' => '3',
            ])
            ->addOption('tries', [
                'shortcut' => 't',
                'description' => 'Number of times to attempt a job before logging it as failed',
                'default' => '3',
            ])
            ->addOption('timeout', [
                'description' => 'The number of seconds a child process can run',
                'default' => '60',
            ])
            ->addOption('memory', [
                'shortcut' => 'm',
                'description' => 'The memory limit in megabytes',
                'default' => '128',
            ])
            ->addOption('once', [
                'description' => 'Only process the next job on the queue',
            ])
            ->addOption('stop-when-empty', [
                'description' => 'Stop when the queue is empty',
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

        $connection = $input->getOption('connection');
        $queue = (string) ($input->getOption('queue') ?? 'default');
        $sleep = (int) ($input->getOption('sleep') ?? 3);
        $tries = (int) ($input->getOption('tries') ?? 3);
        $timeout = (int) ($input->getOption('timeout') ?? 60);
        $memory = (int) ($input->getOption('memory') ?? 128);
        $once = $input->hasOption('once') && $input->getOption('once') !== false;
        $stopWhenEmpty = $input->hasOption('stop-when-empty') && $input->getOption('stop-when-empty') !== false;

        $output->info('Starting queue worker...');
        $output->newLine();
        $output->writeln("  Connection: <comment>" . ($connection ?? 'default') . "</comment>");
        $output->writeln("  Queue: <comment>{$queue}</comment>");
        $output->writeln("  Sleep: <comment>{$sleep}s</comment>");
        $output->writeln("  Tries: <comment>{$tries}</comment>");
        $output->writeln("  Timeout: <comment>{$timeout}s</comment>");
        $output->writeln("  Memory limit: <comment>{$memory}MB</comment>");
        $output->newLine();

        /** @var QueueManager $manager */
        $manager = $this->container->get(QueueManager::class);
        
        $queueInstance = $connection !== null && $connection !== false
            ? $manager->connection((string) $connection) 
            : $manager->connection();

        $worker = new Worker($queueInstance, $this->container);

        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            
            pcntl_signal(SIGTERM, function () use ($worker, $output) {
                $output->newLine();
                $output->comment('Received SIGTERM, shutting down gracefully...');
                $worker->stop();
            });
            
            pcntl_signal(SIGINT, function () use ($worker, $output) {
                $output->newLine();
                $output->comment('Received SIGINT, shutting down gracefully...');
                $worker->stop();
            });
        }

        // Process jobs
        if ($once) {
            $this->processOne($worker, $queue, $tries, $timeout, $output);
        } else {
            $this->daemon($worker, $queue, $sleep, $tries, $timeout, $memory, $stopWhenEmpty, $output);
        }

        return 0;
    }

    /**
     * Process a single job.
     */
    private function processOne(
        Worker $worker,
        string $queue,
        int $tries,
        int $timeout,
        Output $output,
    ): void {
        $job = $worker->getQueue()->pop($queue);

        if ($job === null) {
            $output->comment('No jobs available.');
            return;
        }

        $output->writeln("<info>Processing:</info> {$job->getName()} [{$job->getId()}]");

        try {
            $worker->process($job, $tries, $timeout);
            $output->writeln("<info>Processed:</info> {$job->getName()} [{$job->getId()}]");
        } catch (\Throwable $e) {
            $output->error("Failed: {$job->getName()} [{$job->getId()}]");
            $output->writeln("  <error>{$e->getMessage()}</error>");
        }
    }

    /**
     * Run as a daemon, continuously processing jobs.
     */
    private function daemon(
        Worker $worker,
        string $queue,
        int $sleep,
        int $tries,
        int $timeout,
        int $memory,
        bool $stopWhenEmpty,
        Output $output,
    ): void {
        $lastRestart = $this->getLastRestartTime();

        while (true) {
            // Check for restart signal
            if ($this->shouldRestart($lastRestart)) {
                $output->comment('Worker restart signal received, exiting...');
                return;
            }

            $job = $worker->getQueue()->pop($queue);

            if ($job === null) {
                if ($stopWhenEmpty) {
                    $output->comment('Queue is empty, stopping...');
                    return;
                }

                sleep($sleep);
                continue;
            }

            $output->writeln("<info>Processing:</info> {$job->getName()} [{$job->getId()}]");

            try {
                $worker->process($job, $tries, $timeout);
                $output->writeln("<info>Processed:</info> {$job->getName()} [{$job->getId()}]");
            } catch (\Throwable $e) {
                $output->error("Failed: {$job->getName()} [{$job->getId()}]");
                $output->writeln("  <error>{$e->getMessage()}</error>");
            }

            // Memory check
            if ($this->memoryExceeded($memory)) {
                $output->comment('Memory limit exceeded, exiting...');
                return;
            }

            // Check if worker was stopped
            if ($worker->shouldStop()) {
                return;
            }
        }
    }

    /**
     * Check if memory limit is exceeded.
     */
    private function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Get the last restart timestamp.
     */
    private function getLastRestartTime(): ?int
    {
        $file = sys_get_temp_dir() . '/lumina_queue_restart';
        
        if (file_exists($file)) {
            return (int) file_get_contents($file);
        }

        return null;
    }

    /**
     * Check if worker should restart.
     */
    private function shouldRestart(?int $lastRestart): bool
    {
        $file = sys_get_temp_dir() . '/lumina_queue_restart';

        if (!file_exists($file)) {
            return false;
        }

        return (int) file_get_contents($file) !== $lastRestart;
    }
}
