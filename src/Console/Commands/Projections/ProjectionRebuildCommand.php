<?php

declare(strict_types=1);

namespace Luminor\Console\Commands\Projections;

use Luminor\Console\Command;
use Luminor\Domain\Events\ProjectionManager;

/**
 * Rebuild event projections.
 */
final class ProjectionRebuildCommand extends Command
{
    protected string $signature = 'projection:rebuild
                                    {projector? : Specific projector to rebuild}
                                    {--all : Rebuild all projections}';

    protected string $description = 'Rebuild event projections from the event store';

    public function __construct(
        private readonly ProjectionManager $projectionManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectorName = $this->argument('projector');
        $rebuildAll = $this->option('all');

        if ($rebuildAll) {
            $this->info('Rebuilding all projections...');
            $this->projectionManager->rebuildAll();
            $this->info('All projections rebuilt successfully.');
            return self::SUCCESS;
        }

        if ($projectorName) {
            $this->info(sprintf('Rebuilding projection: %s', $projectorName));

            try {
                $this->projectionManager->rebuild($projectorName);
                $this->info('Projection rebuilt successfully.');
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        // Show available projectors
        $projectors = $this->projectionManager->getProjectors();

        if (empty($projectors)) {
            $this->warn('No projectors registered.');
            return self::SUCCESS;
        }

        $this->info('Available projectors:');
        foreach ($projectors as $name => $projector) {
            $this->line(sprintf('  - %s', $name));
        }

        $this->line('');
        $this->line('Use: projection:rebuild {projector} or --all');

        return self::SUCCESS;
    }
}
