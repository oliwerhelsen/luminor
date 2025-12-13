<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands;

use Luminor\DDD\Console\Input;
use Luminor\DDD\Console\Output;
use Luminor\DDD\Container\ContainerInterface;
use Luminor\DDD\Queue\FailedJobProviderInterface;

/**
 * Command to flush (delete) all failed jobs.
 */
final class QueueFlushCommand extends AbstractCommand
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
        $this->setName('queue:flush')
            ->setDescription('Flush all failed queue jobs')
            ->addOption('hours', [
                'description' => 'The number of hours to retain failed jobs',
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

        if (! $this->container->has(FailedJobProviderInterface::class)) {
            $output->error('Failed job provider is not configured.');
            $output->writeln('Add a FailedJobProviderInterface binding to your container.');

            return 1;
        }

        /** @var FailedJobProviderInterface $provider */
        $provider = $this->container->get(FailedJobProviderInterface::class);

        $hours = $input->getOption('hours');

        if ($hours !== null && $hours !== false) {
            $provider->flush((int) $hours);
            $output->success("All failed jobs older than {$hours} hour(s) have been deleted.");
        } else {
            $provider->flush();
            $output->success('All failed jobs have been deleted.');
        }

        return 0;
    }
}
