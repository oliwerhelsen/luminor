<?php

declare(strict_types=1);

namespace Luminor\Console\Commands\EventStore;

use Luminor\Console\Command;
use Luminor\Domain\Events\EventStoreInterface;

/**
 * List events in the event store.
 */
final class ListEventsCommand extends Command
{
    protected string $signature = 'events:list
                                    {--aggregate= : Filter by aggregate ID}
                                    {--type= : Filter by event type}
                                    {--limit=20 : Number of events to display}';

    protected string $description = 'List events from the event store';

    public function __construct(
        private readonly EventStoreInterface $eventStore
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $aggregateId = $this->option('aggregate');
        $eventType = $this->option('type');
        $limit = (int) $this->option('limit');

        if ($aggregateId) {
            $events = $this->eventStore->getEventsForAggregate($aggregateId);
        } elseif ($eventType) {
            $events = $this->eventStore->getEventsByType($eventType);
        } else {
            $events = $this->eventStore->getAllEvents($limit);
        }

        if (empty($events)) {
            $this->info('No events found.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d event(s):', count($events)));
        $this->line('');

        foreach ($events as $index => $event) {
            $this->line(sprintf('[%d] %s', $index + 1, $event->getEventType()));
            $this->line(sprintf('    Event ID: %s', $event->getEventId()));
            $this->line(sprintf('    Aggregate ID: %s', $event->getAggregateId() ?? 'N/A'));
            $this->line(sprintf('    Occurred: %s', $event->getOccurredOn()->format('Y-m-d H:i:s')));
            $this->line(sprintf('    Payload: %s', json_encode($event->getPayload())));
            $this->line('');
        }

        return self::SUCCESS;
    }
}
