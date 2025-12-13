<?php

declare(strict_types=1);

namespace Luminor\DDD\Console\Commands\EventStore;

use Luminor\DDD\Console\Command;
use Luminor\DDD\Domain\Events\EventStoreInterface;

/**
 * Display event store statistics.
 */
final class EventStatsCommand extends Command
{
    protected string $signature = 'events:stats';

    protected string $description = 'Display event store statistics';

    public function __construct(
        private readonly EventStoreInterface $eventStore
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $totalEvents = $this->eventStore->count();

        $this->info('Event Store Statistics');
        $this->line('======================');
        $this->line('');
        $this->line(sprintf('Total Events: %d', $totalEvents));
        $this->line('');

        if ($totalEvents > 0) {
            $events = $this->eventStore->getAllEvents(1000);
            $eventTypes = [];
            $aggregates = [];

            foreach ($events as $event) {
                $eventType = $event->getEventType();
                $eventTypes[$eventType] = ($eventTypes[$eventType] ?? 0) + 1;

                $aggregateId = $event->getAggregateId();
                if ($aggregateId) {
                    $aggregates[$aggregateId] = ($aggregates[$aggregateId] ?? 0) + 1;
                }
            }

            $this->line('Event Types:');
            arsort($eventTypes);
            foreach (array_slice($eventTypes, 0, 10) as $type => $count) {
                $shortType = substr($type, strrpos($type, '\\') + 1);
                $this->line(sprintf('  - %s: %d', $shortType, $count));
            }

            $this->line('');
            $this->line(sprintf('Unique Aggregates: %d', count($aggregates)));
        }

        return self::SUCCESS;
    }
}
